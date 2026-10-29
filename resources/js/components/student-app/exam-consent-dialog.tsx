import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import ModalShell from '@/components/ui/modal-shell';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    subject: string;
    href: string | null;
    children: ReactNode;
};

export default function ExamConsentDialog({
    open,
    onOpenChange,
    title,
    subject,
    href,
    children,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <ModalShell>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <div className="space-y-3 text-sm">
                    <DialogDescription>{subject}</DialogDescription>
                    {children}
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    {href !== null && (
                        <Button asChild>
                            <Link href={href}>Mulai</Link>
                        </Button>
                    )}
                </DialogFooter>
            </ModalShell>
        </Dialog>
    );
}
