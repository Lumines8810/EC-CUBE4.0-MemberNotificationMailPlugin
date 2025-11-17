<?php

namespace Plugin\CustomerChangeNotify\Service;

use Eccube\Entity\Customer;

/**
 * 差分を保持するシンプルな DTO.
 */
class Diff
{
    /**
     * @var array<string, array{field: string, label: string, old: mixed, new: mixed, old_formatted: string, new_formatted: string}>
     */
    private $changes = [];

    public function addChange(string $field, string $label, $old, $new, string $oldFormatted, string $newFormatted): void
    {
        $this->changes[$field] = [
            'field' => $field,
            'label' => $label,
            'old' => $old,
            'new' => $new,
            'old_formatted' => $oldFormatted,
            'new_formatted' => $newFormatted,
        ];
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function isEmpty(): bool
    {
        return empty($this->changes);
    }
}

/**
 * Doctrine の変更セットから通知対象の差分だけ抽出するビルダ.
 */
class DiffBuilder
{
    /**
     * @var string[]
     */
    private $watchFields;

    /**
     * @var array<string, string>
     */
    private $fieldLabels;

    /**
     * @param string[] $watchFields 監視対象フィールド
     */
    public function __construct(array $watchFields)
    {
        $this->watchFields = $watchFields;
        $this->fieldLabels = [
            'name01' => '姓',
            'name02' => '名',
            'kana01' => 'セイ',
            'kana02' => 'メイ',
            'email' => 'メールアドレス',
            'tel01' => '電話番号（市外局番）',
            'tel02' => '電話番号（市内局番）',
            'tel03' => '電話番号（加入者番号）',
            'zip01' => '郵便番号（3桁）',
            'zip02' => '郵便番号（4桁）',
            'addr01' => '住所1',
            'addr02' => '住所2',
        ];
    }

    /**
     * @param Customer $customer
     * @param array    $changeSet Doctrine の変更セット
     *
     * @return Diff
     */
    public function build(Customer $customer, array $changeSet): Diff
    {
        $diff = new Diff();

        foreach ($changeSet as $field => $value) {
            // Doctrine の変更セット要素は [旧値, 新値] の配列
            if (!is_array($value) || count($value) !== 2) {
                continue;
            }

            list($old, $new) = $value;

            if (!in_array($field, $this->watchFields, true)) {
                continue;
            }

            if ($old == $new) {
                continue;
            }

            $diff->addChange(
                $field,
                $this->getFieldLabel($field),
                $old,
                $new,
                $this->formatValue($old),
                $this->formatValue($new)
            );
        }

        return $diff;
    }

    private function getFieldLabel(string $field): string
    {
        return $this->fieldLabels[$field] ?? $field;
    }

    private function formatValue($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'はい' : 'いいえ';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
