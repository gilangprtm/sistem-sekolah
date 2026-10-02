<?php

namespace App\Services\Assistant;

use App\Models\User;

class AssistantToolRegistry
{
    /** @return array<int, array<string, mixed>> */
    public function forUser(User $user): array
    {
        if (! $user->can('inventory.view')) {
            return [$this->teacherSubjectsTool()];
        }

        return [
            $this->tool('inventory_items', 'Daftar resource aset inventaris secara read-only. Gunakan filter resource dan pagination bila diperlukan.', [

                'search' => ['type' => ['string', 'null'], 'maxLength' => 100],
                'inventory_category_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                'inventory_type_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                'condition' => ['type' => ['string', 'null'], 'enum' => ['B', 'KB', 'RB', null]],
                'year' => ['type' => ['integer', 'null'], 'minimum' => 1900, 'maximum' => 2100],
                'asset_kind' => ['type' => ['string', 'null'], 'enum' => ['tangible', 'intangible', null]],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ]),
            $this->tool('inventory_registers', 'Daftar resource register/unit inventaris secara read-only dengan relasi item dan ruangan.', [
                'search' => ['type' => ['string', 'null'], 'maxLength' => 100],
                'inventory_item_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                'room_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                'condition' => ['type' => ['string', 'null'], 'enum' => ['B', 'KB', 'RB', null]],
                'year' => ['type' => ['integer', 'null'], 'minimum' => 1900, 'maximum' => 2100],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ]),
            $this->tool('inventory_rooms', 'Daftar resource ruangan inventaris secara read-only. Response berisi data room dan meta pagination.', [
                'search' => ['type' => ['string', 'null'], 'maxLength' => 100],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ]),
            $this->tool('inventory_categories', 'Daftar resource kategori inventaris secara read-only dengan jumlah aset dan pagination.', [
                'search' => ['type' => ['string', 'null'], 'maxLength' => 100],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ]),
            $this->teacherSubjectsTool(),
        ];
    }

    /** @return array{type: string, function: array<string, mixed>}
     */
    private function teacherSubjectsTool(): array
    {
        return $this->tool('teacher_subjects', 'Cari nama Guru Mata Pelajaran aktif secara read-only berdasarkan kode atau nama mata pelajaran.', [
            'subject_search' => ['type' => ['string', 'null'], 'maxLength' => 100],
            'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 25],
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  array<int, string>  $required
     * @return array{type: string, function: array<string, mixed>}
     */
    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'additionalProperties' => false,
                    ...($required === [] ? [] : ['required' => $required]),
                ],
            ],
        ];
    }
}
