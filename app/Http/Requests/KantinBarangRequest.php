<?php

namespace App\Http\Requests;

use App\Models\KantinBarang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class KantinBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        $barang = $this->route('kantinBarang');

        return $this->user()?->can($barang instanceof KantinBarang ? 'kantin.barang.update' : 'kantin.barang.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_name' => is_string($this->input('category_name')) ? trim($this->input('category_name')) : $this->input('category_name'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'brand' => is_string($this->input('brand')) ? trim($this->input('brand')) : $this->input('brand'),
            'satuan' => is_string($this->input('satuan')) ? trim($this->input('satuan')) : $this->input('satuan'),
            'harga' => $this->normalizeHarga($this->input('harga')),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
        ]);
    }

    /**
     * Normalize Indonesian or plain decimal rupiah to a canonical decimal.
     *
     * Supported examples are 5000, 5.000, 5.000,50, 5000.50, and 5000,50.
     * Ambiguous or malformed separators are returned unchanged for validation
     * to reject instead of being silently reinterpreted.
     */
    private function normalizeHarga(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if (preg_match('/^\d+$/', $value) === 1) {
            return $value.'.00';
        }

        if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value) === 1) {
            return str_replace('.', '', $value).'.00';
        }

        if (preg_match('/^(\d{1,3}(?:\.\d{3})+),(\d{1,2})$/', $value, $matches) === 1) {
            return str_replace('.', '', $matches[1]).'.'.str_pad($matches[2], 2, '0');
        }

        if (preg_match('/^(\d+)[,.](\d{1,2})$/', $value, $matches) === 1) {
            return $matches[1].'.'.str_pad($matches[2], 2, '0');
        }

        return $value;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:m_kantin_kategori,id', 'required_without:category_name'],
            'category_name' => ['nullable', 'string', 'max:100', 'required_without:category_id'],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'satuan' => ['required', 'string', 'max:50'],
            'harga' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', File::image()->max(1024)],
            'remove_image' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
