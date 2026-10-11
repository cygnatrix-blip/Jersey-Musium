<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;
use App\Services\CouponService;
use RuntimeException;

final class CartController extends Controller
{
    public function index(Request $request): Response
    {
        seo()->title('Shopping Bag')->noindex();
        $summary = (new CartService())->summary();
        return view('store/cart', $summary + ['offers' => CouponService::publicOffers()]);
    }

    public function count(Request $request): Response
    {
        return json(['count' => (new CartService())->count()]);
    }

    public function add(Request $request): Response
    {
        $data = $this->validate($request, [
            'variant_id'    => 'required|integer',
            'qty'           => 'nullable|integer|between:1,10',
            'custom_name'   => 'nullable|max:15',
            'custom_number' => 'nullable|max:2',
        ], ['variant_id' => 'Size']);

        $cart = new CartService();
        try {
            $cart->add((int) $data['variant_id'], (int) ($data['qty'] ?? 1), $data['custom_name'], $data['custom_number']);
        } catch (RuntimeException $e) {
            return $request->wantsJson() ? json(['ok' => false, 'message' => $e->getMessage()], 422) : back()->with('error', $e->getMessage());
        }

        if ($request->bool('buy_now')) {
            return $request->wantsJson() ? json(['ok' => true, 'redirect' => '/checkout']) : redirect('/checkout');
        }
        return $request->wantsJson()
            ? json(['ok' => true, 'message' => 'Added to bag', 'count' => $cart->count()])
            : redirect('/cart')->with('success', 'Added to bag');
    }

    public function update(Request $request): Response
    {
        try {
            (new CartService())->update($request->int('item_id'), $request->int('qty'));
        } catch (RuntimeException $e) {
            return redirect('/cart')->with('error', $e->getMessage());
        }
        return redirect('/cart');
    }

    public function remove(Request $request): Response
    {
        (new CartService())->remove($request->int('item_id'));
        return redirect('/cart')->with('success', 'Item removed from bag');
    }

    public function applyCoupon(Request $request): Response
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return redirect('/cart')->with('error', 'Enter a coupon code.');
        }
        try {
            $res = (new CartService())->applyCoupon($code);
        } catch (RuntimeException $e) {
            return redirect($request->input('from') === 'checkout' ? '/checkout' : '/cart')->with('error', $e->getMessage());
        }
        return redirect($request->input('from') === 'checkout' ? '/checkout' : '/cart')
            ->with('success', $res['code'] . ' applied! You saved ' . money($res['discount'] / 100));
    }

    public function removeCoupon(Request $request): Response
    {
        (new CartService())->removeCoupon();
        return redirect($request->input('from') === 'checkout' ? '/checkout' : '/cart');
    }
}
