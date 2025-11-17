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
            'field'         => $field,
            'label'         => $label,
            'old'           => $old,
            'new'           => $new,
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
            'email'  => 'メールアドレス',
            'tel01'  => '電話番号（市外局番）',
            'tel02'  => '電話番号（市内局番）',
            'tel03'  => '電話番号（加入者番号）',
            'zip01'  => '郵便番号（3桁）',
            'zip02'  => '郵便番号（4桁）',
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

            $normalizedOld = $this->normalize($old);
            $normalizedNew = $this->normalize($new);
            if ($normalizedOld === $normalizedNew) {
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

    /**
     * フィールド名から表示用ラベルを取得する.
     *
     * @param string $field
     *
     * @return string
     */
    private function getFieldLabel(string $field): string
    {
        return $this->fieldLabels[$field] ?? $field;
    }

    /**
     * メール表示用に値を整形する.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function formatValue($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'はい' : 'いいえ';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                return '[encoding error]';
            }
            return $encoded;
        }

        if ($value === null) {
            return '';
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : get_class($value);
        }

        return (string) $value;
    }

    /**
     * 差分判定用に値を正規化する.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function normalize($value)
    {
        if (is_string($value)) {
            return preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $value) ?? $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTime::ATOM);
        }

        return $value;
    }
}