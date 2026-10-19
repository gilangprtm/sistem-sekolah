import type { ReactNode } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableRowActions from '@/components/data-table/data-table-row-actions';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

type PaginatedResource = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type DataTableColumn<Row> = {
    id: string;
    header: ReactNode;
    cell: (row: Row, index: number) => ReactNode;
    headerClassName?: string;
    cellClassName?: string;
};

export type DataTableSelection<Row, RowKey extends string | number> = {
    selectedKeys: RowKey[];
    onSelectionChange: (keys: RowKey[]) => void;
    selectAllLabel: string;
    getRowLabel: (row: Row) => string;
    selectedCountLabel?: (count: number, total: number) => ReactNode;
    clearSelectionLabel?: string;
};

export type DataTableRowAction<Row> = {
    label: (row: Row) => string;
    render: (row: Row) => ReactNode;
};

type DataTablePaginationOptions = {
    resource: PaginatedResource;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    noun?: string;
    perPageOptions?: number[];
};

type GenericDataTableShellProps<Row, RowKey extends string | number> = {
    data: Row[];
    columns: DataTableColumn<Row>[];
    getRowKey: (row: Row) => RowKey;
    emptyState: ReactNode;
    toolbar?: ReactNode;
    selection?: DataTableSelection<Row, RowKey>;
    rowActions?: DataTableRowAction<Row>;
    pagination?: DataTablePaginationOptions;
    tableClassName?: string;
    className?: string;
};

type LegacyDataTableShellProps = {
    children: ReactNode;
    className?: string;
};

export type DataTableShellProps<Row, RowKey extends string | number> =
    GenericDataTableShellProps<Row, RowKey> | LegacyDataTableShellProps;

export default function DataTableShell<
    Row,
    RowKey extends string | number = string | number,
>(props: DataTableShellProps<Row, RowKey>) {
    if ('children' in props) {
        return (
            <div
                className={cn(
                    'overflow-hidden rounded-xl border border-border/70 bg-background',
                    props.className,
                )}
            >
                {props.children}
            </div>
        );
    }

    const {
        data,
        columns,
        getRowKey,
        emptyState,
        toolbar,
        selection,
        rowActions,
        pagination,
        tableClassName,
        className,
    } = props;
    const hasSelection = selection !== undefined;
    const allSelected = Boolean(
        hasSelection &&
        data.length > 0 &&
        data.every((row) => selection.selectedKeys.includes(getRowKey(row))),
    );
    const selectedCount = hasSelection
        ? data.filter((row) => selection.selectedKeys.includes(getRowKey(row)))
              .length
        : 0;
    const totalRows = pagination?.resource.total ?? data.length;
    const columnCount =
        columns.length +
        Number(hasSelection) +
        Number(rowActions !== undefined);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border/70 bg-background',
                className,
            )}
        >
            {(toolbar !== undefined || hasSelection) && (
                <DataTableToolbar>
                    {toolbar ?? (
                        <div className="text-sm text-muted-foreground">
                            {selection?.selectedCountLabel
                                ? selection.selectedCountLabel(
                                      selectedCount,
                                      totalRows,
                                  )
                                : `${selectedCount} dipilih.`}
                        </div>
                    )}
                    {selection && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => selection.onSelectionChange([])}
                            disabled={selectedCount === 0}
                        >
                            {selection.clearSelectionLabel ?? 'Hapus pilihan'}
                        </Button>
                    )}
                </DataTableToolbar>
            )}
            <div className="overflow-x-auto">
                <Table className={tableClassName}>
                    <TableHeader>
                        <TableRow>
                            {selection && (
                                <TableHead className="w-12">
                                    <Checkbox
                                        checked={allSelected}
                                        onCheckedChange={(checked) =>
                                            selection.onSelectionChange(
                                                checked
                                                    ? data.map(getRowKey)
                                                    : [],
                                            )
                                        }
                                        aria-label={selection.selectAllLabel}
                                    />
                                </TableHead>
                            )}
                            {columns.map((column) => (
                                <TableHead
                                    key={column.id}
                                    className={column.headerClassName}
                                >
                                    {column.header}
                                </TableHead>
                            ))}
                            {rowActions && (
                                <TableHead className="text-right">
                                    Aksi
                                </TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {data.map((row, index) => {
                            const key = getRowKey(row);

                            return (
                                <TableRow key={key}>
                                    {selection && (
                                        <TableCell className="w-12">
                                            <Checkbox
                                                checked={selection.selectedKeys.includes(
                                                    key,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    selection.onSelectionChange(
                                                        checked
                                                            ? [
                                                                  ...selection.selectedKeys,
                                                                  key,
                                                              ]
                                                            : selection.selectedKeys.filter(
                                                                  (
                                                                      selectedKey,
                                                                  ) =>
                                                                      selectedKey !==
                                                                      key,
                                                              ),
                                                    )
                                                }
                                                aria-label={selection.getRowLabel(
                                                    row,
                                                )}
                                            />
                                        </TableCell>
                                    )}
                                    {columns.map((column) => (
                                        <TableCell
                                            key={column.id}
                                            className={column.cellClassName}
                                        >
                                            {column.cell(row, index)}
                                        </TableCell>
                                    ))}
                                    {rowActions && (
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={rowActions.label(row)}
                                            >
                                                {rowActions.render(row)}
                                            </DataTableRowActions>
                                        </TableCell>
                                    )}
                                </TableRow>
                            );
                        })}
                        {data.length === 0 && (
                            <DataTableEmptyState colSpan={columnCount}>
                                {emptyState}
                            </DataTableEmptyState>
                        )}
                    </TableBody>
                </Table>
            </div>
            {pagination && <DataTablePagination {...pagination} />}
        </div>
    );
}
