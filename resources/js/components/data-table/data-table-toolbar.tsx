import type { ReactNode } from 'react';

type DataTableToolbarProps = {
    children: ReactNode;
    className?: string;
};

export default function DataTableToolbar({
    children,
    className = '',
}: DataTableToolbarProps) {
    return (
        <div
            className={`flex flex-col gap-3 border-b px-4 py-4 md:flex-row md:items-center md:justify-between ${className}`}
        >
            {children}
        </div>
    );
}
