import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    title: string;
    description?: string;
    backHref: string;
    backLabel?: string;
    actions?: ReactNode;
    footerActions?: ReactNode;
    children: ReactNode;
};

export default function SecondaryPageShell({
    title,
    description,
    backHref,
    backLabel = 'Kembali',
    actions,
    footerActions,
    children,
}: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-center gap-3">
                    <Button asChild variant="outline" size="icon">
                        <Link href={backHref} aria-label={backLabel}>
                            <ArrowLeft aria-hidden="true" />
                        </Link>
                    </Button>
                    <div className="min-w-0">
                        <h1 className="text-base font-medium wrap-anywhere">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {actions && (
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:justify-end [&>*]:w-full [&>*]:sm:w-auto">
                        {actions}
                    </div>
                )}
            </header>
            {children}
            {footerActions && (
                <footer className="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-end [&>*]:w-full [&>*]:sm:w-auto">
                    {footerActions}
                </footer>
            )}
        </div>
    );
}
