<?php

namespace Plugin\CustomerChangeNotify\Service;

use Eccube\Entity\Customer;

/**
 * 差分を保持するシンプルな DTO.
 */
class Diff
{
    /**
     * @var array<string, array{old: mixed, new: mixed}>
     */
    private $changes = [];

    public function addChange(string $field, $old, $new): void
    {
        $this->changes[$field] = [
            'old' => $old,
            'new' => $new,
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
     * @param string[] $watchFields 監視対象フィールド
     */
    public function __construct(array $watchFields)
    {
        $this->watchFields = $watchFields;
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

            $normalizedOld = $this->normalize($old);
            $normalizedNew = $this->normalize($new);

            if (!in_array($field, $this->watchFields, true)) {
                continue;
            }

            if ($normalizedOld === $normalizedNew) {
                continue;
            }

            $diff->addChange($field, $old, $new);
        }

        return $diff;
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
            return trim($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTime::ATOM);
        }

        return $value;
    }
}
