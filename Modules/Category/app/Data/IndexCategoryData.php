<?php
namespace Modules\Category\Data;

use Spatie\DataTransferObject\DataTransferObject;

class IndexCategoryData extends DataTransferObject
{
    public ?int $page;
    public ?int $per_page;
    public ?string $q;
    public ?bool $is_active;

    public static function from(array $validated): self
    {
        return new self([
            'page' => $validated['page'] ?? 1,
            'per_page' => $validated['per_page'] ?? 15,
            'q' => $validated['q'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? (bool)$validated['is_active'] : null,
        ]);
    }
}
