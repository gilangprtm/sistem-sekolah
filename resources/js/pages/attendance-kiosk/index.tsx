import { Head } from '@inertiajs/react';
import { ScanLine } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function AttendanceKiosk() {
    const [message, setMessage] = useState('Siap dipindai');
    const busy = useRef(false);

    useEffect(() => {
        let buffer = '';
        let timer = 0;

        const submit = async () => {
            const qr = buffer;
            buffer = '';
            window.clearTimeout(timer);

            if (!qr || busy.current) {
                return;
            }

            busy.current = true;
            setMessage('Memproses kehadiran...');

            try {
                const csrf = document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content;
                const response = await fetch('/absensi/check-in', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    body: JSON.stringify({ qr }),
                });
                const data = await response.json();
                setMessage(data.message ?? 'Kehadiran tidak dapat dicatat.');
            } catch {
                setMessage('Kehadiran tidak dapat dicatat.');
            } finally {
                busy.current = false;
            }
        };

        const isInteractive = (target: EventTarget | null): boolean =>
            target instanceof HTMLElement &&
            Boolean(
                target.closest(
                    'input, textarea, select, button, a, [contenteditable="true"], [role="button"]',
                ),
            );
        const handleKeyDown = (event: KeyboardEvent) => {
            if (
                busy.current ||
                event.isComposing ||
                event.ctrlKey ||
                event.altKey ||
                event.metaKey ||
                isInteractive(event.target)
            ) {
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                void submit();
            } else if (event.key.length === 1) {
                buffer += event.key;
                window.clearTimeout(timer);
                timer = window.setTimeout(() => {
                    buffer = '';
                }, 500);
            }
        };
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            window.clearTimeout(timer);
            buffer = '';
        };
    }, []);

    return (
        <main
            className="relative grid min-h-dvh place-items-center overflow-hidden bg-[#160F42] bg-cover bg-center p-4 text-slate-900 sm:p-6"
            style={{ backgroundImage: "url('/images/hero-background.png')" }}
        >
            <Head title="Absensi Siswa" />
            <div
                className="absolute inset-0 bg-gradient-to-br from-[#160F42]/95 via-[#160F42]/80 to-[#5D1B68]/70"
                aria-hidden="true"
            />
            <section className="relative w-full max-w-md rounded-3xl border border-white/60 bg-white/95 p-5 text-center shadow-2xl backdrop-blur-sm sm:p-6">
                <div className="mx-auto mb-3 grid size-16 place-items-center rounded-2xl bg-[#EDEAF5] p-2">
                    <img
                        src="/images/logo-sekolah.png"
                        alt="Logo SMPN 17 Denpasar"
                        className="size-full object-contain"
                    />
                </div>
                <p className="text-sm font-semibold tracking-wide text-[#160F42]">
                    SMPN 17 DENPASAR
                </p>
                <h1 className="mt-1 text-2xl font-bold text-[#160F42]">
                    Absensi Siswa
                </h1>
                <p className="mt-2 text-slate-600">
                    Pindai QR kartu siswa untuk mencatat kehadiran.
                </p>
                <div className="mt-5 flex min-h-16 items-center justify-center gap-3 rounded-2xl bg-[#F4F2FA] p-4 text-center">
                    <ScanLine
                        aria-hidden="true"
                        className="size-5 shrink-0 text-[#5D1B68]"
                    />
                    <p
                        className="text-sm font-semibold text-[#160F42]"
                        role="status"
                        aria-live="polite"
                    >
                        {message}
                    </p>
                </div>
            </section>
        </main>
    );
}
