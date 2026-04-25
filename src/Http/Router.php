<?php
declare(strict_types=1);

namespace Sunflower\Http;

/**
 * Minimal HTTP router. Patterns may contain placeholders like
 * "/products/:id" which are matched as positive integers and
 * passed to the handler as named arguments.
 *
 * Usage:
 *   $r = new Router();
 *   $r->get ('/products',     [ProductController::class, 'index']);
 *   $r->post('/products',     [ProductController::class, 'create']);
 *   $r->get ('/products/:id', [ProductController::class, 'show']);
 *   $r->dispatch();
 */
final class Router
{
    /** @var array<int, array{method:string,pattern:string,handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function get(string $p, callable $h): void    { $this->add('GET',    $p, $h); }
    public function post(string $p, callable $h): void   { $this->add('POST',   $p, $h); }
    public function put(string $p, callable $h): void    { $this->add('PUT',    $p, $h); }
    public function patch(string $p, callable $h): void  { $this->add('PATCH',  $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(): never
    {
        $method = Request::method();
        $path   = Request::path();

        $allowed = [];
        foreach ($this->routes as $r) {
            $regex = $this->compile($r['pattern']);
            if (preg_match($regex, $path, $m)) {
                if ($r['method'] !== $method) {
                    $allowed[] = $r['method'];
                    continue;
                }
                $args = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                $args = array_map(fn ($v) => ctype_digit($v) ? (int) $v : $v, $args);
                ($r['handler'])($args);
                Response::json(['error' => 'handler_no_response'], 500);
            }
        }

        if ($allowed) {
            Response::methodNotAllowed(implode(', ', array_unique($allowed)));
        }
        Response::notFound('route_not_found');
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '#:([a-zA-Z_][a-zA-Z0-9_]*)#',
            fn ($m) => '(?P<' . $m[1] . '>[0-9]+)',
            $pattern
        );
        return '#^' . $regex . '$#';
    }
}
