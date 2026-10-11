<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use RuntimeException;

final class CheckoutController extends Controller
{
    public const STATES = [
        'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chandigarh', 'Chhattisgarh',
        'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir',
        'Jharkhand', 'Karnataka', 'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
        'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
        'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    ];

    public function index(Request $request): Response
    {
        $summary = (new CartService())->summary();
        if (!$summary['items']) {
            return redirect('/cart');
        }
        if ($summary['has_issue']) {
            return redirect('/cart')->with('error', 'Please fix the items marked in your bag before checkout.');
        }
        seo()->title('Checkout')->noindex();

        $addresses = db()->select('SELECT * FROM addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC', ['u' => auth()->id()]);
        $methods = PaymentService::availableMethods($summary['totals']['total']);

        return view('store/checkout', $summary + [
            'addresses' => $addresses,
            'methods'   => $methods,
            'codFee'    => paise(setting('cod_fee')),
            'states'    => self::STATES,
        ]);
    }

    public function place(Request $request): Response
    {
        $method = (string) $request->input('payment_method');
        $addressId = $request->int('address_id');

        if ($addressId > 0) {
            $address = db()->first('SELECT * FROM addresses WHERE id = :id AND user_id = :u', ['id' => $addressId, 'u' => auth()->id()]);
            if (!$address) {
                return redirect('/checkout')->with('error', 'Please select a delivery address.');
            }
        } else {
            $data = $this->validate($request, self::addressRules(), ['line1' => 'Address']);
            $data['user_id'] = auth()->id();
            $data['is_default'] = (int) !db()->scalar('SELECT 1 FROM addresses WHERE user_id = :u LIMIT 1', ['u' => auth()->id()]);
            $id = db()->insert('addresses', $data);
            $address = db()->first('SELECT * FROM addresses WHERE id = :id', ['id' => $id]);
        }

        try {
            $order = OrderService::place((int) auth()->id(), $address, $method, (string) $request->input('notes', ''));
        } catch (RuntimeException $e) {
            return redirect('/checkout')->with('error', $e->getMessage());
        }

        return $method === 'cod'
            ? redirect('/order/success/' . $order['order_number'])
            : redirect('/checkout/pay/' . $order['order_number']);
    }

    public static function addressRules(): array
    {
        return [
            'name'     => 'required|max:120',
            'phone'    => 'required|phone',
            'line1'    => 'required|max:190',
            'line2'    => 'nullable|max:190',
            'landmark' => 'nullable|max:120',
            'city'     => 'required|max:80',
            'state'    => 'required|in:' . implode(',', self::STATES),
            'pincode'  => 'required|pincode',
            'type'     => 'nullable|in:home,work,other',
        ];
    }
}
