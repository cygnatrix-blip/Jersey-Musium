<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\ImageUploader;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;
use RuntimeException;

/** Offer banners: homepage hero slider, promo strips, listing-page banners – with scheduling. */
final class BannerController extends Controller
{
    public const PLACEMENTS = [
        'home_hero'   => 'Home – hero slider (1600×610, mobile 800×1000)',
        'home_strip'  => 'Home – promo strip (800×450, max 3)',
        'home_grid'   => 'Home – lower grid (800×450)',
        'listing_top' => 'Listing pages – top banner (1600×220)',
    ];

    public function index(Request $request): Response
    {
        return $this->admin('banners/index', [
            'title'   => 'Offer banners',
            'banners' => db()->select('SELECT * FROM banners ORDER BY placement, sort_order, id DESC'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->admin('banners/form', ['title' => 'New banner', 'b' => null]);
    }

    public function edit(Request $request, string $id): Response
    {
        $b = db()->first('SELECT * FROM banners WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->admin('banners/form', ['title' => 'Edit banner', 'b' => $b]);
    }

    public function store(Request $request): Response
    {
        $data = $this->data($request, true);
        $id = db()->insert('banners', $data);
        AuditService::log('banner.create', 'banner', $id, ['title' => $data['title']]);
        return redirect('/admin/banners')->with('success', 'Banner published.');
    }

    public function update(Request $request, string $id): Response
    {
        $b = db()->first('SELECT * FROM banners WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $data = $this->data($request, false);
        foreach (['image_desktop', 'image_mobile'] as $k) {
            if (isset($data[$k]) && $b[$k]) {
                ImageUploader::delete($b[$k]);
            }
        }
        db()->update('banners', $data, 'id = :id', ['id' => (int) $id]);
        AuditService::log('banner.update', 'banner', (int) $id);
        return redirect('/admin/banners')->with('success', 'Banner saved.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $b = db()->first('SELECT * FROM banners WHERE id = :id', ['id' => (int) $id]);
        if ($b) {
            db()->execute('DELETE FROM banners WHERE id = :id', ['id' => $b['id']]);
            ImageUploader::delete($b['image_desktop']);
            ImageUploader::delete($b['image_mobile']);
            AuditService::log('banner.delete', 'banner', (int) $id);
        }
        return redirect('/admin/banners')->with('success', 'Banner deleted.');
    }

    private function data(Request $request, bool $creating): array
    {
        $data = $this->validate($request, [
            'title'      => 'required|max:140',
            'subtitle'   => 'nullable|max:255',
            'cta_label'  => 'nullable|max:40',
            'link_url'   => 'nullable|url|max:255',
            'placement'  => 'required|in:' . implode(',', array_keys(self::PLACEMENTS)),
            'theme'      => 'required|in:dark,light',
            'sort_order' => 'nullable|integer',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date',
            'is_active'  => 'boolean',
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['starts_at'] = $data['starts_at'] ? date('Y-m-d H:i:s', strtotime($data['starts_at'])) : null;
        $data['ends_at'] = $data['ends_at'] ? date('Y-m-d H:i:s', strtotime($data['ends_at'])) : null;
        try {
            if ($f = $request->file('image_desktop')) {
                $data['image_desktop'] = ImageUploader::store($f, 'banners', 1920);
            } elseif ($creating) {
                throw new ValidationException(['image_desktop' => ['Desktop image is required.']]);
            }
            if ($f = $request->file('image_mobile')) {
                $data['image_mobile'] = ImageUploader::store($f, 'banners', 1000);
            }
        } catch (RuntimeException $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            throw new ValidationException(['image_desktop' => [$e->getMessage()]]);
        }
        return $data;
    }
}
