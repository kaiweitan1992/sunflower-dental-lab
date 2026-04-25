<?php
declare(strict_types=1);

namespace Sunflower\Controllers;

use Sunflower\Auth;
use Sunflower\Csrf;
use Sunflower\Db;
use Sunflower\Http\Request;
use Sunflower\Http\Response;
use Sunflower\Validation\V;

final class ClinicController
{
    public function index(): void
    {
        Auth::requireLogin();
        $q = trim((string) Request::query('q', ''));

        $sql = 'SELECT id, name, contact_person, phone, email, address, notes
                FROM clinics';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE name LIKE :q OR phone LIKE :q OR email LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY name';
        Response::ok(['clinics' => Db::all($sql, $params)]);
    }

    public function show(array $args): void
    {
        Auth::requireLogin();
        $row = Db::one('SELECT * FROM clinics WHERE id = :id', [':id' => $args['id']]);
        if (!$row) Response::notFound();
        Response::ok(['clinic' => $row]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        Csrf::require();
        $row = $this->validate(Request::json());

        Db::exec(
            'INSERT INTO clinics (name, contact_person, phone, email, address, notes)
             VALUES (:name, :cp, :ph, :em, :ad, :no)',
            $this->bind($row)
        );
        Response::created(['id' => Db::lastId()]);
    }

    public function update(array $args): void
    {
        Auth::requireLogin();
        Csrf::require();
        $id  = (int) $args['id'];
        $row = $this->validate(Request::json());

        $n = Db::exec(
            'UPDATE clinics
             SET name = :name, contact_person = :cp, phone = :ph,
                 email = :em, address = :ad, notes = :no
             WHERE id = :id',
            $this->bind($row) + [':id' => $id]
        );
        if ($n === 0 && !Db::one('SELECT id FROM clinics WHERE id = :id', [':id' => $id])) {
            Response::notFound();
        }
        Response::ok();
    }

    public function delete(array $args): void
    {
        Auth::requireLogin();
        Csrf::require();
        $id = (int) $args['id'];
        // Invoices have ON DELETE SET NULL on clinic_id, so historical docs survive.
        Db::exec('DELETE FROM clinics WHERE id = :id', [':id' => $id]);
        Response::ok();
    }

    private function validate(array $input): array
    {
        $v = new V($input);
        $name           = $v->str('name', max: 180, required: true);
        $contact_person = $v->str('contact_person', max: 120);
        $phone          = $v->str('phone', max: 40);
        $email          = $v->str('email', max: 180);
        $address        = $v->str('address', max: 255);
        $notes          = $v->str('notes', max: 5000);
        $v->stopIfErrors();
        return compact('name', 'contact_person', 'phone', 'email', 'address', 'notes');
    }

    private function bind(array $row): array
    {
        return [
            ':name' => $row['name'],
            ':cp'   => $row['contact_person'],
            ':ph'   => $row['phone'],
            ':em'   => $row['email'],
            ':ad'   => $row['address'],
            ':no'   => $row['notes'],
        ];
    }
}
