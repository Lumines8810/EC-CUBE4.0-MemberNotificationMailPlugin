<?php

namespace Plugin\CustomerChangeNotify\Service;

/**
 * 会員情報変更の差分を保持する DTO.
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
