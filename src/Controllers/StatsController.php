<?php
declare(strict_types=1);

namespace Sunflower\Controllers;

use Sunflower\Auth;
use Sunflower\Csrf;
use Sunflower\Db;
use Sunflower\Http\Request;
use Sunflower\Http\Response;
use Sunflower\Validation\V;

final class StatsController
{
    public function dashboard(): void
    {
        Auth::requireLogin();

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $row = Db::one(
            'SELECT
               (SELECT COUNT(*) FROM products WHERE is_active=1) AS active_products,
               (SELECT COUNT(*) FROM clinics)                    AS clinics,
               (SELECT COUNT(*) FROM invoices WHERE doc_date = :today)        AS docs_today,
               (SELECT COALESCE(SUM(total),0) FROM invoices WHERE doc_date >= :ms) AS revenue_mtd,
               (SELECT COALESCE(SUM(total),0) FROM invoices)     AS revenue_total',
            [':today' => $today, ':ms' => $monthStart]
        );

        Response::ok([
            'active_products' => (int) $row['active_products'],
            'clinics'         => (int) $row['clinics'],
            'docs_today'      => (int) $row['docs_today'],
            'revenue_mtd'     => (float) $row['revenue_mtd'],
            'revenue_total'   => (float) $row['revenue_total'],
        ]);
    }

    public function getSettings(): void
    {
        Auth::requireLogin();
        $rows = Db::all('SELECT k, v FROM settings');
        $out = [];
        foreach ($rows as $r) $out[$r['k']] = $r['v'];
        Response::ok(['settings' => $out]);
    }

    public function updateSettings(): void
    {
        Auth::requireLogin();
        Csrf::require();
        if (Auth::user()['role'] !== 'admin') {
            Response::json(['error' => 'forbidden'], 403);
        }

        $v = new V(Request::json());
        $prefix = $v->str('invoice_prefix', max: 8);
        $next   = $v->int('invoice_next', min: 1, max: 999999);
        $v->stopIfErrors();

        if ($prefix !== '') {
            Db::exec(
                "INSERT INTO settings (k,v) VALUES ('invoice_prefix', :v)
                 ON DUPLICATE KEY UPDATE v = :v",
                [':v' => $prefix]
            );
        }
        if ($next > 0) {
            Db::exec(
                "INSERT INTO settings (k,v) VALUES ('invoice_next', :v)
                 ON DUPLICATE KEY UPDATE v = :v",
                [':v' => (string) $next]
            );
        }
        Response::ok();
    }
}
