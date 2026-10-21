import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import ModalShell from '@/components/ui/modal-shell';

type ConfirmationModalProps = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    processing?: boolean;
    onCloseAutoFocus?: (event: Event) => void;
    onConfirm: () => void;
    onCancel: () => void;
};

export default function ConfirmationModal({
    open,
    title,
    description,
    confirmLabel = 'Konfirmasi',
    cancelLabel = 'Batal',
    processing = false,
    onCloseAutoFocus,
    onConfirm,
    onCancel,
}: ConfirmationModalProps) {
    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen && !processing) {
                    onCancel();
                }
            }}
        >
            <ModalShell
                onCloseAutoFocus={onCloseAutoFocus}
                showCloseButton={!processing}
                onEscapeKeyDown={(event) => {
                    if (processing) {
                        event.preventDefault();
                    }
                }}
                onPointerDownOutside={(event) => {
                    if (processing) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={processing}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        {processing ? 'Memproses...' : confirmLabel}
                    </Button>
                </DialogFooter>
            </ModalShell>
        </Dialog>
    );
}
