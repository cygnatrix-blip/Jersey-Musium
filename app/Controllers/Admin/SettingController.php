<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\PaymentService;
use App\Services\SettingService;

final class SettingController extends Controller
{
    private const BOOLEANS = ['cod_enabled', 'razorpay_enabled', 'cashfree_enabled', 'loader_enabled'];
    private const NUMBERS = ['free_shipping_threshold', 'shipping_fee', 'delivery_days_min', 'delivery_days_max', 'cod_fee', 'cod_max_amount',
                             'return_window_days', 'low_stock_threshold', 'pending_payment_expiry_minutes'];

    public function index(Request $request): Response
    {
        return $this->admin('settings', [
            'title'    => 'Settings',
            's'        => SettingService::all(),
            'gateways' => [
                'razorpay' => PaymentService::gateway('razorpay')->isConfigured(),
                'cashfree' => PaymentService::gateway('cashfree')->isConfigured(),
            ],
            'webhooks' => ['razorpay' => url('/webhooks/razorpay'), 'cashfree' => url('/webhooks/cashfree')],
        ]);
    }

    public function update(Request $request): Response
    {
        $values = [];
        $errors = [];
        foreach (array_keys(SettingService::DEFAULTS) as $key) {
            if (in_array($key, self::BOOLEANS, true)) {
                $values[$key] = $request->bool($key) ? '1' : '0';
                continue;
            }
            $v = $request->input($key);
            if ($v === null) {
                continue;
            }
            $v = trim((string) $v);
            if (in_array($key, self::NUMBERS, true) && ($v === '' || !is_numeric($v) || (float) $v < 0)) {
                $errors[$key] = ['Must be a positive number.'];
                continue;
            }
            if (str_starts_with($key, 'social_') && $v !== '' && (!filter_var($v, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $v))) {
                $errors[$key] = ['Must be a full URL (https://…).'];
                continue;
            }
            $values[$key] = mb_substr($v, 0, 1000);
        }
        if (!in_array($request->input('loader_frequency'), ['session', 'always'], true)) {
            unset($values['loader_frequency']);
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }
        SettingService::set($values);
        AuditService::log('settings.update', null, null, ['keys' => array_keys($values)]);
        return back()->with('success', 'Settings saved.');
    }
}
