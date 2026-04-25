<?php
declare(strict_types=1);

use Sunflower\Auth;
use Sunflower\Bootstrap;
use Sunflower\Config;
use Sunflower\Db;

$root = dirname(__DIR__);
require $root . '/src/Bootstrap.php';
Bootstrap::init($root);

if (!Auth::check()) {
    header('Location: /login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Missing or invalid id.');
}

$inv = Db::one(
    'SELECT i.*, u.display_name AS created_by_name
     FROM invoices i
     LEFT JOIN users u ON u.id = i.created_by
     WHERE i.id = :id',
    [':id' => $id]
);
if (!$inv) {
    http_response_code(404);
    exit('Document not found.');
}

$items = Db::all(
    'SELECT product_code, product_name, unit_price, qty, patient_name, line_total
     FROM invoice_items WHERE invoice_id = :id ORDER BY id',
    [':id' => $id]
);

$bizName    = Config::get('BUSINESS_NAME',    'Sunflower Dental Lab');
$bizPhone   = Config::get('BUSINESS_PHONE',   '');
$bizAddress = Config::get('BUSINESS_ADDRESS', '');

function rm(float|string $n): string
{
    return 'RM ' . number_format((float) $n, 2);
}
function fdate(?string $d): string
{
    if (!$d) return '';
    [$y, $m, $dy] = explode('-', $d);
    return "$dy/$m/$y";
}
$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$isReceipt = $inv['doc_type'] === 'receipt';
$titleWord = $isReceipt ? 'RECEIPT' : 'INVOICE';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $h($titleWord . ' ' . $inv['doc_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "DM Sans", sans-serif; color: #1c1a16; background: #f5f1e8; padding: 30px 16px; }
  .doc { max-width: 780px; margin: 0 auto; background: white; padding: 38px 44px; border: 1px solid #e0d4b8; box-shadow: 0 4px 30px rgba(0,0,0,.05); }
  h1, h2, h3 { font-family: "Playfair Display", serif; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 18px; border-bottom: 2px solid #b8902a; margin-bottom: 22px; }
  .biz-name { font-size: 1.5rem; color: #1c1a16; }
  .biz-meta { font-size: .82rem; color: #5a5040; margin-top: 4px; line-height: 1.5; }
  .doc-title { text-align: right; }
  .doc-title .word { font-family: "Playfair Display", serif; font-size: 2rem; color: #b8902a; letter-spacing: .04em; }
  .doc-title .num   { font-size: 1.1rem; font-weight: 600; margin-top: 4px; }
  .doc-title .date  { font-size: .82rem; color: #5a5040; margin-top: 2px; }

  .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 22px; }
  .meta-block .label { font-size: .7rem; text-transform: uppercase; color: #8a7a60; font-weight: 600; letter-spacing: .08em; margin-bottom: 5px; }
  .meta-block .val   { font-size: .95rem; line-height: 1.45; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: .9rem; }
  th { background: #f9f3e3; color: #5a5040; text-align: left; padding: 9px 10px; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; border-bottom: 1.5px solid #b8902a; }
  th.right, td.right { text-align: right; }
  td { padding: 9px 10px; border-bottom: 1px solid #ece1c4; vertical-align: top; }
  td.num { font-variant-numeric: tabular-nums; }
  .patient { display: block; font-size: .78rem; color: #8a7a60; font-style: italic; margin-top: 2px; }

  .totals { margin-left: auto; width: 320px; }
  .totals .row { display: flex; justify-content: space-between; padding: 5px 0; font-size: .95rem; }
  .totals .row.grand { border-top: 2px solid #1c1a16; margin-top: 8px; padding-top: 9px; font-size: 1.2rem; font-weight: 700; color: #b8902a; }

  .pay-block { margin-top: 18px; padding: 12px 16px; background: rgba(26,122,64,.08); border-left: 4px solid #1a7a40; border-radius: 4px; font-size: .9rem; }
  .footer { margin-top: 26px; padding-top: 14px; border-top: 1px dashed #e0d4b8; font-size: .76rem; color: #8a7a60; display: flex; justify-content: space-between; }

  .actions { max-width: 780px; margin: 0 auto 16px; display: flex; gap: 8px; justify-content: flex-end; }
  .btn { background: #1c1a16; color: #d4af50; border: 0; padding: 9px 18px; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; }
  .btn.secondary { background: white; color: #1c1a16; border: 1px solid #e0d4b8; }

  @media print {
    body { background: white; padding: 0; }
    .doc { box-shadow: none; border: 0; padding: 18px 24px; }
    .actions { display: none; }
  }
</style>
</head>
<body>

<div class="actions">
  <button class="btn secondary" onclick="window.close()">Close</button>
  <button class="btn" onclick="window.print()">Print</button>
</div>

<div class="doc">
  <div class="head">
    <div>
      <div class="biz-name"><?= $h($bizName) ?></div>
      <div class="biz-meta">
        <?php if ($bizPhone): ?><?= $h($bizPhone) ?><br><?php endif; ?>
        <?php if ($bizAddress): ?><?= $h($bizAddress) ?><?php endif; ?>
      </div>
    </div>
    <div class="doc-title">
      <div class="word"><?= $titleWord ?></div>
      <div class="num"><?= $h($inv['doc_no']) ?></div>
      <div class="date">Date: <?= $h(fdate($inv['doc_date'])) ?></div>
    </div>
  </div>

  <div class="meta-grid">
    <div class="meta-block">
      <div class="label">Bill to</div>
      <div class="val">
        <strong><?= $h($inv['clinic_name']) ?></strong><br>
        <?php if ($inv['clinic_address']): ?><?= $h($inv['clinic_address']) ?><br><?php endif; ?>
        <?php if ($inv['clinic_phone']): ?><?= $h($inv['clinic_phone']) ?><?php endif; ?>
      </div>
    </div>
    <div class="meta-block" style="text-align:right">
      <div class="label">Issued by</div>
      <div class="val"><?= $h($inv['created_by_name'] ?: '—') ?></div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:90px">Code</th>
        <th>Description</th>
        <th class="right" style="width:90px">Qty</th>
        <th class="right" style="width:120px">Unit</th>
        <th class="right" style="width:130px">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= $h($it['product_code']) ?></td>
          <td>
            <?= $h($it['product_name']) ?>
            <?php if (!empty($it['patient_name'])): ?>
              <span class="patient">Patient: <?= $h($it['patient_name']) ?></span>
            <?php endif; ?>
          </td>
          <td class="right num"><?= (int) $it['qty'] ?></td>
          <td class="right num"><?= rm($it['unit_price']) ?></td>
          <td class="right num"><?= rm($it['line_total']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <div class="row"><span>Subtotal</span><span class="num"><?= rm($inv['subtotal']) ?></span></div>
    <?php if ((float) $inv['discount'] > 0): ?>
      <div class="row"><span>Discount</span><span class="num">− <?= rm($inv['discount']) ?></span></div>
    <?php endif; ?>
    <div class="row grand"><span><?= $isReceipt ? 'Paid' : 'Total' ?></span><span class="num"><?= rm($inv['total']) ?></span></div>
  </div>

  <?php if ($isReceipt): ?>
    <div class="pay-block">
      <strong>Payment received</strong>
      <?php if ($inv['payment_method']): ?> via <?= $h($inv['payment_method']) ?><?php endif; ?>
      <?php if ($inv['paid_at']): ?> on <?= $h(fdate($inv['paid_at'])) ?><?php endif; ?>.
    </div>
  <?php endif; ?>

  <div class="footer">
    <div>Thank you for choosing <?= $h($bizName) ?>.</div>
    <div>Generated <?= date('Y-m-d H:i') ?></div>
  </div>
</div>

</body>
</html>
