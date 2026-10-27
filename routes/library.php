<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('perpustakaan')->name('library.')->group(function (): void {
    Route::inertia('/koleksi-buku', 'library/placeholder', [
        'section' => 'collection',
    ])->middleware('role_or_permission:Super Admin|library.collection.view')->name('collection.index');

    Route::inertia('/sirkulasi', 'library/placeholder', [
        'section' => 'circulation',
    ])->middleware('role_or_permission:Super Admin|library.circulation.view')->name('circulation.index');
});
