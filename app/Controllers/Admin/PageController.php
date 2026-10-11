<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;

/** CMS pages: About, Privacy, Terms, Shipping, Returns & Refund policy (required for gateway KYC). */
final class PageController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->admin('pages/index', ['title' => 'Pages', 'pages' => db()->select('SELECT * FROM pages ORDER BY title')]);
    }

    public function create(Request $request): Response
    {
        return $this->admin('pages/form', ['title' => 'New page', 'pg' => null]);
    }

    public function edit(Request $request, string $id): Response
    {
        $pg = db()->first('SELECT * FROM pages WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->admin('pages/form', ['title' => 'Edit ' . $pg['title'], 'pg' => $pg]);
    }

    public function store(Request $request): Response
    {
        $data = $this->data($request, null);
        $id = db()->insert('pages', $data);
        AuditService::log('page.create', 'page', $id);
        return redirect('/admin/pages')->with('success', 'Page created.');
    }

    public function update(Request $request, string $id): Response
    {
        $data = $this->data($request, (int) $id);
        db()->update('pages', $data, 'id = :id', ['id' => (int) $id]);
        AuditService::log('page.update', 'page', (int) $id);
        return redirect('/admin/pages')->with('success', 'Page saved.');
    }

    private function data(Request $request, ?int $id): array
    {
        return $this->validate($request, [
            'title'            => 'required|max:140',
            'slug'             => 'required|slug|max:140|unique:pages,slug' . ($id ? ",$id" : ''),
            'content'          => 'required|max:200000',
            'meta_description' => 'nullable|max:255',
            'show_in_footer'   => 'boolean',
            'is_active'        => 'boolean',
        ]);
    }
}
