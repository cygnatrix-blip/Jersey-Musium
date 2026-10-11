<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validator::make($data, ['email' => 'required|email|max:120', 'qty' => 'required|integer|min:1']);
 * Rules: required nullable string email numeric integer boolean min:n max:n between:a,b in:a,b,c
 *        confirmed phone pincode date url slug unique:table,col[,ignoreId] exists:table,col
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];

    private function __construct(private array $data, private array $rules, private array $labels)
    {
        $this->run();
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $isEmpty = $value === null || $value === '' || $value === [];
            $numeric = in_array('numeric', $rules, true) || in_array('integer', $rules, true);

            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->add($field, ':label is required.');
                } else {
                    $this->validated[$field] = in_array('boolean', $rules, true) ? 0 : null;
                }
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $ok = match ($name) {
                    'required', 'nullable', 'string' => true,
                    'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) !== false && strlen($value) <= 190,
                    'numeric'  => is_numeric($value),
                    'integer'  => filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'boolean'  => in_array($value, [0, 1, '0', '1', 'on', true, false], true),
                    'min'      => $numeric ? (float) $value >= (float) $arg : mb_strlen((string) $value) >= (int) $arg,
                    'max'      => $numeric ? (float) $value <= (float) $arg : mb_strlen((string) $value) <= (int) $arg,
                    'between'  => (fn ($a) => (float) $value >= (float) $a[0] && (float) $value <= (float) $a[1])(explode(',', $arg)),
                    'in'       => in_array((string) $value, explode(',', $arg), true),
                    'confirmed' => $value === ($this->data[$field . '_confirmation'] ?? null),
                    'phone'    => (bool) preg_match('/^[6-9]\d{9}$/', (string) $value),
                    'pincode'  => (bool) preg_match('/^[1-9]\d{5}$/', (string) $value),
                    'date'     => strtotime((string) $value) !== false,
                    // only http(s) absolute URLs or site-relative paths ("/x", not protocol-relative "//x") – blocks javascript:/data: links
                    'url'      => (filter_var($value, FILTER_VALIDATE_URL) !== false && (bool) preg_match('#^https?://#i', (string) $value)) || (bool) preg_match('#^/(?!/)#', (string) $value),
                    'slug'     => (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value),
                    'unique'   => $this->unique($value, $arg),
                    'exists'   => $this->exists($value, $arg),
                    default    => throw new \LogicException("Unknown validation rule [$name]"),
                };
                if (!$ok) {
                    $this->add($field, $this->message($name, $arg, $numeric));
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = in_array('boolean', $rules, true) ? (int) in_array($value, [1, '1', 'on', true], true) : $value;
            }
        }
    }

    private function unique(mixed $value, string $arg): bool
    {
        [$table, $col, $ignore] = array_pad(explode(',', $arg), 3, null);
        $sql = "SELECT COUNT(*) FROM `$table` WHERE `$col` = :v" . ($ignore ? ' AND id <> :id' : '');
        $params = ['v' => $value] + ($ignore ? ['id' => (int) $ignore] : []);
        return (int) db()->scalar($sql, $params) === 0;
    }

    private function exists(mixed $value, string $arg): bool
    {
        [$table, $col] = explode(',', $arg);
        return (int) db()->scalar("SELECT COUNT(*) FROM `$table` WHERE `$col` = :v", ['v' => $value]) > 0;
    }

    private function message(string $rule, ?string $arg, bool $numeric = false): string
    {
        return match ($rule) {
            'email'     => 'Enter a valid email address.',
            'numeric', 'integer' => ':label must be a number.',
            'min'       => ':label must be at least ' . $arg . ($numeric ? '.' : ' characters.'),
            'max'       => $numeric ? ':label may not be greater than ' . $arg . '.' : ':label may not be longer than ' . $arg . ' characters.',
            'between'   => ':label must be between ' . str_replace(',', ' and ', (string) $arg) . '.',
            'in'        => 'Select a valid :label.',
            'confirmed' => ':label confirmation does not match.',
            'phone'     => 'Enter a valid 10-digit mobile number.',
            'pincode'   => 'Enter a valid 6-digit pincode.',
            'unique'    => 'This :label is already in use.',
            'exists'    => 'Selected :label is invalid.',
            'slug'      => ':label may contain only lowercase letters, numbers and hyphens.',
            default     => ':label is invalid.',
        };
    }

    private function add(string $field, string $message): void
    {
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $this->errors[$field][] = str_replace(':label', $label, $message);
    }
}
