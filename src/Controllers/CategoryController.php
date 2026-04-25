<?php
declare(strict_types=1);

namespace Sunflower\Controllers;

use Sunflower\Auth;
use Sunflower\Csrf;
use Sunflower\Db;
use Sunflower\Http\Request;
use Sunflower\Http\Response;
use Sunflower\Validation\V;

final class CategoryController
{
    public function index(): void
    {
        Auth::requireLogin();
        Response::ok([
            'categories' => Db::all(
                'SELECT id, code, label, sort_order
                 FROM categories ORDER BY sort_order, label'
            ),
        ]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        Csrf::require();

        $v = new V(Request::json());
        $code  = $v->str('code',  max: 40,  required: true);
        $label = $v->str('label', max: 80,  required: true);
        $sort  = $v->int('sort_order', min: 0, max: 9999);
        $v->stopIfErrors();

        try {
            Db::exec(
                'INSERT INTO categories (code, label, sort_order) VALUES (:c, :l, :s)',
                [':c' => $code, ':l' => $label, ':s' => $sort]
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

        $id = (int) $args['id'];
        $v  = new V(Request::json());
        $label = $v->str('label', max: 80, required: true);
        $sort  = $v->int('sort_order', min: 0, max: 9999);
        $v->stopIfErrors();

        $n = Db::exec(
            'UPDATE categories SET label = :l, sort_order = :s WHERE id = :id',
            [':l' => $label, ':s' => $sort, ':id' => $id]
        );
        if ($n === 0 && !Db::one('SELECT id FROM categories WHERE id = :id', [':id' => $id])) {
            Response::notFound();
        }
        Response::ok();
    }

    public function delete(array $args): void
    {
        Auth::requireLogin();
        Csrf::require();

        $id = (int) $args['id'];
        $inUse = Db::one('SELECT id FROM products WHERE category_id = :id LIMIT 1', [':id' => $id]);
        if ($inUse) {
            Response::badRequest('category_in_use');
        }
        Db::exec('DELETE FROM categories WHERE id = :id', [':id' => $id]);
        Response::ok();
    }
}
