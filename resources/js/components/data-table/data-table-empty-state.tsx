import type { ReactNode } from 'react';
import { TableCell, TableRow } from '@/components/ui/table';

type DataTableEmptyStateProps = {
    colSpan: number;
    children: ReactNode;
    icon?: ReactNode;
};

export default function DataTableEmptyState({
    colSpan,
    children,
    icon,
}: DataTableEmptyStateProps) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className="text-center text-muted-foreground"
            >
                <div className="flex flex-col items-center gap-2 py-8">
                    {icon}
                    {children}
                </div>
            </TableCell>
        </TableRow>
    );
}
