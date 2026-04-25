<?php
declare(strict_types=1);

/**
 * Single entry point for the JSON API.
 * URL shape: /api.php/<resource>[/<id>]
 * With Apache rewrite (.htaccess), it also responds at /api/<resource>.
 */

use Sunflower\Bootstrap;
use Sunflower\Http\Response;
use Sunflower\Http\Router;
use Sunflower\Controllers\AuthController;
use Sunflower\Controllers\CategoryController;
use Sunflower\Controllers\ClinicController;
use Sunflower\Controllers\InvoiceController;
use Sunflower\Controllers\ProductController;
use Sunflower\Controllers\StatsController;

$root = dirname(__DIR__);
require $root . '/src/Bootstrap.php';
Bootstrap::init($root);

set_exception_handler(function (\Throwable $e): void {
    error_log('[API] ' . $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (\Sunflower\Config::bool('APP_DEBUG', false)) {
        Response::json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
    }
    Response::json(['error' => 'server_error'], 500);
});

$r = new Router();

// --- Auth
$auth = new AuthController();
$r->post('/auth/login',           fn () => $auth->login());
$r->post('/auth/logout',          fn () => $auth->logout());
$r->get ('/auth/me',              fn () => $auth->me());
$r->post('/auth/change-password', fn () => $auth->changePassword());

// --- Categories
$cat = new CategoryController();
$r->get   ('/categories',     fn ()    => $cat->index());
$r->post  ('/categories',     fn ()    => $cat->create());
$r->put   ('/categories/:id', fn ($a)  => $cat->update($a));
$r->delete('/categories/:id', fn ($a)  => $cat->delete($a));

// --- Products
$prod = new ProductController();
$r->get   ('/products',     fn ()   => $prod->index());
$r->post  ('/products',     fn ()   => $prod->create());
$r->get   ('/products/:id', fn ($a) => $prod->show($a));
$r->put   ('/products/:id', fn ($a) => $prod->update($a));
$r->delete('/products/:id', fn ($a) => $prod->delete($a));

// --- Clinics
$clin = new ClinicController();
$r->get   ('/clinics',     fn ()   => $clin->index());
$r->post  ('/clinics',     fn ()   => $clin->create());
$r->get   ('/clinics/:id', fn ($a) => $clin->show($a));
$r->put   ('/clinics/:id', fn ($a) => $clin->update($a));
$r->delete('/clinics/:id', fn ($a) => $clin->delete($a));

// --- Invoices / receipts
$inv = new InvoiceController();
$r->get   ('/invoices',     fn ()   => $inv->index());
$r->post  ('/invoices',     fn ()   => $inv->create());
$r->get   ('/invoices/:id', fn ($a) => $inv->show($a));
$r->delete('/invoices/:id', fn ($a) => $inv->delete($a));

// --- Stats / settings
$stats = new StatsController();
$r->get ('/stats',    fn () => $stats->dashboard());
$r->get ('/settings', fn () => $stats->getSettings());
$r->put ('/settings', fn () => $stats->updateSettings());

$r->dispatch();
