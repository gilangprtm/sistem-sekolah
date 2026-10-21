import { Head } from '@inertiajs/react';
import {
    ChevronUp,
    LogOut,
    Minus,
    Plus,
    Search,
    ShoppingBasket,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useMemo, useState } from 'react';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import { formatRupiah } from '@/lib/utils';

type Category = { id: number; name: string };
type Product = {
    id: number;
    kantin_kategori_id: number;
    name: string;
    brand: string | null;
    satuan: string;
    harga: string;
    image_url: string | null;
};
type Props = { categories: Category[]; products: Product[] };
type IdentifiedStudent = {
    id: number;
    name: string;
    nis: string | null;
    balance: string;
};
type PinChallenge = { token: string };

export default function KantinPos({ categories, products }: Props) {
    const confirm = useConfirmation();
    const [student, setStudent] = useState<IdentifiedStudent | null>(null);
    const [pinChallenge, setPinChallenge] = useState<PinChallenge | null>(null);
    const [pin, setPin] = useState('');
    const [verificationToken, setVerificationToken] = useState('');
    const [nisn, setNisn] = useState('');
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

    useEffect(
        () => () => {
            streamRef.current?.getTracks().forEach((track) => track.stop());
        },
        [],
    );

    async function identify(value: string, method: 'nisn' | 'qr') {
        if (busy) {
            return;
        }

        setBusy(true);
        setError('');

        try {
            const csrf = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )?.content;
            const response = await fetch('/kantin/pos/identify', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(
                            /(?:^|; )XSRF-TOKEN=([^;]*)/,
                        )?.[1] ?? '',
                    ),
                },
                body: JSON.stringify({ [method]: value.trim() }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message ?? 'Kartu tidak dapat diverifikasi.',
                );
            }

            stopCamera();

            if (data.pin_required) {
                setPinChallenge({ token: data.challenge_token as string });
                setPin('');

                return;
            }

            setStudent(data.student as IdentifiedStudent);
            setVerificationToken(data.verification_token as string);
            setCart({});
            setNisn('');
        } catch (cause) {
            setError(
                cause instanceof Error ? cause.message : 'Gagal membaca kartu.',
            );
        } finally {
            setBusy(false);
        }
    }

    async function startCamera() {
        setError('');

        if (!navigator.mediaDevices?.getUserMedia) {
            setError(
                'Kamera tidak tersedia. Gunakan HTTPS atau masukkan NISN.',
            );

            return;
        }

        type Detector = {
            detect: (
                source: HTMLVideoElement,
            ) => Promise<Array<{ rawValue: string }>>;
        };
        type DetectorClass = new (options: { formats: string[] }) => Detector;
        const DetectorAPI = (
            window as Window & { BarcodeDetector?: DetectorClass }
        ).BarcodeDetector;

        if (!DetectorAPI) {
            setError(
                'Browser ini belum mendukung pemindaian QR lewat kamera. Silakan masuk menggunakan NISN.',
            );

            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });
            streamRef.current = stream;
            setScanning(true);
            const video = videoRef.current;

            if (!video) {
                stopCamera();

                return;
            }

            video.srcObject = stream;
            await video.play();
            const detector = new DetectorAPI({ formats: ['qr_code'] });

            while (streamRef.current === stream && stream.active) {
                const codes = await detector.detect(video);

                if (codes[0]?.rawValue) {
                    await identify(codes[0].rawValue, 'qr');
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
    const [imageErrors, setImageErrors] = useState<Record<number, boolean>>({});
    const [cart, setCart] = useState<Record<number, number>>({});
    const [cartOpen, setCartOpen] = useState(false);

    const visible = useMemo(
        () =>
            products.filter(
                (product) =>
                    (category === null ||
                        product.kantin_kategori_id === category) &&
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
        Accept: 'application/json',
        'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '',
        ),
    });

    async function verifyPin() {
        if (!pinChallenge || pin.length !== 6 || busy) {
            return;
        }

        setBusy(true);
        setError('');

        try {
            const response = await fetch('/kantin/pos/verify-pin', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify({
                    challenge_token: pinChallenge.token,
                    pin,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                if (response.status === 429) {
                    setPinChallenge(null);
                    setPin('');
                    setStudent(null);
                    setVerificationToken('');
                    setCart({});
                }

                throw new Error(
                    data.message ?? 'PIN tidak dapat diverifikasi.',
                );
            }

            setPinChallenge(null);
            setPin('');
            setStudent(data.student as IdentifiedStudent);
            setVerificationToken(data.verification_token as string);
            setCart({});
        } catch (cause) {
            setError(
                cause instanceof Error
                    ? cause.message
                    : 'PIN tidak dapat diverifikasi.',
            );
        } finally {
            setBusy(false);
        }
    }

    async function checkout() {
        if (!student || !count || paying || total > Number(student.balance)) {
            return;
        }

        setPaying(true);
        setError('');

        try {
            const response = await fetch('/kantin/pos/checkout', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify({
                    verification_token: verificationToken,
                    items: items.map((item) => ({
                        product_id: item.id,
                        quantity: cart[item.id],
                    })),
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message ?? 'Pembayaran gagal.'),
                );
            }

            setCart({});
            setStudent(null);
            setVerificationToken('');
            setNisn('');
            setSearch('');
            setCategory(null);
            setCartOpen(false);
            setNotice(
                'Pembayaran berhasil! Total ' +
                    formatRupiah(Number(data.total)) +
                    '. Sisa saldo ' +
                    formatRupiah(Number(data.balance)) +
                    '.',
            );
            window.setTimeout(() => setNotice(''), 5000);
        } catch (cause) {
            setError(
                cause instanceof Error ? cause.message : 'Pembayaran gagal.',
            );
        } finally {
            setPaying(false);
        }
    }

    async function exitPos() {
        if (
            count > 0 &&
            !(await confirm({
                title: 'Keluar dari POS?',
                description: 'Barang di keranjang akan dihapus.',
            }))
        ) {
            return;
        }

        try {
            await fetch('/kantin/pos/exit', {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: '{}',
            });
        } catch {
            /* The server session will expire independently. */
        }

        stopCamera();
        setCart({});
        setCartOpen(false);
        setStudent(null);
        setVerificationToken('');
        setPinChallenge(null);
        setPin('');
        setNisn('');
        setSearch('');
        setCategory(null);
        setError('');
    }

    function changeQuantity(id: number, delta: number) {
        setCart((current) => {
            const next = { ...current };
            const quantity = Math.max(0, (next[id] ?? 0) + delta);

            if (quantity === 0) {
                delete next[id];
            } else {
                next[id] = quantity;
            }

            return next;
        });
    }

    if (pinChallenge && !student) {
        return (
            <div
                className="relative grid min-h-dvh place-items-center overflow-hidden bg-[#160F42] bg-cover bg-center p-5"
                style={{
                    backgroundImage: "url('/images/hero-background.png')",
                }}
            >
                <div
                    className="absolute inset-0 bg-gradient-to-br from-[#160F42]/95 via-[#160F42]/80 to-[#5D1B68]/70"
                    aria-hidden="true"
                />
                <div className="relative w-full max-w-md rounded-3xl bg-white p-8 text-center shadow-2xl">
                    <img
                        src="/images/logo-sekolah.png"
                        alt="Logo SMPN 17 Denpasar"
                        className="mx-auto mb-5 size-16 object-contain"
                    />
                    <h1 className="text-2xl font-bold text-[#160F42]">
                        Masukkan PIN Kantin
                    </h1>
                    <p className="mt-3 text-sm text-slate-500">
                        PIN diperlukan sebelum memilih barang.
                    </p>
                    <form
                        className="mt-6 space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            void verifyPin();
                        }}
                    >
                        <input
                            type="password"
                            value={pin}
                            onChange={(event) =>
                                setPin(
                                    event.target.value
                                        .replace(/\D/g, '')
                                        .slice(0, 6),
                                )
                            }
                            inputMode="numeric"
                            autoComplete="off"
                            maxLength={6}
                            className="min-h-14 w-full rounded-xl border-2 border-[#160F42] bg-white px-4 text-center text-2xl tracking-[0.5em] text-black caret-black placeholder:text-black focus-visible:border-[#160F42] focus-visible:ring-2 focus-visible:ring-[#160F42]/30"
                            aria-label="PIN Kantin"
                        />
                        <button
                            type="submit"
                            disabled={busy || pin.length !== 6}
                            className="min-h-14 w-full rounded-2xl bg-[#160F42] font-semibold text-white disabled:opacity-50"
                        >
                            {busy ? 'Memeriksa...' : 'Lanjutkan'}
                        </button>
                    </form>
                    {error && (
                        <p role="alert" className="mt-4 text-sm text-red-600">
                            {error}
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={() => {
                            setPinChallenge(null);
                            setPin('');
                            setError('');
                        }}
                        className="mt-4 text-sm text-slate-500 underline"
                    >
                        Kembali scan
                    </button>
                </div>
            </div>
        );
    }

    if (!student) {
        return (
            <div
                className="relative grid min-h-dvh place-items-center overflow-hidden bg-[#160F42] bg-cover bg-center p-5 text-slate-900"
                style={{
                    backgroundImage: "url('/images/hero-background.png')",
                }}
            >
                <Head title="Scan Kartu Pelajar - Kantin" />
                <div
                    className="absolute inset-0 bg-gradient-to-br from-[#160F42]/95 via-[#160F42]/80 to-[#5D1B68]/70"
                    aria-hidden="true"
                />
                <div className="relative w-full max-w-md rounded-3xl border border-white/60 bg-white/95 p-8 text-center shadow-2xl backdrop-blur-sm">
                    <div className="mx-auto mb-5 grid size-20 place-items-center rounded-3xl bg-[#EDEAF5] p-3">
                        <img
                            src="/images/logo-sekolah.png"
                            alt="Logo SMPN 17 Denpasar"
                            className="size-full object-contain"
                        />
                    </div>
                    <p className="text-sm font-semibold text-[#160F42]">
                        SMPN 17 DENPASAR
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">Kantin Sekolah</h1>
                    <p className="mt-3 text-slate-500">
                        Scan kartu pelajar untuk mulai berbelanja, atau masuk
                        menggunakan NISN.
                    </p>
                    <video
                        ref={videoRef}
                        muted
                        playsInline
                        autoPlay
                        className={`mt-6 aspect-video w-full rounded-2xl bg-black object-cover ${scanning ? '' : 'hidden'}`}
                    />
                    <button
                        type="button"
                        onClick={scanning ? stopCamera : startCamera}
                        disabled={busy}
                        className="mt-6 min-h-14 w-full rounded-2xl bg-[#160F42] font-semibold text-white disabled:opacity-50"
                    >
                        {scanning ? 'Batalkan Scan' : 'Scan Kartu Pelajar'}
                    </button>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            void identify(nisn, 'nisn');
                        }}
                        className="mt-6 space-y-3 border-t border-slate-100 pt-5"
                    >
                        <p className="pt-3 text-left text-sm text-slate-500">
                            Atau masuk menggunakan NISN
                        </p>
                        <label htmlFor="nisn-input" className="sr-only">
                            NISN siswa
                        </label>
                        <input
                            id="nisn-input"
                            autoComplete="off"
                            type="text"
                            value={nisn}
                            onChange={(event) => setNisn(event.target.value)}
                            inputMode="numeric"
                            maxLength={20}
                            placeholder="Masukkan NISN"
                            className="min-h-12 w-full rounded-xl border border-slate-200 px-4"
                        />
                        <button
                            type="submit"
                            disabled={!nisn.trim() || busy}
                            className="min-h-12 w-full rounded-xl border border-[#DCD6EE] font-semibold text-[#160F42] disabled:opacity-40"
                        >
                            {busy ? 'Memeriksa...' : 'Verifikasi NISN'}
                        </button>
                    </form>
                    {notice && (
                        <p
                            role="status"
                            className="mt-4 rounded-xl bg-green-50 p-4 font-semibold text-green-700"
                        >
                            {notice}
                        </p>
                    )}
                    {error && (
                        <p role="alert" className="mt-4 text-sm text-red-600">
                            {error}
                        </p>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="relative isolate min-h-dvh bg-[#160F42] text-slate-900">
            <Head title="Kantin Sekolah" />
            <div
                aria-hidden="true"
                className="pointer-events-none fixed inset-0 -z-10 bg-[url('/images/hero-background.png')] bg-cover bg-center"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none fixed inset-0 -z-10 bg-[#100B32]/80"
            />
            <div className="mx-auto flex min-h-dvh max-w-7xl flex-col lg:flex-row">
                <main
                    className={`min-w-0 flex-1 p-5 sm:p-8 ${cartOpen ? 'pb-[calc(50dvh+1.25rem)] sm:pb-[calc(50dvh+2rem)]' : 'pb-[calc(18rem+env(safe-area-inset-bottom))] sm:pb-[calc(18rem+env(safe-area-inset-bottom))]'} lg:pb-8`}
                >
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#DCD6EE] bg-[#F4F2FA] p-4">
                        <div>
                            <p className="text-xs text-[#160F42]">
                                Siswa aktif
                            </p>
                            <p className="font-bold">{student.name}</p>
                            <p className="text-xs text-slate-500">
                                {student.nis
                                    ? `NIS ${student.nis}`
                                    : 'Kartu terverifikasi'}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-[#160F42]">
                                Saldo tersedia
                            </p>
                            <p className="text-xl font-bold text-[#160F42]">
                                {formatRupiah(Number(student.balance))}
                            </p>
                            <button
                                type="button"
                                onClick={exitPos}
                                className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[#160F42]"
                            >
                                <LogOut className="size-3" /> Keluar
                            </button>
                        </div>
                    </div>
                    <header className="mb-8 text-white">
                        <p className="text-sm font-semibold tracking-wide text-white/80">
                            SMPN 17 DENPASAR
                        </p>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Kantin Sekolah
                        </h1>
                        <p className="mt-2 text-sm text-white/75">
                            Pilih makanan dan minuman favoritmu.
                        </p>
                    </header>

                    <label className="mb-5 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <Search
                            className="size-5 text-slate-400"
                            aria-hidden="true"
                        />
                        <span className="sr-only">Cari produk</span>
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari makanan atau minuman..."
                            className="w-full bg-transparent text-base outline-none placeholder:text-slate-400"
                        />
                    </label>

                    <div className="mb-6 flex gap-2 overflow-x-auto pb-2">
                        {[{ id: null, name: 'Semua' }, ...categories].map(
                            (item) => (
                                <button
                                    key={item.id ?? 'all'}
                                    type="button"
                                    onClick={() => setCategory(item.id)}
                                    aria-pressed={category === item.id}
                                    className={`shrink-0 rounded-full px-5 py-3 text-sm font-semibold transition-colors ${category === item.id ? 'bg-[#160F42] text-white' : 'border border-slate-200 bg-white text-slate-600'}`}
                                >
                                    {item.name}
                                </button>
                            ),
                        )}
                    </div>

                    {visible.length ? (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                            {visible.map((product) => (
                                <article
                                    key={product.id}
                                    className="flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                                >
                                    <div className="mb-4 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl bg-[#F4F2FA]">
                                        {product.image_url &&
                                        !imageErrors[product.id] ? (
                                            <img
                                                src={product.image_url}
                                                alt={product.name}
                                                className="size-full object-cover"
                                                onError={() =>
                                                    setImageErrors((current) =>
                                                        current[product.id]
                                                            ? current
                                                            : {
                                                                  ...current,
                                                                  [product.id]: true,
                                                              },
                                                    )
                                                }
                                            />
                                        ) : (
                                            <ShoppingBasket
                                                className="size-10 text-[#8479B5]"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </div>
                                    <h2 className="line-clamp-2 min-h-10 text-sm leading-5 font-semibold">
                                        {product.name}
                                    </h2>
                                    <p className="mt-1 truncate text-xs text-slate-500">
                                        {product.brand || product.satuan}
                                    </p>
                                    <p className="mt-3 text-base font-bold text-[#160F42]">
                                        {formatRupiah(product.harga)}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            changeQuantity(product.id, 1)
                                        }
                                        className="mt-3 flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#160F42] text-sm font-semibold text-white active:bg-[#100B32]"
                                    >
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />{' '}
                                        Tambah
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

                <aside
                    aria-label="Keranjang belanja"
                    onKeyDown={(event) => {
                        if (event.key === 'Escape' && cartOpen) {
                            setCartOpen(false);
                            document.getElementById('cart-toggle')?.focus();
                        }
                    }}
                    className="fixed inset-x-0 bottom-0 z-40 flex max-h-[50dvh] flex-col overflow-hidden rounded-t-2xl border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_25px_rgba(0,0,0,.08)] lg:sticky lg:top-0 lg:h-dvh lg:max-h-none lg:w-96 lg:shrink-0 lg:rounded-none lg:p-6 lg:shadow-none"
                >
                    <button
                        id="cart-toggle"
                        type="button"
                        aria-expanded={cartOpen}
                        aria-controls="cart-details"
                        aria-label={
                            cartOpen ? 'Tutup keranjang' : 'Buka keranjang'
                        }
                        onClick={() => setCartOpen((open) => !open)}
                        className="flex min-h-20 w-full shrink-0 flex-col gap-3 px-5 pt-3 pb-4 text-left focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#160F42] lg:hidden"
                    >
                        <span
                            aria-hidden="true"
                            className="mx-auto h-1 w-10 rounded-full bg-slate-300"
                        />
                        <span className="flex w-full items-center justify-between gap-3">
                            <span className="flex items-center gap-2 font-semibold">
                                <ShoppingBasket
                                    className="size-5 text-[#160F42]"
                                    aria-hidden="true"
                                />
                                <span aria-live="polite">{count} barang</span>
                            </span>
                            <span className="flex items-center gap-2 font-bold text-[#160F42]">
                                <ChevronUp
                                    aria-hidden="true"
                                    className={`size-4 transition-transform motion-reduce:transition-none ${cartOpen ? 'rotate-180' : ''}`}
                                />
                            </span>
                        </span>
                    </button>
                    <div className="flex min-h-0 flex-col px-3 pb-3 lg:flex-1 lg:overflow-hidden lg:p-0">
                        <div
                            id="cart-details"
                            className={`${cartOpen ? 'flex' : 'hidden'} min-h-0 flex-col overflow-y-auto overscroll-contain lg:flex lg:flex-1 lg:overflow-visible`}
                        >
                            <div className="mb-2 flex shrink-0 items-center justify-between lg:mb-5">
                                <h2 className="sr-only items-center gap-2 text-lg font-bold lg:not-sr-only lg:flex">
                                    <ShoppingBasket className="size-5 text-[#160F42]" />
                                    Keranjang{' '}
                                    <span className="text-sm font-normal text-slate-500">
                                        ({count})
                                    </span>
                                </h2>
                                {count > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setCart({})}
                                        className="flex items-center gap-1 text-sm text-slate-500"
                                    >
                                        <Trash2 className="size-4" /> Kosongkan
                                    </button>
                                )}
                            </div>
                            <div className="shrink-0 space-y-3 lg:min-h-0 lg:flex-1 lg:shrink lg:space-y-4 lg:overflow-y-auto lg:overscroll-contain">
                                {items.length ? (
                                    items.map((product) => (
                                        <div
                                            key={product.id}
                                            className="flex items-center justify-between gap-3 border-b border-slate-100 pb-4"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold">
                                                    {product.name}
                                                </p>
                                                <p className="text-sm text-slate-500">
                                                    {formatRupiah(
                                                        Number(product.harga) *
                                                            cart[product.id],
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    aria-label={`Kurangi ${product.name}`}
                                                    onClick={() =>
                                                        changeQuantity(
                                                            product.id,
                                                            -1,
                                                        )
                                                    }
                                                    className="grid size-10 place-items-center rounded-xl border border-slate-200"
                                                >
                                                    <Minus className="size-4" />
                                                </button>
                                                <span className="w-5 text-center font-semibold">
                                                    {cart[product.id]}
                                                </span>
                                                <button
                                                    type="button"
                                                    aria-label={`Tambah ${product.name}`}
                                                    onClick={() =>
                                                        changeQuantity(
                                                            product.id,
                                                            1,
                                                        )
                                                    }
                                                    className="grid size-10 place-items-center rounded-xl bg-[#EDEAF5] text-[#160F42]"
                                                >
                                                    <Plus className="size-4" />
                                                </button>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="py-4 text-center text-sm text-slate-500 lg:py-10">
                                        Belum ada barang di keranjang.
                                    </p>
                                )}
                            </div>
                        </div>
                        <div
                            id="cart-summary"
                            className="mt-auto shrink-0 border-t border-slate-200 pt-2 lg:mt-5 lg:pt-5"
                        >
                            <div className="flex justify-between text-base font-semibold">
                                <span>Total belanja</span>
                                <span>{formatRupiah(total)}</span>
                            </div>
                            <p className="mt-2 text-sm text-slate-500">
                                Sisa saldo setelah belanja:{' '}
                                <strong
                                    className={
                                        total > Number(student.balance)
                                            ? 'text-red-600'
                                            : 'text-[#160F42]'
                                    }
                                >
                                    {formatRupiah(
                                        Number(student.balance) - total,
                                    )}
                                </strong>
                            </p>
                            {error && (
                                <p
                                    role="alert"
                                    className="mt-2 text-sm text-red-600"
                                >
                                    {error}
                                </p>
                            )}
                            <button
                                type="button"
                                onClick={() => void checkout()}
                                disabled={
                                    !count ||
                                    paying ||
                                    total > Number(student.balance)
                                }
                                className="mt-2 min-h-12 w-full rounded-2xl bg-[#160F42] text-base font-bold text-white disabled:bg-slate-200 disabled:text-slate-500 lg:mt-4 lg:min-h-14"
                            >
                                {paying
                                    ? 'Memproses pembayaran...'
                                    : total > Number(student.balance)
                                      ? 'Saldo tidak mencukupi'
                                      : 'Bayar ' + formatRupiah(total)}
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    );
}
