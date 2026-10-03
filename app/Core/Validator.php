<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Rule based validator with localised messages.
 *
 *   $v = Validator::make($request->all(), ['title' => 'required|string|max:300']);
 *   if ($v->fails()) { ... $v->errors() ... }
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $data;

    /** @var array<string,string> */
    private array $rules;

    /** @var array<string,string[]> field → messages */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    private function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->run();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', (string) $ruleString);
            $value = $this->data[$field] ?? null;
            $isNullable = in_array('nullable', $rules, true);
            $present = array_key_exists($field, $this->data) && $value !== null && $value !== '';

            if (!$present) {
                if (in_array('required', $rules, true)) {
                    $this->addError($field, __('validation.required', ['field' => $this->label($field)]));
                }
                if ($isNullable) {
                    $this->validated[$field] = null;
                }
                continue;
            }

            $skipValueRules = false;
            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'nullable') {
                    continue;
                }
                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);
                $ok = $this->applyRule($name, $argument, $field, $value);
                if (!$ok) {
                    $skipValueRules = true;
                    break;
                }
            }
            if (!$skipValueRules) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function applyRule(string $name, ?string $argument, string $field, mixed $value): bool
    {
        $label = $this->label($field);

        switch ($name) {
            case 'required':
                return true; // handled above

            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, __('validation.string', ['field' => $label]));
                    return false;
                }
                return true;

            case 'int':
            case 'integer':
                if (!is_numeric($value) || (string) (int) $value !== (string) $value) {
                    $this->addError($field, __('validation.integer', ['field' => $label]));
                    return false;
                }
                $this->validated[$field] = (int) $value;
                return true;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, __('validation.numeric', ['field' => $label]));
                    return false;
                }
                return true;

            case 'bool':
            case 'boolean':
                if (!in_array($value, ['0', '1', 0, 1, true, false, 'true', 'false', 'on'], true)) {
                    $this->addError($field, __('validation.boolean', ['field' => $label]));
                    return false;
                }
                return true;

            case 'email':
                if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, __('validation.email', ['field' => $label]));
                    return false;
                }
                return true;

            case 'url':
                if (!filter_var((string) $value, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', (string) $value)) {
                    $this->addError($field, __('validation.url', ['field' => $label]));
                    return false;
                }
                return true;

            case 'min':
                $min = (float) $argument;
                $length = is_string($value) ? mb_strlen(trim($value)) : (float) $value;
                if ($length < $min) {
                    $this->addError($field, __('validation.min', ['field' => $label, 'min' => (string) (int) $min]));
                    return false;
                }
                return true;

            case 'max':
                $max = (float) $argument;
                $length = is_string($value) ? mb_strlen(trim($value)) : (float) $value;
                if ($length > $max) {
                    $this->addError($field, __('validation.max', ['field' => $label, 'max' => (string) (int) $max]));
                    return false;
                }
                return true;

            case 'in':
                $allowed = explode(',', (string) $argument);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->addError($field, __('validation.in', ['field' => $label]));
                    return false;
                }
                return true;

            case 'regex':
                if (!preg_match((string) $argument, (string) $value)) {
                    $this->addError($field, __('validation.regex', ['field' => $label]));
                    return false;
                }
                return true;

            case 'date':
                if (strtotime((string) $value) === false) {
                    $this->addError($field, __('validation.date', ['field' => $label]));
                    return false;
                }
                return true;

            case 'same':
                if (($this->data[$argument] ?? null) !== $value) {
                    $this->addError($field, __('validation.same', ['field' => $label, 'other' => $this->label((string) $argument)]));
                    return false;
                }
                return true;

            case 'accepted':
                if (!in_array($value, ['1', 1, true, 'on', 'yes'], true)) {
                    $this->addError($field, __('validation.accepted', ['field' => $label]));
                    return false;
                }
                return true;

            case 'unique':
                // unique:table,column[,exceptId]
                $parts = explode(',', (string) $argument);
                $table = $parts[0] ?? '';
                $column = $parts[1] ?? $field;
                $exceptId = $parts[2] ?? null;
                if ($table === '') {
                    return true;
                }
                $sql = 'SELECT COUNT(*) FROM {{' . $table . '}} WHERE ' . $column . ' = :value';
                $params = ['value' => $value];
                if ($exceptId !== null && $exceptId !== '') {
                    $sql .= ' AND id <> :except';
                    $params['except'] = (int) $exceptId;
                }
                if ((int) Database::instance()->scalar($sql, $params) > 0) {
                    $this->addError($field, __('validation.unique', ['field' => $label]));
                    return false;
                }
                return true;

            case 'alpha_dash':
                if (!preg_match('/^[\p{L}\p{N}_.\-]+$/u', (string) $value)) {
                    $this->addError($field, __('validation.alpha_dash', ['field' => $label]));
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    private function label(string $field): string
    {
        $key = 'field.' . $field;
        $translated = __($key);
        return $translated === $key ? ucfirst(str_replace('_', ' ', $field)) : $translated;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? '';
        }
        return '';
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }
}
