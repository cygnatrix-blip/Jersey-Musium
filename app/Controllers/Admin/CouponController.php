<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;

final class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = db()->select(
            'SELECT c.*, cat.name AS category_name,
                    (SELECT COALESCE(SUM(discount), 0) FROM coupon_redemptions r WHERE r.coupon_id = c.id) AS total_discount
               FROM coupons c LEFT JOIN categories cat ON cat.id = c.category_id ORDER BY c.id DESC'
        );
        return $this->admin('coupons/index', ['title' => 'Promo codes', 'coupons' => $rows]);
    }

    public function create(Request $request): Response
    {
        return $this->admin('coupons/form', ['title' => 'Create promo code', 'c' => null, 'categories' => db()->select('SELECT id, name FROM categories ORDER BY name')]);
    }

    public function edit(Request $request, string $id): Response
    {
        $c = db()->first('SELECT * FROM coupons WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->admin('coupons/form', ['title' => 'Edit ' . $c['code'], 'c' => $c, 'categories' => db()->select('SELECT id, name FROM categories ORDER BY name')]);
    }

    public function store(Request $request): Response
    {
        $data = $this->data($request, null);
        $id = db()->insert('coupons', $data);
        AuditService::log('coupon.create', 'coupon', $id, ['code' => $data['code']]);
        return redirect('/admin/coupons')->with('success', 'Promo code ' . $data['code'] . ' created.');
    }

    public function update(Request $request, string $id): Response
    {
        db()->first('SELECT id FROM coupons WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $data = $this->data($request, (int) $id);
        db()->update('coupons', $data, 'id = :id', ['id' => (int) $id]);
        AuditService::log('coupon.update', 'coupon', (int) $id, ['code' => $data['code']]);
        return redirect('/admin/coupons')->with('success', 'Promo code saved.');
    }

    public function destroy(Request $request, string $id): Response
    {
        if (db()->scalar('SELECT 1 FROM coupon_redemptions WHERE coupon_id = :id LIMIT 1', ['id' => (int) $id])) {
            db()->update('coupons', ['is_active' => 0], 'id = :id', ['id' => (int) $id]);
            return back()->with('info', 'Coupon has been used on orders, so it was deactivated instead of deleted.');
        }
        db()->execute('DELETE FROM coupons WHERE id = :id', ['id' => (int) $id]);
        AuditService::log('coupon.delete', 'coupon', (int) $id);
        return redirect('/admin/coupons')->with('success', 'Promo code deleted.');
    }

    private function data(Request $request, ?int $id): array
    {
        $input = $request->all();
        $input['code'] = strtoupper(preg_replace('/\s+/', '', (string) ($input['code'] ?? '')));
        $v = \App\Core\Validator::make($input, [
            'code'             => 'required|min:3|max:40|unique:coupons,code' . ($id ? ",$id" : ''),
            'description'      => 'nullable|max:255',
            'type'             => 'required|in:percent,flat',
            'value'            => 'required|numeric|min:1',
            'max_discount'     => 'nullable|numeric|min:1',
            'min_order_amount' => 'nullable|numeric|min:0',
            'category_id'      => 'nullable|integer|exists:categories,id',
            'usage_limit'      => 'nullable|integer|min:1',
            'per_user_limit'   => 'required|integer|min:1',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date',
            'first_order_only' => 'boolean',
            'is_active'        => 'boolean',
            'is_public'        => 'boolean',
        ]);
        if ($v->fails()) {
            throw new ValidationException($v->errors());
        }
        $data = $v->validated();
        if (!preg_match('/^[A-Z0-9_-]+$/', $data['code'])) {
            throw new ValidationException(['code' => ['Use only letters, numbers, - and _.']]);
        }
        if ($data['type'] === 'percent' && (float) $data['value'] > 90) {
            throw new ValidationException(['value' => ['Percentage discount cannot exceed 90%.']]);
        }
        if ($data['starts_at'] && $data['ends_at'] && strtotime($data['ends_at']) <= strtotime($data['starts_at'])) {
            throw new ValidationException(['ends_at' => ['End date must be after start date.']]);
        }
        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;
        $data['starts_at'] = $data['starts_at'] ? date('Y-m-d H:i:s', strtotime($data['starts_at'])) : null;
        $data['ends_at'] = $data['ends_at'] ? date('Y-m-d H:i:s', strtotime($data['ends_at'])) : null;
        return $data;
    }
}
