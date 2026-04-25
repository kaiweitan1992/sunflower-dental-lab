<?php
declare(strict_types=1);

namespace Sunflower\Validation;

use Sunflower\Http\Response;

/**
 * Tiny validator. Each method either returns a coerced value
 * or pushes an error onto the bag. Call ::stopIfErrors() at the
 * end of validation to send a 400 with the collected details.
 *
 * Example:
 *   $v = new V($input);
 *   $name  = $v->str('name', max: 180, required: true);
 *   $price = $v->money('price', min: 0);
 *   $v->stopIfErrors();
 */
final class V
{
    private array $errors = [];

    public function __construct(private array $data) {}

    public function str(string $key, int $max = 255, bool $required = false, string $default = ''): string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'required';
            }
            return $default;
        }
        if (!is_string($v)) {
            $this->errors[$key] = 'must_be_string';
            return $default;
        }
        $v = trim($v);
        if (mb_strlen($v) > $max) {
            $this->errors[$key] = "max_$max";
            return mb_substr($v, 0, $max);
        }
        return $v;
    }

    public function int(string $key, ?int $min = null, ?int $max = null, bool $required = false, int $default = 0): int
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'required';
            }
            return $default;
        }
        if (!is_numeric($v)) {
            $this->errors[$key] = 'must_be_int';
            return $default;
        }
        $i = (int) $v;
        if ($min !== null && $i < $min) {
            $this->errors[$key] = "min_$min";
        }
        if ($max !== null && $i > $max) {
            $this->errors[$key] = "max_$max";
        }
        return $i;
    }

    public function money(string $key, float $min = 0, bool $required = false): float
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'required';
            }
            return 0.0;
        }
        if (!is_numeric($v)) {
            $this->errors[$key] = 'must_be_number';
            return 0.0;
        }
        $f = round((float) $v, 2);
        if ($f < $min) {
            $this->errors[$key] = "min_$min";
        }
        return $f;
    }

    public function enum(string $key, array $allowed, bool $required = false, ?string $default = null): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'required';
            }
            return $default;
        }
        if (!in_array($v, $allowed, true)) {
            $this->errors[$key] = 'not_allowed';
            return $default;
        }
        return (string) $v;
    }

    public function date(string $key, bool $required = false, ?string $default = null): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'required';
            }
            return $default;
        }
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            $this->errors[$key] = 'must_be_date';
            return $default;
        }
        $parts = explode('-', $v);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            $this->errors[$key] = 'invalid_date';
            return $default;
        }
        return $v;
    }

    /** Validate an array of items via a callback that returns a coerced row. */
    public function array(string $key, callable $each, int $minItems = 1): array
    {
        $arr = $this->data[$key] ?? null;
        if (!is_array($arr) || count($arr) < $minItems) {
            $this->errors[$key] = "min_items_$minItems";
            return [];
        }
        $out = [];
        foreach ($arr as $i => $row) {
            if (!is_array($row)) {
                $this->errors["{$key}.{$i}"] = 'must_be_object';
                continue;
            }
            try {
                $out[] = $each($row, $i);
            } catch (\Throwable $e) {
                $this->errors["{$key}.{$i}"] = $e->getMessage();
            }
        }
        return $out;
    }

    public function errors(): array { return $this->errors; }

    public function ok(): bool { return $this->errors === []; }

    public function stopIfErrors(): void
    {
        if ($this->errors !== []) {
            Response::badRequest('validation_failed', $this->errors);
        }
    }
}
