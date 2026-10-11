<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;
use App\Services\ReturnService;
use App\Services\ShipmentService;
use RuntimeException;

final class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        seo()->title('My Account')->noindex();
        $orders = db()->select('SELECT * FROM orders WHERE user_id = :u ORDER BY id DESC LIMIT 3', ['u' => auth()->id()]);
        return view('store/account/index', ['user' => auth()->user(), 'orders' => $orders]);
    }

    public function updateProfile(Request $request): Response
    {
        $data = $this->validate($request, ['name' => 'required|min:2|max:120', 'phone' => 'required|phone']);
        db()->update('users', $data, 'id = :id', ['id' => auth()->id()]);
        return redirect('/account')->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): Response
    {
        $data = $this->validate($request, ['current_password' => 'required', 'password' => 'required|min:8|max:200|confirmed']);
        $hash = (string) db()->scalar('SELECT password_hash FROM users WHERE id = :id', ['id' => auth()->id()]);
        if (!password_verify($data['current_password'], $hash)) {
            return redirect('/account')->withErrors(['current_password' => ['Current password is incorrect.']]);
        }
        db()->update('users', ['password_hash' => password_hash($data['password'], PASSWORD_DEFAULT)], 'id = :id', ['id' => auth()->id()]);
        session()->regenerate();
        return redirect('/account')->with('success', 'Password changed.');
    }

    public function orders(Request $request): Response
    {
        seo()->title('My Orders')->noindex();
        $orders = Paginator::query(
            'SELECT o.*, (SELECT oi.image FROM order_items oi WHERE oi.order_id = o.id LIMIT 1) AS image,
                    (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id) AS item_count
               FROM orders o WHERE o.user_id = :u ORDER BY o.id DESC',
            ['u' => auth()->id()],
            10
        );
        return view('store/account/orders', ['orders' => $orders]);
    }

    public function order(Request $request, string $number): Response
    {
        $order = $this->ownedOrder($number);
        seo()->title('Order ' . $number)->noindex();
        return view('store/account/order', [
            'order'      => $order,
            'items'      => OrderService::items((int) $order['id']),
            'history'    => OrderService::history((int) $order['id']),
            'shipments'  => ShipmentService::forOrder((int) $order['id']),
            'returns'    => db()->select('SELECT r.*, oi.product_name, oi.size FROM returns r JOIN order_items oi ON oi.id = r.order_item_id WHERE r.order_id = :id ORDER BY r.id DESC', ['id' => $order['id']]),
            'refunds'    => db()->select('SELECT * FROM refunds WHERE order_id = :id ORDER BY id DESC', ['id' => $order['id']]),
            'canCancel'  => in_array($order['status'], OrderService::CUSTOMER_CANCELLABLE, true),
            'canReturn'  => (bool) ReturnService::returnableItems($order),
            'address'    => OrderService::address($order),
        ]);
    }

    public function invoice(Request $request, string $number): Response
    {
        return \App\Services\InvoiceService::render($this->ownedOrder($number));
    }

    public function cancel(Request $request, string $number): Response
    {
        $order = $this->ownedOrder($number);
        $reason = mb_substr((string) $request->input('reason', 'Cancelled by customer'), 0, 200) ?: 'Cancelled by customer';
        try {
            OrderService::cancel($order, 'Customer: ' . $reason, 'customer', auth()->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect('/account/orders/' . $number)->with('success', 'Your order has been cancelled.' . ($order['payment_status'] === 'paid' ? ' Refund initiated to your original payment method.' : ''));
    }

    public function returnForm(Request $request, string $number): Response
    {
        $order = $this->ownedOrder($number);
        $items = ReturnService::returnableItems($order);
        if (!$items) {
            return redirect('/account/orders/' . $number)->with('error', 'No items in this order are eligible for return.');
        }
        seo()->title('Return / Exchange')->noindex();
        return view('store/account/return', ['order' => $order, 'items' => $items, 'reasons' => ReturnService::REASONS]);
    }

    public function returnStore(Request $request, string $number): Response
    {
        $order = $this->ownedOrder($number);
        $data = $this->validate($request, [
            'order_item_id' => 'required|integer',
            'qty'           => 'required|integer|min:1',
            'type'          => 'required|in:return,exchange',
            'reason'        => 'required|in:' . implode(',', array_keys(ReturnService::REASONS)),
            'details'       => 'nullable|max:1000',
            'exchange_size' => 'nullable|in:' . implode(',', config('app.sizes')),
            'refund_upi'    => 'nullable|max:80',
        ]);
        try {
            $ret = ReturnService::request($order, (int) $data['order_item_id'], (int) $data['qty'], $data['type'], $data['reason'], $data['details'], $data['exchange_size'], $data['refund_upi']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return redirect('/account/orders/' . $number)->with('success', 'Return request ' . $ret['rma_number'] . ' submitted. We will schedule a pickup shortly.');
    }

    public function addresses(Request $request): Response
    {
        seo()->title('Saved Addresses')->noindex();
        return view('store/account/addresses', [
            'addresses' => db()->select('SELECT * FROM addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC', ['u' => auth()->id()]),
            'states'    => CheckoutController::STATES,
        ]);
    }

    public function addressStore(Request $request): Response
    {
        $data = $this->validate($request, CheckoutController::addressRules(), ['line1' => 'Address']);
        $data['user_id'] = auth()->id();
        $first = !db()->scalar('SELECT 1 FROM addresses WHERE user_id = :u LIMIT 1', ['u' => auth()->id()]);
        $data['is_default'] = (int) ($first || $request->bool('is_default'));
        if ($data['is_default']) {
            db()->execute('UPDATE addresses SET is_default = 0 WHERE user_id = :u', ['u' => auth()->id()]);
        }
        db()->insert('addresses', $data);
        return back('/account/addresses')->with('success', 'Address saved.');
    }

    public function addressDelete(Request $request, string $id): Response
    {
        db()->execute('DELETE FROM addresses WHERE id = :id AND user_id = :u', ['id' => (int) $id, 'u' => auth()->id()]);
        return redirect('/account/addresses')->with('success', 'Address removed.');
    }

    public function addressDefault(Request $request, string $id): Response
    {
        if (db()->scalar('SELECT 1 FROM addresses WHERE id = :id AND user_id = :u', ['id' => (int) $id, 'u' => auth()->id()])) {
            db()->execute('UPDATE addresses SET is_default = (id = :id) WHERE user_id = :u', ['id' => (int) $id, 'u' => auth()->id()]);
        }
        return redirect('/account/addresses');
    }

    private function ownedOrder(string $number): array
    {
        return OrderService::findByNumber($number, auth()->id()) ?? throw new HttpException(404);
    }
}
