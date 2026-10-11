<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\ImageUploader;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\InventoryService;
use RuntimeException;

final class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($q = $request->query('q')) {
            $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2 OR p.team LIKE :q3)';
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        if ($cat = $request->int('category')) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = $cat;
        }
        $status = $request->query('status');
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'p.is_active = ' . ($status === 'active' ? 1 : 0);
        }
        $having = $request->query('stock') === 'low' ? ' HAVING stock <= 5' : ($request->query('stock') === 'out' ? ' HAVING stock = 0' : '');

        $sql = 'SELECT p.*, c.name AS category_name,
                       (SELECT i.path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image,
                       (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS stock
                  FROM products p JOIN categories c ON c.id = p.category_id
                 WHERE ' . implode(' AND ', $where) . $having . ' ORDER BY p.id DESC';

        return $this->admin('products/index', [
            'title'      => 'Products',
            'products'   => Paginator::query($sql, $params, (int) config('app.admin_per_page')),
            'categories' => db()->select('SELECT id, name FROM categories ORDER BY name'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function edit(Request $request, string $id): Response
    {
        $p = db()->first('SELECT * FROM products WHERE id = :id AND deleted_at IS NULL', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->form($p);
    }

    private function form(?array $p): Response
    {
        return $this->admin('products/form', [
            'title'      => $p ? 'Edit product' : 'Add product',
            'p'          => $p,
            'images'     => $p ? db()->select('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order, id', ['id' => $p['id']]) : [],
            'variants'   => $p ? db()->select('SELECT * FROM product_variants WHERE product_id = :id ORDER BY sort_order, id', ['id' => $p['id']]) : [],
            'categories' => db()->select('SELECT id, name, parent_id FROM categories ORDER BY parent_id IS NOT NULL, name'),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = db()->transaction(function () use ($data, $request) {
            $id = db()->insert('products', $data);
            $this->syncVariants($id, $data['sku'], (array) $request->input('variants', []));
            $this->storeImages($id, $request, $data['name']);
            return $id;
        });
        AuditService::log('product.create', 'product', $id, ['name' => $data['name']]);
        return redirect("/admin/products/$id/edit")->with('success', 'Product created.');
    }

    public function update(Request $request, string $id): Response
    {
        $id = (int) $id;
        $p = db()->first('SELECT * FROM products WHERE id = :id AND deleted_at IS NULL', ['id' => $id]) ?? throw new HttpException(404);
        $data = $this->validated($request, $id);
        db()->transaction(function () use ($id, $data, $request) {
            db()->update('products', $data, 'id = :id', ['id' => $id]);
            $this->syncVariants($id, $data['sku'], (array) $request->input('variants', []));
            $this->storeImages($id, $request, $data['name']);
            foreach ((array) $request->input('image_order', []) as $imgId => $order) {
                db()->update('product_images', ['sort_order' => (int) $order], 'id = :id AND product_id = :p', ['id' => (int) $imgId, 'p' => $id]);
            }
        });
        $changes = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', array_intersect_key($p, $data))));
        AuditService::log('product.update', 'product', $id, ['changed' => $changes]);
        return redirect("/admin/products/$id/edit")->with('success', 'Product saved.');
    }

    public function destroy(Request $request, string $id): Response
    {
        db()->update('products', ['deleted_at' => now(), 'is_active' => 0], 'id = :id', ['id' => (int) $id]);
        AuditService::log('product.delete', 'product', (int) $id);
        return redirect('/admin/products')->with('success', 'Product archived. Past orders are unaffected.');
    }

    public function deleteImage(Request $request, string $id, string $imageId): Response
    {
        $img = db()->first('SELECT * FROM product_images WHERE id = :i AND product_id = :p', ['i' => (int) $imageId, 'p' => (int) $id]);
        if ($img) {
            db()->execute('DELETE FROM product_images WHERE id = :i', ['i' => $img['id']]);
            ImageUploader::delete($img['path']);
            AuditService::log('product.image_delete', 'product', (int) $id);
        }
        return redirect("/admin/products/$id/edit")->with('success', 'Image removed.');
    }

    /** Quick stock update from the product list / edit page. */
    public function stock(Request $request, string $id): Response
    {
        foreach ((array) $request->input('stock', []) as $variantId => $qty) {
            if (is_numeric($qty) && db()->scalar('SELECT 1 FROM product_variants WHERE id = :v AND product_id = :p', ['v' => (int) $variantId, 'p' => (int) $id])) {
                InventoryService::setStock((int) $variantId, (int) $qty, admin_auth()->id());
            }
        }
        AuditService::log('product.stock', 'product', (int) $id);
        return back()->with('success', 'Stock updated.');
    }

    private function validated(Request $request, ?int $id): array
    {
        $data = $this->validate($request, [
            'name'                => 'required|max:190',
            'slug'                => 'required|slug|max:210|unique:products,slug' . ($id ? ",$id" : ''),
            'sku'                 => 'required|max:64|unique:products,sku' . ($id ? ",$id" : ''),
            'category_id'         => 'required|integer|exists:categories,id',
            'brand'               => 'nullable|max:80',
            'team'                => 'nullable|max:120',
            'league'              => 'nullable|max:120',
            'season'              => 'nullable|max:20',
            'kit_type'            => 'nullable|in:' . implode(',', array_keys(config('app.kit_types'))),
            'gender'              => 'required|in:men,women,kids,unisex',
            'short_description'   => 'nullable|max:500',
            'description'         => 'nullable|max:20000',
            'mrp'                 => 'required|numeric|min:1',
            'price'               => 'required|numeric|min:1',
            'tax_rate'            => 'required|numeric|between:0,28',
            'hsn_code'            => 'nullable|max:12',
            'allow_customization' => 'boolean',
            'customization_price' => 'nullable|numeric|min:0',
            'is_active'           => 'boolean',
            'is_featured'         => 'boolean',
            'is_new_arrival'      => 'boolean',
            'meta_title'          => 'nullable|max:190',
            'meta_description'    => 'nullable|max:255',
        ], ['category_id' => 'Category', 'mrp' => 'MRP', 'sku' => 'SKU']);

        if ((float) $data['price'] > (float) $data['mrp']) {
            throw new \App\Core\ValidationException(['price' => ['Selling price cannot be higher than MRP.']]);
        }
        $data['sku'] = strtoupper($data['sku']);
        $data['customization_price'] = $data['customization_price'] ?? 0;
        return $data;
    }

    private function syncVariants(int $productId, string $sku, array $rows): void
    {
        $sizes = config('app.sizes');
        $keep = [];
        foreach ($rows as $row) {
            $size = strtoupper(trim((string) ($row['size'] ?? '')));
            if ($size === '') {
                continue;
            }
            $stock = max(0, (int) ($row['stock'] ?? 0));
            $vsku = strtoupper(trim((string) ($row['sku'] ?? '')) ?: $sku . '-' . $size);
            $order = array_search($size, $sizes, true);
            $existing = !empty($row['id'])
                ? db()->first('SELECT * FROM product_variants WHERE id = :id AND product_id = :p', ['id' => (int) $row['id'], 'p' => $productId])
                : db()->first('SELECT * FROM product_variants WHERE product_id = :p AND size = :s', ['p' => $productId, 's' => $size]);
            try {
                if ($existing) {
                    db()->update('product_variants', ['size' => $size, 'sku' => $vsku, 'is_active' => 1, 'sort_order' => $order === false ? 99 : $order], 'id = :id', ['id' => $existing['id']]);
                    if ((int) $existing['stock'] !== $stock) {
                        InventoryService::setStock((int) $existing['id'], $stock, admin_auth()->id());
                    }
                    $keep[] = (int) $existing['id'];
                } else {
                    $vid = db()->insert('product_variants', ['product_id' => $productId, 'size' => $size, 'sku' => $vsku, 'stock' => 0, 'sort_order' => $order === false ? 99 : $order]);
                    InventoryService::adjust($vid, $stock, 'initial', 'admin', admin_auth()->id());
                    $keep[] = $vid;
                }
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw new \App\Core\ValidationException(['variants' => ["Duplicate size or variant SKU ($vsku)."]]);
                }
                throw $e;
            }
        }
        // Removed sizes are deactivated (not deleted) – order history references them.
        [$in, $p] = \App\Core\Database::in('k', $keep ?: [0]);
        db()->execute("UPDATE product_variants SET is_active = 0 WHERE product_id = :pid AND id NOT IN $in", ['pid' => $productId] + $p);
    }

    private function storeImages(int $productId, Request $request, string $alt): void
    {
        $max = (int) db()->scalar('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = :p', ['p' => $productId]);
        foreach (array_slice($request->files('images'), 0, 10) as $file) {
            try {
                $path = ImageUploader::store($file, 'products', 1400);
            } catch (RuntimeException $e) {
                throw new \App\Core\ValidationException(['images' => [$file['name'] . ': ' . $e->getMessage()]]);
            }
            db()->insert('product_images', ['product_id' => $productId, 'path' => $path, 'alt' => $alt, 'sort_order' => ++$max]);
        }
    }
}
