<?php

namespace App\Services\Assistant;

use App\Models\User;

class AssistantToolRegistry
{
    /** @return array<int, array<string, mixed>> */
    public function forUser(User $user): array
    {
        if (! $user->can('inventory.view')) {
            return [];
        }

        return [
            ['type' => 'function', 'function' => [
                'name' => 'inventory_summary',
                'description' => 'Ringkasan jumlah inventaris. Read-only.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'inventory_query',
                'description' => 'Query aset inventaris read-only. Gunakan field/filter/sort yang terdaftar saja; hasil tidak boleh dipakai untuk mengarang kondisi register individual.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'fields' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['kode_barang', 'nama_jenis_barang', 'merk_type', 'asal_perolehan', 'tahun_pembelian', 'harga', 'keterangan', 'inventory_category_id', 'inventory_type_id', 'asset_kind', 'tangible_asset_type_id', 'intangible_asset_type_id']], 'maxItems' => 13],
                    'filters' => ['type' => 'object', 'properties' => ['search' => ['type' => 'string', 'maxLength' => 100], 'inventory_category_id' => ['type' => 'integer'], 'inventory_type_id' => ['type' => 'integer'], 'asset_kind' => ['type' => 'string', 'enum' => ['tangible', 'intangible']], 'tahun_pembelian' => ['type' => 'integer']], 'additionalProperties' => false],
                    'sort' => ['type' => 'object', 'properties' => ['field' => ['type' => 'string', 'enum' => ['kode_barang', 'nama_jenis_barang', 'merk_type', 'asal_perolehan', 'tahun_pembelian', 'harga', 'keterangan', 'inventory_category_id', 'inventory_type_id', 'asset_kind', 'tangible_asset_type_id', 'intangible_asset_type_id']], 'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']]], 'additionalProperties' => false],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ], 'additionalProperties' => false],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'inventory_register_query',
                'description' => 'Query register/unit inventaris secara read-only. Hasil menampilkan field allowlist dan display_code terhitung backend; tidak boleh digunakan untuk mengarang data yang tidak dikembalikan.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'fields' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['id', 'inventory_item_id', 'register', 'condition', 'display_code', 'item.kode_barang', 'room.id', 'room.name', 'room.code', 'item.nama_jenis_barang', 'item.merk_type', 'item.tahun_pembelian', 'item.harga', 'item.asal_perolehan', 'item.satuan', 'item.inventory_category_id', 'item.inventory_type_id', 'item.asset_kind']], 'maxItems' => 18],
                    'filters' => ['type' => 'object', 'description' => 'Semua key opsional. Kirim hanya filter yang diminta pengguna; jangan isi key lain dengan string kosong, 0, atau nilai default.', 'properties' => ['search' => ['type' => 'string', 'maxLength' => 100], 'register' => ['type' => 'string', 'maxLength' => 100], 'condition' => ['type' => 'string', 'enum' => ['B', 'KB', 'RB']], 'inventory_item_id' => ['type' => 'integer'], 'kode_barang' => ['type' => 'string', 'maxLength' => 100], 'tahun_pembelian' => ['type' => 'integer'], 'asal_perolehan' => ['type' => 'string', 'maxLength' => 100], 'satuan' => ['type' => 'string', 'maxLength' => 100], 'inventory_category_id' => ['type' => 'integer'], 'inventory_type_id' => ['type' => 'integer'], 'asset_kind' => ['type' => 'string', 'enum' => ['tangible', 'intangible']], 'room_id' => ['type' => 'integer'], 'room_name' => ['type' => 'string', 'maxLength' => 100], 'room_code' => ['type' => 'string', 'maxLength' => 100]], 'additionalProperties' => false],
                    'sort' => ['type' => 'object', 'properties' => ['field' => ['type' => 'string', 'enum' => ['id', 'inventory_item_id', 'register', 'condition', 'kode_barang']], 'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']]], 'additionalProperties' => false],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ], 'additionalProperties' => false],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'inventory_room_query',
                'description' => 'Query Inventaris Ruangan secara read-only. Gunakan summary untuk jumlah ruangan/register, list untuk daftar ruangan beserta jumlah register.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'operation' => ['type' => 'string', 'enum' => ['summary', 'list']],
                    'filters' => ['type' => 'object', 'properties' => ['room_id' => ['type' => 'integer', 'minimum' => 1], 'room_name' => ['type' => 'string', 'maxLength' => 100], 'room_code' => ['type' => 'string', 'maxLength' => 100], 'placement' => ['type' => 'string', 'enum' => ['assigned', 'unassigned']]], 'additionalProperties' => false],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ], 'additionalProperties' => false],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'inventory_search',
                'description' => 'Mencari inventaris secara read-only.',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'maxLength' => 100]], 'required' => ['query'], 'additionalProperties' => false],
            ]],
        ];
    }
}
