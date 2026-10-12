import { Head, InfiniteScroll, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { formatWitaDateTime } from '@/lib/date-time';

type Detail = {
    barang_name: string;
    quantity: number;
    harga: string;
    subtotal: string;
};
type Purchase = { id: number; date: string; total: string; details: Detail[] };
type TopUp = {
    id: number;
    date: string;
    amount: string;
    balance_after: string;
    balance_before?: string;
};
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};
type Props = {
    has_kantin_pin: boolean;
    balance: { saldo: string };
    purchases: Paginator<Purchase>;
    topups: Paginator<TopUp>;
};

const currency = (value: string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 2,
    }).format(Number(value));
function HistoryLoading() {
    return (
        <p className="border-t p-4 text-sm text-muted-foreground" role="status">
            Memuat riwayat...
        </p>
    );
}

export default function StudentKantin({
    has_kantin_pin,
    balance,
    purchases,
    topups,
}: Props) {
    const [pinOpen, setPinOpen] = useState(!has_kantin_pin);
    const [pin, setPin] = useState('');
    const [pinConfirmation, setPinConfirmation] = useState('');
    const [currentPassword, setCurrentPassword] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [activeTab, setActiveTab] = useState<'purchases' | 'topups'>(
        'purchases',
    );

    const submitPin = () => {
        setProcessing(true);
        setErrors({});
        router.put(
            '/student/profile/kantin-pin',
            {
                current_password: currentPassword,
                pin,
                pin_confirmation: pinConfirmation,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPinOpen(false);
                    setPin('');
                    setPinConfirmation('');
                    setCurrentPassword('');
                },
                onError: (validationErrors) => setErrors(validationErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title="Kantin" />
            <section
                className="space-y-5"
                aria-labelledby="student-kantin-balance-heading"
            >
                <div
                    className="rounded-2xl border bg-card p-5"
                    aria-labelledby="student-kantin-balance-heading"
                >
                    <p
                        id="student-kantin-balance-heading"
                        className="text-sm text-muted-foreground"
                    >
                        Saldo Kantin
                    </p>
                    <p className="mt-1 text-2xl font-bold text-violet-700">
                        {currency(balance.saldo)}
                    </p>
                </div>
                <div
                    className="grid w-full grid-cols-2 rounded-2xl bg-muted p-1"
                    role="tablist"
                    aria-label="Riwayat Kantin"
                >
                    <button
                        type="button"
                        role="tab"
                        id="purchases-tab"
                        aria-controls="purchases-panel"
                        aria-selected={activeTab === 'purchases'}
                        className="min-h-11 rounded-xl px-4 text-sm font-semibold data-[active=true]:bg-background data-[active=true]:text-violet-700 data-[active=true]:shadow-sm"
                        data-active={activeTab === 'purchases'}
                        onClick={() => setActiveTab('purchases')}
                    >
                        Riwayat Pembelian
                    </button>
                    <button
                        type="button"
                        role="tab"
                        id="topups-tab"
                        aria-controls="topups-panel"
                        aria-selected={activeTab === 'topups'}
                        className="min-h-11 rounded-xl px-4 text-sm font-semibold data-[active=true]:bg-background data-[active=true]:text-violet-700 data-[active=true]:shadow-sm"
                        data-active={activeTab === 'topups'}
                        onClick={() => setActiveTab('topups')}
                    >
                        Riwayat Top Up
                    </button>
                </div>
                {activeTab === 'purchases' ? (
                    <section
                        id="purchases-panel"
                        className="rounded-2xl border bg-card"
                        role="tabpanel"
                        aria-labelledby="purchases-tab"
                    >
                        <div className="border-b p-4">
                            <h2
                                id="purchases-heading"
                                className="font-semibold"
                            >
                                Riwayat Pembelian
                            </h2>
                        </div>
                        {purchases.data.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">
                                Belum ada riwayat pembelian.
                            </p>
                        ) : (
                            <InfiniteScroll
                                data="purchases"
                                onlyNext
                                loading={<HistoryLoading />}
                            >
                                {purchases.data.map((purchase) => (
                                    <article
                                        key={purchase.id}
                                        className="space-y-2 border-b p-4 text-sm last:border-b-0"
                                    >
                                        <div className="flex justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                {formatWitaDateTime(
                                                    purchase.date,
                                                )}
                                            </span>
                                            <strong>
                                                {currency(purchase.total)}
                                            </strong>
                                        </div>
                                        <ul className="space-y-1 text-muted-foreground">
                                            {purchase.details.map(
                                                (detail, index) => (
                                                    <li
                                                        key={`${purchase.id}-${index}`}
                                                        className="flex justify-between gap-3"
                                                    >
                                                        <span>
                                                            {detail.barang_name}{' '}
                                                            × {detail.quantity}
                                                        </span>
                                                        <span>
                                                            {currency(
                                                                detail.subtotal,
                                                            )}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </article>
                                ))}
                            </InfiniteScroll>
                        )}
                    </section>
                ) : (
                    <section
                        id="topups-panel"
                        className="rounded-2xl border bg-card"
                        role="tabpanel"
                        aria-labelledby="topups-tab"
                    >
                        <div className="border-b p-4">
                            <h2 id="topups-heading" className="font-semibold">
                                Riwayat Top Up
                            </h2>
                        </div>
                        {topups.data.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">
                                Belum ada riwayat top up.
                            </p>
                        ) : (
                            <InfiniteScroll
                                data="topups"
                                onlyNext
                                loading={<HistoryLoading />}
                            >
                                {topups.data.map((topup) => (
                                    <article
                                        key={topup.id}
                                        className="flex justify-between gap-3 border-b p-4 text-sm last:border-b-0"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {currency(topup.amount)}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {formatWitaDateTime(topup.date)}
                                            </p>
                                        </div>
                                        <p className="text-right text-muted-foreground">
                                            Saldo{' '}
                                            {currency(topup.balance_after)}
                                        </p>
                                    </article>
                                ))}
                            </InfiniteScroll>
                        )}
                    </section>
                )}
            </section>

            {pinOpen && (
                <div
                    className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="student-kantin-pin-title"
                >
                    <div className="w-full max-w-sm rounded-2xl bg-background p-5 shadow-xl">
                        <h2
                            id="student-kantin-pin-title"
                            className="text-lg font-semibold"
                        >
                            Buat PIN Kantin
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Masukkan password saat ini dan PIN 6 digit untuk
                            transaksi Kantin.
                        </p>
                        <div className="mt-4 grid gap-3">
                            <PasswordInput
                                value={currentPassword}
                                onChange={(event) =>
                                    setCurrentPassword(event.target.value)
                                }
                                placeholder="Password saat ini"
                                autoComplete="current-password"
                                aria-label="Password saat ini"
                            />
                            <InputError message={errors.current_password} />
                            <PasswordInput
                                value={pin}
                                onChange={(event) =>
                                    setPin(
                                        event.target.value
                                            .replace(/\D/g, '')
                                            .slice(0, 6),
                                    )
                                }
                                placeholder="PIN 6 digit"
                                inputMode="numeric"
                                autoComplete="new-password"
                                aria-label="PIN 6 digit"
                            />
                            <InputError message={errors.pin} />
                            <PasswordInput
                                value={pinConfirmation}
                                onChange={(event) =>
                                    setPinConfirmation(
                                        event.target.value
                                            .replace(/\D/g, '')
                                            .slice(0, 6),
                                    )
                                }
                                placeholder="Konfirmasi PIN"
                                inputMode="numeric"
                                autoComplete="new-password"
                                aria-label="Konfirmasi PIN"
                            />
                            <InputError message={errors.pin_confirmation} />
                        </div>
                        <div className="mt-5 flex justify-end gap-2">
                            {has_kantin_pin && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setPinOpen(false)}
                                    disabled={processing}
                                >
                                    Batal
                                </Button>
                            )}
                            <Button
                                type="button"
                                onClick={submitPin}
                                disabled={
                                    processing ||
                                    currentPassword.length === 0 ||
                                    pin.length !== 6 ||
                                    pinConfirmation.length !== 6
                                }
                            >
                                {processing ? 'Menyimpan...' : 'Simpan PIN'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
