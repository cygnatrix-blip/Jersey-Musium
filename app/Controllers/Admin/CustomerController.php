<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\NotificationService;

/**
 * Customer (user) management: search/filter, view 360°, edit details, block/unblock,
 * send password-reset link, and anonymise on request (DPDP Act 2023 right to erasure).
 */
final class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $where = ['1 = 1'];
        $params = [];
        if ($q = $request->query('q')) {
            $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR u.phone LIKE :q3)';
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        if (in_array($s = $request->query('status'), ['active', 'blocked', 'deleted'], true)) {
            $where[] = 'u.status = :st';
            $params['st'] = $s;
        }
        $w = implode(' AND ', $where);
        return $this->admin('customers/index', [
            'title'     => 'Customers',
            'counts'    => array_column(db()->select('SELECT status, COUNT(*) c FROM users GROUP BY status'), 'c', 'status'),
            'customers' => Paginator::query(
                "SELECT u.*,
                        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status NOT IN ('pending_payment','payment_failed','cancelled')) AS orders,
                        (SELECT COALESCE(SUM(grand_total - refunded_total), 0) FROM orders o2 WHERE o2.user_id = u.id AND o2.status NOT IN ('pending_payment','payment_failed','cancelled')) AS ltv
                   FROM users u WHERE $w ORDER BY u.id DESC",
                $params,
                (int) config('app.admin_per_page'),
                null,
                "SELECT COUNT(*) FROM users u WHERE $w"
            ),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $u = $this->find((int) $id);
        return $this->admin('customers/show', [
            'title'     => $u['name'],
            'u'         => $u,
            'orders'    => db()->select('SELECT * FROM orders WHERE user_id = :id ORDER BY id DESC LIMIT 50', ['id' => $u['id']]),
            'addresses' => db()->select('SELECT * FROM addresses WHERE user_id = :id', ['id' => $u['id']]),
            'stats'     => db()->first(
                "SELECT (SELECT COUNT(*) FROM wishlists WHERE user_id = :a) wishlist,
                        (SELECT COUNT(*) FROM product_reviews WHERE user_id = :b) reviews,
                        (SELECT COUNT(*) FROM returns WHERE user_id = :c) returns_count,
                        (SELECT COALESCE(SUM(grand_total - refunded_total), 0) FROM orders WHERE user_id = :d AND status NOT IN ('pending_payment','payment_failed','cancelled')) ltv",
                ['a' => $u['id'], 'b' => $u['id'], 'c' => $u['id'], 'd' => $u['id']]
            ),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $u = $this->find((int) $id);
        if ($u['status'] === 'deleted') {
            return back()->with('error', 'Anonymised accounts cannot be edited.');
        }
        $data = $this->validate($request, [
            'name'       => 'required|min:2|max:120',
            'email'      => 'required|email|unique:users,email,' . $u['id'],
            'phone'      => 'nullable|phone',
            'admin_note' => 'nullable|max:500',
        ]);
        $data['email'] = strtolower($data['email']);
        if ($data['email'] !== $u['email']) {
            $data['email_verified_at'] = null; // new address must be verified by the customer again
        }
        db()->update('users', $data, 'id = :id', ['id' => $u['id']]);
        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', array_intersect_key($u, $data))));
        AuditService::log('customer.update', 'user', (int) $u['id'], ['changed' => $changed]);
        return back()->with('success', 'Customer details saved.');
    }

    public function toggle(Request $request, string $id): Response
    {
        $u = $this->find((int) $id);
        if ($u['status'] === 'deleted') {
            return back()->with('error', 'This account has been anonymised.');
        }
        $status = $u['status'] === 'active' ? 'blocked' : 'active';
        db()->update('users', ['status' => $status], 'id = :id', ['id' => $u['id']]);
        AuditService::log('customer.' . $status, 'user', (int) $id);
        return back()->with('success', 'Customer ' . ($status === 'blocked' ? 'blocked – they are signed out on their next request.' : 'unblocked.'));
    }

    /** Email the customer a password-reset link (admins never see or set customer passwords). */
    public function sendReset(Request $request, string $id): Response
    {
        $u = $this->find((int) $id);
        if ($u['status'] !== 'active') {
            return back()->with('error', 'Only active accounts can reset their password.');
        }
        $token = bin2hex(random_bytes(32));
        db()->execute('DELETE FROM password_resets WHERE email = :e', ['e' => $u['email']]);
        db()->insert('password_resets', ['email' => $u['email'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
        NotificationService::passwordReset($u['email'], url('/reset-password/' . $token));
        AuditService::log('customer.reset_link', 'user', (int) $id);
        return back()->with('success', 'Password reset link emailed to ' . $u['email'] . '.');
    }

    /**
     * Right to erasure: scrub personal data from the account while keeping order/invoice records
     * (GST law requires invoices to be retained for 6+ years; orders keep their own address snapshot).
     */
    public function anonymise(Request $request, string $id): Response
    {
        $u = $this->find((int) $id);
        if ($u['status'] === 'deleted') {
            return back()->with('info', 'Already anonymised.');
        }
        if (strtoupper(trim((string) $request->input('confirm'))) !== 'DELETE') {
            return back()->with('error', 'Type DELETE to confirm anonymisation.');
        }
        $open = (int) db()->scalar("SELECT COUNT(*) FROM orders WHERE user_id = :u AND status IN ('pending_payment','confirmed','packed','shipped','out_for_delivery','return_requested')", ['u' => $u['id']]);
        if ($open > 0) {
            return back()->with('error', "Customer has $open open order(s). Complete or cancel them first.");
        }
        db()->transaction(function () use ($u) {
            db()->update('users', [
                'name' => 'Deleted user', 'email' => 'deleted+' . $u['id'] . '@invalid.local', 'phone' => null,
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'status' => 'deleted', 'admin_note' => 'Anonymised on ' . date('Y-m-d') . ' at customer request',
            ], 'id = :id', ['id' => $u['id']]);
            foreach (['addresses', 'wishlists', 'carts'] as $t) {
                db()->execute("DELETE FROM `$t` WHERE user_id = :u", ['u' => $u['id']]);
            }
            db()->execute('DELETE FROM password_resets WHERE email = :e', ['e' => $u['email']]);
            db()->execute('DELETE FROM newsletter_subscribers WHERE email = :e', ['e' => $u['email']]);
        });
        AuditService::log('customer.anonymise', 'user', (int) $u['id']);
        return back()->with('success', 'Personal data erased. Order & invoice records retained for tax compliance.');
    }

    private function find(int $id): array
    {
        return db()->first('SELECT * FROM users WHERE id = :id', ['id' => $id]) ?? throw new HttpException(404);
    }
}
