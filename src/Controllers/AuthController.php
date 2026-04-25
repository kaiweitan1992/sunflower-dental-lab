<?php
declare(strict_types=1);

namespace Sunflower\Controllers;

use Sunflower\Auth;
use Sunflower\Csrf;
use Sunflower\Db;
use Sunflower\Http\Request;
use Sunflower\Http\Response;
use Sunflower\Validation\V;

final class AuthController
{
    public function login(): void
    {
        // Login endpoint *issues* CSRF for the next request, so it
        // doesn't require one on the way in. Use a tiny throttle to
        // make brute-force expensive.
        $v = new V(Request::json());
        $username = $v->str('username', max: 60, required: true);
        $password = $v->str('password', max: 200, required: true);
        $v->stopIfErrors();

        if (!Auth::attempt($username, $password)) {
            usleep(random_int(150_000, 400_000));
            Response::json(['error' => 'invalid_credentials'], 401);
        }

        Response::ok([
            'user'  => Auth::user(),
            'csrf'  => Csrf::token(),
        ]);
    }

    public function logout(): void
    {
        Csrf::require();
        Auth::logout();
        Response::ok();
    }

    public function me(): void
    {
        if (!Auth::check()) {
            Response::json(['error' => 'unauthorized'], 401);
        }
        Response::ok([
            'user' => Auth::user(),
            'csrf' => Csrf::token(),
        ]);
    }

    public function changePassword(): void
    {
        Auth::requireLogin();
        Csrf::require();

        $v = new V(Request::json());
        $current = $v->str('current_password', max: 200, required: true);
        $next    = $v->str('new_password',     max: 200, required: true);
        $v->stopIfErrors();

        if (mb_strlen($next) < 8) {
            Response::badRequest('weak_password', ['new_password' => 'min_8_chars']);
        }

        $u = Auth::user();
        $row = Db::one('SELECT password_hash FROM users WHERE id = :id', [':id' => $u['id']]);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            Response::json(['error' => 'invalid_current_password'], 403);
        }

        Db::exec(
            'UPDATE users SET password_hash = :h WHERE id = :id',
            [':h' => password_hash($next, PASSWORD_DEFAULT), ':id' => $u['id']]
        );
        Response::ok();
    }
}
