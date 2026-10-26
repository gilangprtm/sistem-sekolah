import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import { Textarea } from '@/components/ui/textarea';
import { store as sendAspiration } from '@/routes/student-app/silaras';

type Props = {
    trigger: ReactNode;
    triggerClassName: string;
};

export default function SilarasAspirationDialog({
    trigger,
    triggerClassName,
}: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({ content: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.post(sendAspiration.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={triggerClassName}
            >
                {trigger}
            </button>
            <Dialog
                open={open}
                onOpenChange={(nextOpen) => {
                    if (form.processing) {
                        return;
                    }

                    setOpen(nextOpen);

                    if (!nextOpen) {
                        form.clearErrors();
                    }
                }}
            >
                <ModalShell size="medium" aria-describedby={undefined}>
                    <DialogHeader>
                        <DialogTitle>Kirim Aspirasi</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-5">
                        <div className="space-y-2">
                            <Label htmlFor="silaras-content">Aspirasi</Label>
                            <Textarea
                                id="silaras-content"
                                value={form.data.content}
                                onChange={(event) =>
                                    form.setData('content', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.content)}
                                aria-describedby={
                                    form.errors.content
                                        ? 'silaras-content-error'
                                        : undefined
                                }
                                maxLength={5000}
                                required
                                rows={6}
                                disabled={form.processing}
                            />
                            <InputError
                                id="silaras-content-error"
                                message={form.errors.content}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Mengirim…' : 'Kirim'}
                            </Button>
                        </DialogFooter>
                    </form>
                </ModalShell>
            </Dialog>
        </>
    );
}
