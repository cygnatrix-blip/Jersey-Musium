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

final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = db()->select(
            'SELECT c.*, p.name AS parent_name,
                    (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.id AND pr.deleted_at IS NULL) AS product_count
               FROM categories c LEFT JOIN categories p ON p.id = c.parent_id
              ORDER BY COALESCE(p.sort_order, c.sort_order), COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order, c.name'
        );
        return $this->admin('categories/index', ['title' => 'Categories', 'categories' => $rows]);
    }

    public function create(Request $request): Response
    {
        return $this->admin('categories/form', ['title' => 'Add category', 'c' => null, 'parents' => $this->parents(null)]);
    }

    public function edit(Request $request, string $id): Response
    {
        $c = db()->first('SELECT * FROM categories WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->admin('categories/form', ['title' => 'Edit category', 'c' => $c, 'parents' => $this->parents((int) $id)]);
    }

    public function store(Request $request): Response
    {
        $data = $this->data($request, null);
        $id = db()->insert('categories', $data);
        AuditService::log('category.create', 'category', $id, ['name' => $data['name']]);
        return redirect('/admin/categories')->with('success', 'Category created.');
    }

    public function update(Request $request, string $id): Response
    {
        $c = db()->first('SELECT * FROM categories WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $data = $this->data($request, (int) $id);
        if (isset($data['image']) && $c['image']) {
            ImageUploader::delete($c['image']);
        }
        db()->update('categories', $data, 'id = :id', ['id' => (int) $id]);
        AuditService::log('category.update', 'category', (int) $id);
        return redirect('/admin/categories')->with('success', 'Category saved.');
    }

    public function destroy(Request $request, string $id): Response
    {
        if (db()->scalar('SELECT 1 FROM products WHERE category_id = :id LIMIT 1', ['id' => (int) $id])) {
            return back()->with('error', 'Move or archive the products in this category first (or deactivate it instead).');
        }
        db()->execute('DELETE FROM categories WHERE id = :id', ['id' => (int) $id]);
        AuditService::log('category.delete', 'category', (int) $id);
        return redirect('/admin/categories')->with('success', 'Category deleted.');
    }

    private function data(Request $request, ?int $id): array
    {
        $data = $this->validate($request, [
            'name'             => 'required|max:120',
            'slug'             => 'required|slug|max:140|unique:categories,slug' . ($id ? ",$id" : ''),
            'parent_id'        => 'nullable|integer|exists:categories,id',
            'description'      => 'nullable|max:2000',
            'meta_title'       => 'nullable|max:190',
            'meta_description' => 'nullable|max:255',
            'sort_order'       => 'nullable|integer',
            'show_in_menu'     => 'boolean',
            'is_active'        => 'boolean',
        ]);
        if ($id && (int) $data['parent_id'] === $id) {
            throw new ValidationException(['parent_id' => ['A category cannot be its own parent.']]);
        }
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        if ($file = $request->file('image')) {
            try {
                $data['image'] = ImageUploader::store($file, 'categories', 800);
            } catch (RuntimeException $e) {
                throw new ValidationException(['image' => [$e->getMessage()]]);
            }
        }
        return $data;
    }

    private function parents(?int $exclude): array
    {
        return db()->select('SELECT id, name FROM categories WHERE parent_id IS NULL AND id <> :ex ORDER BY name', ['ex' => $exclude ?? 0]);
    }
}
