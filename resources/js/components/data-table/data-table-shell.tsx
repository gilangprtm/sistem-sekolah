import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type DataTableShellProps = {
    children: ReactNode;
    className?: string;
};

export default function DataTableShell({
    children,
    className,
}: DataTableShellProps) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border/70 bg-background',
                className,
            )}
        >
            {children}
        </div>
    );
}
