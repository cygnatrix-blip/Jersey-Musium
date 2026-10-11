<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\CatalogService;

final class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';
        return $this->admin('reviews/index', [
            'title'   => 'Reviews',
            'status'  => $status,
            'reviews' => Paginator::query(
                'SELECT r.*, u.name AS user_name, p.name AS product_name, p.slug FROM product_reviews r
                   JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
                  WHERE r.status = :s ORDER BY r.id DESC',
                ['s' => $status],
                (int) config('app.admin_per_page')
            ),
        ]);
    }

    public function moderate(Request $request, string $id): Response
    {
        $r = db()->first('SELECT * FROM product_reviews WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $status = $request->input('status');
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return back()->with('error', 'Invalid action.');
        }
        db()->update('product_reviews', ['status' => $status], 'id = :id', ['id' => $r['id']]);
        CatalogService::refreshRating((int) $r['product_id']);
        AuditService::log('review.' . $status, 'review', (int) $id);
        return back()->with('success', 'Review ' . $status . '.');
    }
}
