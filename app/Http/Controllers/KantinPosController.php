<?php

namespace App\Http\Controllers;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use Inertia\Inertia;
use Inertia\Response;

class KantinPosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('kantin/pos/index', [
            'categories' => KantinCategory::query()->orderBy('name')->get(['id', 'name']),
            'products' => KantinBarang::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'kantin_kategori_id', 'name', 'brand', 'satuan', 'harga']),
        ]);
    }
}
