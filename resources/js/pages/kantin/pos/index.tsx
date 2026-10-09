import { Head } from '@inertiajs/react';
import { Minus, Plus, Search, ShoppingBasket, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

type Category = { id: number; name: string };
type Product = {
    id: number;
    kantin_kategori_id: number;
    name: string;
    brand: string | null;
    satuan: string;
    harga: string;
};
type Props = { categories: Category[]; products: Product[] };

const rupiah = (amount: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);

export default function KantinPos({ categories, products }: Props) {
    const [category, setCategory] = useState<number | null>(null);
    const [search, setSearch] = useState('');
    const [cart, setCart] = useState<Record<number, number>>({});

    const visible = useMemo(
        () =>
            products.filter(
                (product) =>
                    (category === null || product.kantin_kategori_id === category) &&
                    `${product.name} ${product.brand ?? ''}`
                        .toLowerCase()
                        .includes(search.trim().toLowerCase()),
            ),
        [category, products, search],
    );

    const items = products.filter((product) => (cart[product.id] ?? 0) > 0);
    const count = items.reduce((sum, product) => sum + cart[product.id], 0);
    const total = items.reduce(
        (sum, product) => sum + Number(product.harga) * cart[product.id],
        0,
    );

    function changeQuantity(id: number, delta: number) {
        setCart((current) => {
            const next = { ...current };
            const quantity = Math.max(0, (next[id] ?? 0) + delta);
            if (quantity === 0) delete next[id];
            else next[id] = quantity;
            return next;
        });
    }

    return (
        <div className="min-h-dvh bg-slate-50 text-slate-900">
            <Head title="Kantin Sekolah" />
            <div className="mx-auto flex min-h-dvh max-w-7xl flex-col lg:flex-row">
                <main className="min-w-0 flex-1 p-5 pb-36 sm:p-8 lg:pb-8">
                    <header className="mb-8">
                        <p className="text-sm font-semibold tracking-wide text-violet-700">
                            SMPN 17 DENPASAR
                        </p>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Kantin Sekolah
                        </h1>
                        <p className="mt-2 text-sm text-slate-500">
                            Pilih makanan dan minuman favoritmu.
                        </p>
                    </header>

                    <label className="mb-5 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <Search className="size-5 text-slate-400" aria-hidden="true" />
                        <span className="sr-only">Cari produk</span>
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari makanan atau minuman..."
                            className="w-full bg-transparent text-base outline-none placeholder:text-slate-400"
                        />
                    </label>

                    <div className="mb-6 flex gap-2 overflow-x-auto pb-2">
                        {[{ id: null, name: 'Semua' }, ...categories].map((item) => (
                            <button
                                key={item.id ?? 'all'}
                                type="button"
                                onClick={() => setCategory(item.id)}
                                aria-pressed={category === item.id}
                                className={`shrink-0 rounded-full px-5 py-3 text-sm font-semibold transition-colors ${category === item.id ? 'bg-violet-700 text-white' : 'border border-slate-200 bg-white text-slate-600'}`}
                            >
                                {item.name}
                            </button>
                        ))}
                    </div>

                    {visible.length ? (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                            {visible.map((product) => (
                                <article key={product.id} className="flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <div className="mb-4 grid aspect-[4/3] place-items-center rounded-xl bg-violet-50">
                                        <ShoppingBasket className="size-10 text-violet-300" aria-hidden="true" />
                                    </div>
                                    <h2 className="line-clamp-2 min-h-10 text-sm font-semibold leading-5">{product.name}</h2>
                                    <p className="mt-1 truncate text-xs text-slate-500">{product.brand || product.satuan}</p>
                                    <p className="mt-3 text-base font-bold text-violet-800">{rupiah(Number(product.harga))}</p>
                                    <button
                                        type="button"
                                        onClick={() => changeQuantity(product.id, 1)}
                                        className="mt-3 flex min-h-11 items-center justify-center gap-2 rounded-xl bg-violet-700 text-sm font-semibold text-white active:bg-violet-800"
                                    >
                                        <Plus className="size-4" aria-hidden="true" /> Tambah
                                    </button>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
                            Tidak ada produk yang cocok.
                        </div>
                    )}
                </main>

                <aside className="border-t border-slate-200 bg-white p-5 lg:sticky lg:top-0 lg:flex lg:h-dvh lg:w-96 lg:flex-col lg:border-t-0 lg:border-l lg:p-6">
                    <div className="mb-5 flex items-center justify-between">
                        <h2 className="flex items-center gap-2 text-lg font-bold">
                            <ShoppingBasket className="size-5 text-violet-700" />
                            Keranjang <span className="text-sm font-normal text-slate-500">({count})</span>
                        </h2>
                        {count > 0 && (
                            <button type="button" onClick={() => setCart({})} className="flex items-center gap-1 text-sm text-slate-500">
                                <Trash2 className="size-4" /> Kosongkan
                            </button>
                        )}
                    </div>
                    <div className="max-h-72 flex-1 space-y-4 overflow-y-auto lg:max-h-none">
                        {items.length ? items.map((product) => (
                            <div key={product.id} className="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold">{product.name}</p>
                                    <p className="text-sm text-slate-500">{rupiah(Number(product.harga) * cart[product.id])}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button type="button" aria-label={`Kurangi ${product.name}`} onClick={() => changeQuantity(product.id, -1)} className="grid size-10 place-items-center rounded-xl border border-slate-200"><Minus className="size-4" /></button>
                                    <span className="w-5 text-center font-semibold">{cart[product.id]}</span>
                                    <button type="button" aria-label={`Tambah ${product.name}`} onClick={() => changeQuantity(product.id, 1)} className="grid size-10 place-items-center rounded-xl bg-violet-100 text-violet-800"><Plus className="size-4" /></button>
                                </div>
                            </div>
                        )) : <p className="py-10 text-center text-sm text-slate-500">Belum ada barang di keranjang.</p>}
                    </div>
                    <div className="mt-5 border-t border-slate-200 pt-5">
                        <div className="flex justify-between text-base font-semibold">
                            <span>Total belanja</span><span>{rupiah(total)}</span>
                        </div>
                        <button type="button" disabled className="mt-4 min-h-14 w-full rounded-2xl bg-slate-200 text-base font-bold text-slate-500">
                            Pembayaran belum tersedia
                        </button>
                        <p className="mt-2 text-center text-xs text-slate-400">
                            Fitur pembayaran saldo siswa akan ditambahkan berikutnya.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    );
}
