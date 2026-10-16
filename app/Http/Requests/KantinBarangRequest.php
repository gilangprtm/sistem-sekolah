<?php

namespace App\Http\Requests;

use App\Models\KantinBarang;
use App\Support\RupiahAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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
            'harga' => RupiahAmount::normalize($this->input('harga')),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
        ]);
    }

    /**
     * @return array{category_id?: int|null, category_name?: string|null, name: string, brand?: string|null, satuan: string, harga: numeric-string|int|float, description?: string|null, status?: string, image?: UploadedFile|null, remove_image?: bool}
     */
    public function validatedData(): array
    {
        $data = $this->validator->validated();

        return [
            'category_id' => $data['category_id'] ?? null,
            'category_name' => $data['category_name'] ?? null,
            'name' => $data['name'],
            'brand' => $data['brand'] ?? null,
            'satuan' => $data['satuan'],
            'harga' => $data['harga'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? null,
            'image' => $data['image'] ?? null,
            'remove_image' => $data['remove_image'] ?? false,
        ];
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
            'harga' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,2})?$/', 'min:0', 'decimal:0,2'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', File::image()->max(1024)],
            'remove_image' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
