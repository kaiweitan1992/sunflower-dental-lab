<?php
declare(strict_types=1);

namespace Sunflower\Controllers;

use Sunflower\Auth;
use Sunflower\Csrf;
use Sunflower\Db;
use Sunflower\Http\Request;
use Sunflower\Http\Response;
use Sunflower\Validation\V;

final class ProductController
{
    public function index(): void
    {
        Auth::requireLogin();

        $q   = trim((string) Request::query('q', ''));
        $cat = (int)  Request::query('category_id', '0');
        $all = Request::query('all') === '1';

        $where  = [];
        $params = [];
        if (!$all) {
            $where[] = 'p.is_active = 1';
        }
        if ($q !== '') {
            $where[] = '(p.name LIKE :q OR p.code LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($cat > 0) {
            $where[] = 'p.category_id = :cat';
            $params[':cat'] = $cat;
        }
        $sql = 'SELECT p.id, p.code, p.name, p.category_id, c.code AS category_code,
                       c.label AS category_label, p.price, p.note, p.is_active
                FROM products p
                JOIN categories c ON c.id = p.category_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY c.sort_order, p.name';

        Response::ok([
            'products' => array_map(
                fn ($r) => $r + ['price' => (float) $r['price']],
                Db::all($sql, $params)
            ),
        ]);
    }

    public function show(array $args): void
    {
        Auth::requireLogin();
        $row = Db::one(
            'SELECT id, code, name, category_id, price, note, is_active
             FROM products WHERE id = :id',
            [':id' => $args['id']]
        );
        if (!$row) {
            Response::notFound();
        }
        $row['price'] = (float) $row['price'];
        Response::ok(['product' => $row]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        Csrf::require();

        $row = $this->validate(Request::json());

        try {
            Db::exec(
                'INSERT INTO products (code, name, category_id, price, note, is_active)
                 VALUES (:code, :name, :cat, :price, :note, :active)',
                [
                    ':code'   => $row['code'],
                    ':name'   => $row['name'],
                    ':cat'    => $row['category_id'],
                    ':price'  => $row['price'],
                    ':note'   => $row['note'],
                    ':active' => $row['is_active'],
                ]
            );
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Response::badRequest('duplicate_code');
            }
            throw $e;
        }
        Response::created(['id' => Db::lastId()]);
    }

    public function update(array $args): void
    {
        Auth::requireLogin();
        Csrf::require();

        $id  = (int) $args['id'];
        $row = $this->validate(Request::json(), updating: true);

        $n = Db::exec(
            'UPDATE products
             SET code = :code, name = :name, category_id = :cat,
                 price = :price, note = :note, is_active = :active
             WHERE id = :id',
            [
                ':code'   => $row['code'],
                ':name'   => $row['name'],
                ':cat'    => $row['category_id'],
                ':price'  => $row['price'],
                ':note'   => $row['note'],
                ':active' => $row['is_active'],
                ':id'     => $id,
            ]
        );
        if ($n === 0 && !Db::one('SELECT id FROM products WHERE id = :id', [':id' => $id])) {
            Response::notFound();
        }
        Response::ok();
    }

    public function delete(array $args): void
    {
        Auth::requireLogin();
        Csrf::require();
        $id = (int) $args['id'];

        // Soft-delete by deactivating, since invoice items reference products.
        Db::exec('UPDATE products SET is_active = 0 WHERE id = :id', [':id' => $id]);
        Response::ok();
    }

    /** @return array{code:string,name:string,category_id:int,price:float,note:string,is_active:int} */
    private function validate(array $input, bool $updating = false): array
    {
        $v = new V($input);
        $code  = $v->str('code',  max: 40,  required: true);
        $name  = $v->str('name',  max: 180, required: true);
        $cat   = $v->int('category_id', min: 1, required: true);
        $price = $v->money('price', min: 0);
        $note  = $v->str('note', max: 180);
        $active = $v->int('is_active', min: 0, max: 1, default: 1);
        $v->stopIfErrors();

        if (!Db::one('SELECT id FROM categories WHERE id = :id', [':id' => $cat])) {
            Response::badRequest('validation_failed', ['category_id' => 'unknown']);
        }
        return compact('code', 'name', 'price', 'note') + [
            'category_id' => $cat,
            'is_active'   => $active,
        ];
    }
}
