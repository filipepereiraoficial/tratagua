<?php
namespace App\Core;

/**
 * Validação de formulários por regras encadeadas.
 * Ex.: ['full_name' => 'required|max:150', 'email' => 'nullable|email']
 */
class Validator
{
    private array $data;
    private array $errors = [];
    private array $labels;

    public function __construct(array $data, array $labels = [])
    {
        $this->data   = $data;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $labels);
        $validator->validate($rules);
        return $validator;
    }

    public function validate(array $rules): void
    {
        foreach ($rules as $field => $ruleString) {
            $value    = $this->data[$field] ?? null;
            $value    = is_string($value) ? trim($value) : $value;
            $ruleList = explode('|', $ruleString);
            $nullable = in_array('nullable', $ruleList, true);

            if ($nullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($ruleList as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $name, $param);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param): void
    {
        $label = $this->labels[$field] ?? $field;

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                    $this->add($field, "O campo {$label} é obrigatório.");
                }
                break;
            case 'email':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->add($field, "Informe um e-mail válido em {$label}.");
                }
                break;
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->add($field, "O campo {$label} deve ser numérico.");
                }
                break;
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->add($field, "O campo {$label} deve ser um número inteiro.");
                }
                break;
            // min/max sempre medem TAMANHO DE TEXTO; limites numéricos usam
            // min_value/max_value. Decidir pelo formato do valor seria frágil:
            // um CPF só de dígitos ou uma senha numérica seriam lidos como número.
            case 'min':
                if (mb_strlen((string) $value) < (int) $param) {
                    $this->add($field, "O campo {$label} deve ter no mínimo {$param} caracteres.");
                }
                break;
            case 'max':
                if (mb_strlen((string) $value) > (int) $param) {
                    $this->add($field, "O campo {$label} deve ter no máximo {$param} caracteres.");
                }
                break;
            case 'min_value':
                if (!is_numeric($value) || (float) $value < (float) $param) {
                    $this->add($field, "O campo {$label} deve ser maior ou igual a {$param}.");
                }
                break;
            case 'max_value':
                if (!is_numeric($value) || (float) $value > (float) $param) {
                    $this->add($field, "O campo {$label} deve ser menor ou igual a {$param}.");
                }
                break;
            case 'date':
                if (!self::isDate((string) $value)) {
                    $this->add($field, "Informe uma data válida em {$label}.");
                }
                break;
            case 'in':
                $options = explode(',', (string) $param);
                if (!in_array((string) $value, $options, true)) {
                    $this->add($field, "Valor inválido para {$label}.");
                }
                break;
            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->add($field, "A confirmação de {$label} não confere.");
                }
                break;
            case 'unique':
                // unique:tabela,coluna[,id_a_ignorar]
                [$table, $column, $ignoreId] = array_pad(explode(',', (string) $param), 3, null);
                $sql    = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
                $params = [$value];
                if ($ignoreId !== null && $ignoreId !== '') {
                    $sql .= ' AND id <> ?';
                    $params[] = $ignoreId;
                }
                if ((int) Database::value($sql, $params, 0) > 0) {
                    $this->add($field, "Já existe um registro com este {$label}.");
                }
                break;
            case 'exists':
                [$table] = explode(',', (string) $param);
                if ((int) Database::value("SELECT COUNT(*) FROM {$table} WHERE id = ?", [$value], 0) === 0) {
                    $this->add($field, "O registro selecionado em {$label} não existe.");
                }
                break;
        }
    }

    public static function isDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    public function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0];
        }
        return null;
    }
}
