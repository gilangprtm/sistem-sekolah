import { Head, router } from '@inertiajs/react';
import { ShoppingBasket } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';

type Props = {
    has_kantin_pin: boolean;
};

export default function StudentKantin({ has_kantin_pin }: Props) {
    const [pinOpen, setPinOpen] = useState(!has_kantin_pin);
    const [pin, setPin] = useState('');
    const [pinConfirmation, setPinConfirmation] = useState('');
    const [currentPassword, setCurrentPassword] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

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
                aria-labelledby="student-kantin-heading"
            >
                <div className="flex items-center gap-3">
                    <div className="grid size-11 place-items-center rounded-2xl bg-pink-100 text-pink-700">
                        <ShoppingBasket aria-hidden="true" />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Portal Siswa
                        </p>
                        <h1
                            id="student-kantin-heading"
                            className="text-2xl font-semibold"
                        >
                            Kantin
                        </h1>
                    </div>
                </div>
                {has_kantin_pin ? (
                    <div className="rounded-2xl border bg-card p-4 text-sm">
                        PIN Kantin sudah aktif. Gunakan PIN tersebut saat
                        bertransaksi di POS Kantin.
                    </div>
                ) : (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        Buat PIN terlebih dahulu agar dapat menggunakan
                        transaksi Kantin.
                    </div>
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
