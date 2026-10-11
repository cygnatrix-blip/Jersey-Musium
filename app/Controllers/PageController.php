<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;
use App\Services\ShipmentService;

final class PageController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $page = db()->first('SELECT * FROM pages WHERE slug = :s AND is_active = 1', ['s' => $slug]) ?? throw new HttpException(404);
        seo()->title($page['title'])->description($page['meta_description'] ?: str_limit($page['content']))->canonical('/page/' . $slug)
            ->breadcrumbs([['Home', '/'], [$page['title'], null]]);
        return view('store/page', ['page' => $page]);
    }

    /** Guest order tracking by order number + email. */
    public function track(Request $request): Response
    {
        seo()->title('Track your order')->description('Track your ' . setting('store_name') . ' order status and shipment.');
        $order = null;
        $error = null;
        if ($request->isPost()) {
            $number = strtoupper(trim((string) $request->input('order_number')));
            $email = strtolower(trim((string) $request->input('email')));
            $order = db()->first('SELECT * FROM orders WHERE order_number = :n AND customer_email = :e', ['n' => $number, 'e' => $email]);
            if (!$order) {
                $error = 'No order found with those details.';
            }
        }
        return view('store/track', [
            'order'     => $order,
            'error'     => $error,
            'history'   => $order ? OrderService::history((int) $order['id']) : [],
            'shipments' => $order ? ShipmentService::forOrder((int) $order['id']) : [],
        ]);
    }

    public function newsletter(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Please enter a valid email.');
        }
        db()->execute('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (:e)', ['e' => $email]);
        return back()->with('success', 'You\'re on the team sheet! Watch your inbox for drops & offers.');
    }
}
