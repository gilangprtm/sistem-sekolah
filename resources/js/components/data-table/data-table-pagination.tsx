import { ChevronsLeft, ChevronsRight } from 'lucide-react';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type PaginatedResource = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type DataTablePaginationProps = {
    resource: PaginatedResource;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    perPageOptions?: number[];
    noun?: string;
};

function getPageNumbers(currentPage: number, pageCount: number) {
    if (pageCount <= 3) {
        return Array.from({ length: pageCount }, (_, index) => index + 1);
    }

    if (currentPage <= 2) {
        return [1, 2, 3];
    }

    if (currentPage >= pageCount - 1) {
        return [pageCount - 2, pageCount - 1, pageCount];
    }

    return [currentPage - 1, currentPage, currentPage + 1];
}

export default function DataTablePagination({
    resource,
    onPageChange,
    onPerPageChange,
    perPageOptions = [10, 20, 30, 40, 50],
    noun = 'baris',
}: DataTablePaginationProps) {
    const pageNumbers = getPageNumbers(
        resource.current_page,
        resource.last_page,
    );
    const isFirstPage = resource.current_page === 1;
    const isLastPage = resource.current_page === resource.last_page;

    return (
        <div className="flex flex-col gap-3 border-t px-4 py-4 md:flex-row md:items-center md:justify-between">
            <div className="text-sm text-muted-foreground">
                {resource.total} {noun}
            </div>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-6">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-muted-foreground">
                        Baris per halaman
                    </span>
                    <Select
                        value={`${resource.per_page}`}
                        onValueChange={(value) =>
                            onPerPageChange(Number(value))
                        }
                    >
                        <SelectTrigger className="h-8 w-18">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {perPageOptions.map((size) => (
                                <SelectItem key={size} value={`${size}`}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="text-sm font-medium text-muted-foreground">
                    Halaman {resource.current_page} dari {resource.last_page}
                </div>
                {resource.last_page > 1 && (
                    <Pagination className="mx-0 w-auto justify-start sm:justify-end">
                        <PaginationContent className="gap-1">
                            <PaginationItem className="hidden lg:block">
                                <PaginationLink
                                    href="#"
                                    aria-label="Halaman pertama"
                                    aria-disabled={isFirstPage}
                                    className={
                                        isFirstPage
                                            ? 'pointer-events-none opacity-50'
                                            : undefined
                                    }
                                    onClick={(event) => {
                                        event.preventDefault();

                                        if (!isFirstPage) {
                                            onPageChange(1);
                                        }
                                    }}
                                >
                                    <ChevronsLeft />
                                </PaginationLink>
                            </PaginationItem>
                            <PaginationItem>
                                <PaginationPrevious
                                    href="#"
                                    text="Prev"
                                    aria-disabled={isFirstPage}
                                    className={
                                        isFirstPage
                                            ? 'pointer-events-none opacity-50'
                                            : undefined
                                    }
                                    onClick={(event) => {
                                        event.preventDefault();

                                        if (!isFirstPage) {
                                            onPageChange(
                                                resource.current_page - 1,
                                            );
                                        }
                                    }}
                                />
                            </PaginationItem>
                            {pageNumbers[0] > 1 && (
                                <PaginationItem>
                                    <PaginationEllipsis />
                                </PaginationItem>
                            )}
                            {pageNumbers.map((page) => (
                                <PaginationItem key={page}>
                                    <PaginationLink
                                        href="#"
                                        isActive={
                                            page === resource.current_page
                                        }
                                        onClick={(event) => {
                                            event.preventDefault();
                                            onPageChange(page);
                                        }}
                                    >
                                        {page}
                                    </PaginationLink>
                                </PaginationItem>
                            ))}
                            {pageNumbers[pageNumbers.length - 1] <
                                resource.last_page && (
                                <PaginationItem>
                                    <PaginationEllipsis />
                                </PaginationItem>
                            )}
                            <PaginationItem>
                                <PaginationNext
                                    href="#"
                                    text="Next"
                                    aria-disabled={isLastPage}
                                    className={
                                        isLastPage
                                            ? 'pointer-events-none opacity-50'
                                            : undefined
                                    }
                                    onClick={(event) => {
                                        event.preventDefault();

                                        if (!isLastPage) {
                                            onPageChange(
                                                resource.current_page + 1,
                                            );
                                        }
                                    }}
                                />
                            </PaginationItem>
                            <PaginationItem className="hidden lg:block">
                                <PaginationLink
                                    href="#"
                                    aria-label="Halaman terakhir"
                                    aria-disabled={isLastPage}
                                    className={
                                        isLastPage
                                            ? 'pointer-events-none opacity-50'
                                            : undefined
                                    }
                                    onClick={(event) => {
                                        event.preventDefault();

                                        if (!isLastPage) {
                                            onPageChange(resource.last_page);
                                        }
                                    }}
                                >
                                    <ChevronsRight />
                                </PaginationLink>
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                )}
            </div>
        </div>
    );
}
