import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import { update } from '@/routes/user-password';

export default function ChangePasswordModal({
    onClose,
    onAfterClose,
}: {
    onClose: () => void;
    onAfterClose: () => void;
}) {
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const passwordInput = useRef<HTMLInputElement>(null);
    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const close = () => {
        if (!form.processing) {
            form.resetAndClearErrors();
            onClose();
        }
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        event.stopPropagation();

        if (form.processing) {
            return;
        }

        form.put(update.url(), {
            preserveScroll: true,
            errorBag: 'changePassword',
            onSuccess: () => {
                form.resetAndClearErrors();
                onClose();
            },
            onError: (errors) => {
                form.reset();

                if (errors.current_password) {
                    currentPasswordInput.current?.focus();
                } else {
                    passwordInput.current?.focus();
                }
            },
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <ModalShell
                size="small"
                showCloseButton={!form.processing}
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    currentPasswordInput.current?.focus();
                }}
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    onAfterClose();
                }}
                onEscapeKeyDown={(event) => {
                    if (form.processing) {
                        event.preventDefault();
                    }
                }}
                onInteractOutside={(event) => {
                    if (form.processing) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>Ubah Password</DialogTitle>
                    <DialogDescription>
                        Masukkan password saat ini dan konfirmasi password baru
                        untuk mengamankan akun Anda.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="change-current-password">
                            Password saat ini
                        </Label>
                        <PasswordInput
                            id="change-current-password"
                            ref={currentPasswordInput}
                            name="current_password"
                            autoComplete="current-password"
                            value={form.data.current_password}
                            onChange={(event) =>
                                form.setData(
                                    'current_password',
                                    event.target.value,
                                )
                            }
                            required
                            disabled={form.processing}
                            aria-invalid={Boolean(form.errors.current_password)}
                            aria-describedby={
                                form.errors.current_password
                                    ? 'change-current-password-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="change-current-password-error"
                            message={form.errors.current_password}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="change-new-password">
                            Password baru
                        </Label>
                        <PasswordInput
                            id="change-new-password"
                            ref={passwordInput}
                            name="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            required
                            disabled={form.processing}
                            aria-invalid={Boolean(form.errors.password)}
                            aria-describedby={
                                form.errors.password
                                    ? 'change-new-password-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="change-new-password-error"
                            message={form.errors.password}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="change-password-confirmation">
                            Konfirmasi password baru
                        </Label>
                        <PasswordInput
                            id="change-password-confirmation"
                            name="password_confirmation"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            required
                            disabled={form.processing}
                            aria-invalid={Boolean(
                                form.errors.password_confirmation,
                            )}
                            aria-describedby={
                                form.errors.password_confirmation
                                    ? 'change-password-confirmation-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="change-password-confirmation-error"
                            message={form.errors.password_confirmation}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={close}
                            disabled={form.processing}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan Password'}
                        </Button>
                    </DialogFooter>
                </form>
            </ModalShell>
        </Dialog>
    );
}
