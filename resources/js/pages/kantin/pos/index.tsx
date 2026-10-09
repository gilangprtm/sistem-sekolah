import { Head } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { Camera, LogOut, Minus, Plus, Search, ShoppingBasket, Trash2 } from 'lucide-react';
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
type IdentifiedStudent = { id: number; name: string; nis: string | null; balance: string };

const rupiah = (amount: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);

export default function KantinPos({ categories, products }: Props) {
    const [student, setStudent] = useState<IdentifiedStudent | null>(null);
    const [qr, setQr] = useState('');
    const [scanning, setScanning] = useState(false);
    const [busy, setBusy] = useState(false);
    const [paying, setPaying] = useState(false);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);

    function stopCamera() {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setScanning(false);
    }

    useEffect(() => () => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
    }, []);

    async function identify(value: string) {
        if (busy) return;
        setBusy(true);
        setError('');
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch('/kantin/pos/identify', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? ''),
                },
                body: JSON.stringify({ qr: value.trim() }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message ?? 'Kartu tidak dapat diverifikasi.');
            stopCamera();
            setStudent(data.student as IdentifiedStudent);
            setCart({});
            setQr('');
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : 'Gagal membaca kartu.');
        } finally {
            setBusy(false);
        }
    }

    async function startCamera() {
        setError('');
        if (!navigator.mediaDevices?.getUserMedia) {
            setError('Kamera tidak tersedia. Gunakan HTTPS atau pemindai QR eksternal.');
            return;
        }
        type Detector = { detect: (source: HTMLVideoElement) => Promise<Array<{ rawValue: string }>> };
        type DetectorClass = new (options: { formats: string[] }) => Detector;
        const DetectorAPI = (window as Window & { BarcodeDetector?: DetectorClass }).BarcodeDetector;
        if (!DetectorAPI) {
            setError('Browser ini belum mendukung pemindaian QR lewat kamera. Gunakan browser yang mendukung BarcodeDetector atau pemindai QR eksternal.');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            streamRef.current = stream;
            setScanning(true);
            const video = videoRef.current;
            if (!video) { stopCamera(); return; }
            video.srcObject = stream;
            await video.play();
            const detector = new DetectorAPI({ formats: ['qr_code'] });
            while (streamRef.current === stream && stream.active) {
                const codes = await detector.detect(video);
                if (codes[0]?.rawValue) {
                    await identify(codes[0].rawValue);
                    break;
                }
                await new Promise((resolve) => setTimeout(resolve, 200));
            }
        } catch {
            setError('Kamera gagal diakses. Pastikan izin kamera diberikan.');
            stopCamera();
        }
    }

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

    const csrfHeaders = () => ({
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? ''),
    });

    async function checkout() {
        if (!student || !count || paying || total > Number(student.balance)) return;
        setPaying(true);
        setError('');
        try {
            const response = await fetch('/kantin/pos/checkout', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify({ items: items.map((item) => ({ product_id: item.id, quantity: cart[item.id] })) }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message ?? 'Pembayaran gagal.');
            setCart({});
            setStudent(null);
            setQr('');
            setSearch('');
            setCategory(null);
            setNotice('Pembayaran berhasil! Total ' + rupiah(Number(data.total)) + '. Sisa saldo ' + rupiah(Number(data.balance)) + '.');
            window.setTimeout(() => setNotice(''), 5000);
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : 'Pembayaran gagal.');
        } finally {
            setPaying(false);
        }
    }

    async function exitPos() {
        if (count > 0 && !window.confirm('Keluar dari POS? Barang di keranjang akan dihapus.')) return;
        try {
            await fetch('/kantin/pos/exit', { method: 'POST', credentials: 'same-origin', headers: csrfHeaders(), body: '{}' });
        } catch { /* The server session will expire independently. */ }
        stopCamera();
        setCart({});
        setStudent(null);
        setQr('');
        setSearch('');
        setCategory(null);
        setError('');
    }

    function changeQuantity(id: number, delta: number) {
        setCart((current) => {
            const next = { ...current };
            const quantity = Math.max(0, (next[id] ?? 0) + delta);
            if (quantity === 0) delete next[id];
            else next[id] = quantity;
            return next;
        });
    }

    if (!student) {
        return (
            <div className="grid min-h-dvh place-items-center bg-slate-50 p-5 text-slate-900">
                <Head title="Scan Kartu Pelajar - Kantin" />
                <div className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <div className="mx-auto mb-5 grid size-20 place-items-center rounded-3xl bg-violet-100">
                        <Camera className="size-10 text-violet-700" />
                    </div>
                    <p className="text-sm font-semibold text-violet-700">SMPN 17 DENPASAR</p>
                    <h1 className="mt-2 text-3xl font-bold">Kantin Sekolah</h1>
                    <p className="mt-3 text-slate-500">Scan QR Code pada kartu pelajar untuk mulai berbelanja.</p>
                    <video ref={videoRef} muted playsInline autoPlay className={`mt-6 aspect-video w-full rounded-2xl bg-black object-cover ${scanning ? "" : "hidden"}`} />
                    <button type="button" onClick={scanning ? stopCamera : startCamera} disabled={busy}
                        className="mt-6 min-h-14 w-full rounded-2xl bg-violet-700 font-semibold text-white disabled:opacity-50">
                        {scanning ? 'Batalkan Scan' : 'Scan QR Kartu Pelajar'}
                    </button>
                    <form onSubmit={(event) => { event.preventDefault(); void identify(qr); }} className="mt-6 space-y-3 border-t border-slate-100 pt-5">
                        <label htmlFor="qr-input" className="block text-left text-sm text-slate-500">Pemindai QR eksternal / input kode kartu</label>
                        <input id="qr-input" autoComplete="off" type="text" value={qr} onChange={(event) => setQr(event.target.value)}
                            placeholder="Scan kode di sini" className="min-h-12 w-full rounded-xl border border-slate-200 px-4" />
                        <button type="submit" disabled={!qr.trim() || busy} className="min-h-12 w-full rounded-xl border border-violet-200 font-semibold text-violet-700 disabled:opacity-40">
                            {busy ? 'Memeriksa...' : 'Verifikasi Kartu'}
                        </button>
                    </form>
                    {notice && <p role="status" className="mt-4 rounded-xl bg-green-50 p-4 font-semibold text-green-700">{notice}</p>}
                    {error && <p role="alert" className="mt-4 text-sm text-red-600">{error}</p>}
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-dvh bg-slate-50 text-slate-900">
            <Head title="Kantin Sekolah" />
            <div className="mx-auto flex min-h-dvh max-w-7xl flex-col lg:flex-row">
                <main className="min-w-0 flex-1 p-5 pb-[32vh] sm:p-8 lg:pb-8">
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-violet-200 bg-violet-50 p-4">
                        <div>
                            <p className="text-xs text-violet-700">Siswa aktif</p>
                            <p className="font-bold">{student.name}</p>
                            <p className="text-xs text-slate-500">{student.nis ? `NIS ${student.nis}` : 'Kartu terverifikasi'}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-violet-700">Saldo tersedia</p>
                            <p className="text-xl font-bold text-violet-800">{rupiah(Number(student.balance))}</p>
                            <button type="button" onClick={exitPos} className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-violet-700">
                                <LogOut className="size-3" /> Keluar
                            </button>
                        </div>
                    </div>
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

                <aside className="fixed inset-x-0 bottom-0 z-40 flex h-[27dvh] min-h-44 flex-col border-t border-slate-200 bg-white p-3 shadow-[0_-8px_25px_rgba(0,0,0,.08)] lg:sticky lg:top-0 lg:h-dvh lg:w-96 lg:p-6 lg:shadow-none">
                    <div className="mb-2 flex items-center justify-between lg:mb-5">
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
                    <div className="min-h-0 flex-1 space-y-3 overflow-y-auto lg:space-y-4">
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
                    <div className="mt-2 shrink-0 border-t border-slate-200 pt-2 lg:mt-5 lg:pt-5">
                        <div className="flex justify-between text-base font-semibold">
                            <span>Total belanja</span><span>{rupiah(total)}</span>
                        </div>
                        <p className="mt-2 text-sm text-slate-500">Sisa saldo setelah belanja: <strong className={total > Number(student.balance) ? 'text-red-600' : 'text-violet-700'}>{rupiah(Number(student.balance) - total)}</strong></p>
                        {error && <p role="alert" className="mt-2 text-sm text-red-600">{error}</p>}
                        <button type="button" onClick={() => void checkout()} disabled={!count || paying || total > Number(student.balance)} className="mt-2 min-h-12 w-full rounded-2xl bg-violet-700 text-base font-bold text-white disabled:bg-slate-200 disabled:text-slate-500 lg:mt-4 lg:min-h-14">
                            {paying ? 'Memproses pembayaran...' : total > Number(student.balance) ? 'Saldo tidak mencukupi' : 'Bayar ' + rupiah(total)}
                        </button>
                    </div>
                </aside>
            </div>
        </div>
    );
}
