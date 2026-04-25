<?php
declare(strict_types=1);

namespace Sunflower\Controllers;

use Sunflower\Auth;
use Sunflower\Csrf;
use Sunflower\Db;
use Sunflower\Http\Request;
use Sunflower\Http\Response;
use Sunflower\Validation\V;

final class InvoiceController
{
    /** GET /invoices?from=YYYY-MM-DD&to=...&type=invoice|receipt&clinic_id=&q= */
    public function index(): void
    {
        Auth::requireLogin();

        $from = Request::query('from');
        $to   = Request::query('to');
        $type = Request::query('type');
        $cid  = (int) Request::query('clinic_id', '0');
        $q    = trim((string) Request::query('q', ''));

        $where  = [];
        $params = [];

        if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'i.doc_date >= :from';
            $params[':from'] = $from;
        }
        if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'i.doc_date <= :to';
            $params[':to'] = $to;
        }
        if ($type && in_array($type, ['invoice', 'receipt'], true)) {
            $where[] = 'i.doc_type = :type';
            $params[':type'] = $type;
        }
        if ($cid > 0) {
            $where[] = 'i.clinic_id = :cid';
            $params[':cid'] = $cid;
        }
        if ($q !== '') {
            $where[] = '(i.doc_no LIKE :q OR i.clinic_name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = 'SELECT i.id, i.doc_no, i.doc_type, i.doc_date,
                       i.clinic_id, i.clinic_name, i.subtotal, i.discount, i.total,
                       i.payment_method, i.paid_at,
                       u.display_name AS created_by_name
                FROM invoices i
                LEFT JOIN users u ON u.id = i.created_by'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY i.doc_date DESC, i.id DESC LIMIT 500';

        $rows = array_map(fn ($r) => $r + [
            'subtotal' => (float) $r['subtotal'],
            'discount' => (float) $r['discount'],
            'total'    => (float) $r['total'],
        ], Db::all($sql, $params));

        // Aggregate totals (small dataset; safe in PHP)
        $sum = array_reduce($rows, fn ($a, $r) => [
            'count'    => $a['count'] + 1,
            'subtotal' => $a['subtotal'] + $r['subtotal'],
            'discount' => $a['discount'] + $r['discount'],
            'total'    => $a['total']    + $r['total'],
        ], ['count' => 0, 'subtotal' => 0.0, 'discount' => 0.0, 'total' => 0.0]);

        Response::ok(['invoices' => $rows, 'summary' => $sum]);
    }

    public function show(array $args): void
    {
        Auth::requireLogin();
        $id = (int) $args['id'];

        $inv = Db::one(
            'SELECT i.*, u.display_name AS created_by_name
             FROM invoices i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.id = :id',
            [':id' => $id]
        );
        if (!$inv) Response::notFound();

        $items = Db::all(
            'SELECT id, product_id, product_code, product_name,
                    unit_price, qty, patient_name, line_total
             FROM invoice_items WHERE invoice_id = :id ORDER BY id',
            [':id' => $id]
        );

        // Cast decimals to floats so JS handles them naturally.
        foreach (['subtotal', 'discount', 'total'] as $f) {
            $inv[$f] = (float) $inv[$f];
        }
        foreach ($items as &$it) {
            $it['unit_price'] = (float) $it['unit_price'];
            $it['line_total'] = (float) $it['line_total'];
            $it['qty']        = (int)   $it['qty'];
        }
        unset($it);

        Response::ok(['invoice' => $inv, 'items' => $items]);
    }

    /**
     * POST /invoices
     * Body:
     *  {
     *    "doc_type":"invoice"|"receipt",
     *    "clinic_id":12,            // optional; if missing, snapshot from fields below
     *    "clinic_name":"...", ...   // optional snapshot fields
     *    "doc_date":"2026-04-22",
     *    "discount":0,
     *    "payment_method":"...",    // receipt only
     *    "paid_at":"2026-04-22",    // receipt only
     *    "notes":"...",
     *    "items":[ {product_id, qty, patient_name}, ... ]
     *  }
     */
    public function create(): void
    {
        Auth::requireLogin();
        Csrf::require();

        $v = new V(Request::json());
        $type     = $v->enum('doc_type', ['invoice', 'receipt'], required: true);
        $clinicId = $v->int('clinic_id', min: 0);
        $docDate  = $v->date('doc_date',   required: true);
        $discount = $v->money('discount',  min: 0);
        $payMethod = $v->str('payment_method', max: 40);
        $paidAt   = $v->date('paid_at');
        $notes    = $v->str('notes', max: 5000);
        // Snapshot fallbacks (used when clinic_id is missing or for ad-hoc entries)
        $cName    = $v->str('clinic_name',    max: 180);
        $cAddr    = $v->str('clinic_address', max: 255);
        $cPhone   = $v->str('clinic_phone',   max: 40);

        $items = $v->array('items', function ($row, $i) {
            $iv = new V($row);
            $pid     = $iv->int('product_id', min: 1, required: true);
            $qty     = $iv->int('qty', min: 1, max: 9999, required: true);
            $patient = $iv->str('patient_name', max: 120);
            if (!$iv->ok()) {
                throw new \RuntimeException(json_encode($iv->errors()));
            }
            return ['product_id' => $pid, 'qty' => $qty, 'patient_name' => $patient];
        }, minItems: 1);

        $v->stopIfErrors();

        $userId = Auth::user()['id'];

        // Resolve clinic snapshot from DB if clinic_id supplied
        if ($clinicId > 0) {
            $c = Db::one('SELECT name, address, phone FROM clinics WHERE id = :id', [':id' => $clinicId]);
            if (!$c) Response::badRequest('validation_failed', ['clinic_id' => 'unknown']);
            $cName  = $c['name'];
            $cAddr  = $c['address'];
            $cPhone = $c['phone'];
        }

        // Resolve products + price snapshot, compute totals
        $ids = array_column($items, 'product_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Db::all(
            "SELECT id, code, name, price FROM products WHERE id IN ($placeholders)",
            $ids
        );
        $byId = [];
        foreach ($rows as $r) $byId[(int) $r['id']] = $r;

        $subtotal = 0.0;
        foreach ($items as &$it) {
            $p = $byId[$it['product_id']] ?? null;
            if (!$p) Response::badRequest('validation_failed', ['items' => "unknown_product_{$it['product_id']}"]);
            $it['unit_price']   = (float) $p['price'];
            $it['line_total']   = round($it['unit_price'] * $it['qty'], 2);
            $it['product_code'] = $p['code'];
            $it['product_name'] = $p['name'];
            $subtotal += $it['line_total'];
        }
        unset($it);

        $subtotal = round($subtotal, 2);
        $total    = max(0.0, round($subtotal - $discount, 2));

        // All DB writes inside one transaction
        $newId = Db::tx(function () use (
            $type, $clinicId, $cName, $cAddr, $cPhone, $docDate, $subtotal,
            $discount, $total, $payMethod, $paidAt, $notes, $userId, $items
        ) {
            $docNo = $this->nextDocNo();

            Db::exec(
                'INSERT INTO invoices
                  (doc_no, doc_type, clinic_id, clinic_name, clinic_address, clinic_phone,
                   doc_date, subtotal, discount, total, payment_method, paid_at,
                   notes, created_by)
                 VALUES
                  (:no, :type, :cid, :cname, :caddr, :cphone,
                   :date, :sub, :disc, :tot, :pm, :paid,
                   :notes, :uid)',
                [
                    ':no'    => $docNo,
                    ':type'  => $type,
                    ':cid'   => $clinicId > 0 ? $clinicId : null,
                    ':cname' => $cName,
                    ':caddr' => $cAddr,
                    ':cphone'=> $cPhone,
                    ':date'  => $docDate,
                    ':sub'   => $subtotal,
                    ':disc'  => $discount,
                    ':tot'   => $total,
                    ':pm'    => $payMethod,
                    ':paid'  => $paidAt,
                    ':notes' => $notes,
                    ':uid'   => $userId,
                ]
            );
            $invId = Db::lastId();

            foreach ($items as $it) {
                Db::exec(
                    'INSERT INTO invoice_items
                      (invoice_id, product_id, product_code, product_name,
                       unit_price, qty, patient_name, line_total)
                     VALUES (:i, :p, :pc, :pn, :price, :qty, :pat, :lt)',
                    [
                        ':i'    => $invId,
                        ':p'    => $it['product_id'],
                        ':pc'   => $it['product_code'],
                        ':pn'   => $it['product_name'],
                        ':price'=> $it['unit_price'],
                        ':qty'  => $it['qty'],
                        ':pat'  => $it['patient_name'],
                        ':lt'   => $it['line_total'],
                    ]
                );
            }
            return $invId;
        });

        Response::created(['id' => $newId]);
    }

    public function delete(array $args): void
    {
        Auth::requireLogin();
        Csrf::require();
        if (Auth::user()['role'] !== 'admin') {
            Response::json(['error' => 'forbidden'], 403);
        }
        $id = (int) $args['id'];
        Db::exec('DELETE FROM invoices WHERE id = :id', [':id' => $id]);
        Response::ok();
    }

    /**
     * Atomically increment & format the next doc number.
     * Uses SELECT ... FOR UPDATE inside the calling transaction.
     */
    private function nextDocNo(): string
    {
        $prefix = Db::one("SELECT v FROM settings WHERE k = 'invoice_prefix' FOR UPDATE")['v'] ?? 'SF';
        $next   = (int) (Db::one("SELECT v FROM settings WHERE k = 'invoice_next' FOR UPDATE")['v'] ?? 1);

        Db::exec("UPDATE settings SET v = :v WHERE k = 'invoice_next'", [':v' => (string) ($next + 1)]);

        return sprintf('%s-%06d', $prefix, $next);
    }
}
