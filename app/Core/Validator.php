<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $data;
    private array $errors = [];
    private array $validated = [];

    private array $labels = [
        'name'         => '用户名',
        'account'      => '账号',
        'email'        => '邮箱',
        'password'     => '密码',
        'password_confirmation' => '确认密码',
        'old_password' => '原密码',
        'group_id'     => '用户组',
        'capacity'     => '容量',
        'album_id'     => '相册',
        'storage_id'   => '存储策略',
        'token_name'   => '令牌名称',
        'status'       => '状态',
    ];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function label(string $field): string
    {
        return $this->labels[$field] ?? $field;
    }

    public function validate(array $rules): array
    {
        $this->errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $label = $this->label($field);
            $ruleList = explode('|', (string) $ruleString);

            foreach ($ruleList as $rule) {
                [$ruleName, $arg] = array_pad(explode(':', $rule, 2), 2, null);

                if ($ruleName === 'required') {
                    if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                        $this->errors[$field] = $label . '不能为空';
                        break;
                    }
                    continue;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                switch ($ruleName) {
                    case 'email':
                        if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                            $this->errors[$field] = $label . '格式不正确';
                        }
                        break;
                    case 'url':
                        if (!filter_var((string) $value, FILTER_VALIDATE_URL)) {
                            $this->errors[$field] = $label . '必须是合法的 URL';
                        }
                        break;
                    case 'integer':
                        if (!preg_match('/^-?\d+$/', (string) $value)) {
                            $this->errors[$field] = $label . '必须是整数';
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $this->errors[$field] = $label . '必须是数字';
                        }
                        break;
                    case 'min':
                        if (mb_strlen((string) $value) < (int) $arg) {
                            $this->errors[$field] = $label . '至少 ' . (int) $arg . ' 个字符';
                        }
                        break;
                    case 'max':
                        if (mb_strlen((string) $value) > (int) $arg) {
                            $this->errors[$field] = $label . '最多 ' . (int) $arg . ' 个字符';
                        }
                        break;
                    case 'in':
                        $allowed = explode(',', (string) $arg);
                        if (!in_array((string) $value, $allowed, true)) {
                            $this->errors[$field] = $label . '取值不合法';
                        }
                        break;
                    case 'same':
                        if ((string) $value !== (string) ($this->data[(string) $arg] ?? '')) {
                            $this->errors[$field] = $label . '与' . $this->label((string) $arg) . '不一致';
                        }
                        break;
                    case 'confirmed':
                        if ((string) $value !== (string) ($this->data[$field . '_confirmation'] ?? '')) {
                            $this->errors[$field] = '两次输入的' . $label . '不一致';
                        }
                        break;
                    case 'unique':
                        [$table, $column] = array_pad(explode(',', (string) $arg), 2, 'email');
                        $sql = 'SELECT COUNT(*) FROM ' . Database::table($table) . ' WHERE `' . $column . '` = :value';
                        $params = ['value' => $value];
                        if (isset($this->data['__id'])) {
                            $sql .= ' AND id <> :id';
                            $params['id'] = (int) $this->data['__id'];
                        }
                        if ((int) Database::value($sql, $params) > 0) {
                            $this->errors[$field] = '该' . $label . '已被占用';
                        }
                        break;
                }

                if (isset($this->errors[$field])) {
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
        return $this->validated;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function firstError(): string
    {
        $first = reset($this->errors);
        return $first === false ? '' : (string) $first;
    }
}
