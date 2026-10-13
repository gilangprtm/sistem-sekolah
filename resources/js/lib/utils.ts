import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatCurrencyInput(value: string, previousValue = ''): string {
    const trimmed = value.trim();

    if (!trimmed) {
        return '';
    }

    if (
        !/^\d[\d.,]*$/.test(trimmed) ||
        (trimmed.match(/,/g) ?? []).length > 1
    ) {
        return value;
    }

    const groupedInteger = /^\d{1,3}(?:\.\d{3})+$/;
    const plainInteger = /^\d+$/;
    const [wholePart, fraction] = trimmed.split(',');
    const appendedDigitToGroupedValue =
        groupedInteger.test(previousValue) &&
        value.startsWith(previousValue) &&
        /^\d$/.test(value.slice(previousValue.length));
    const validWhole =
        plainInteger.test(wholePart) ||
        groupedInteger.test(wholePart) ||
        appendedDigitToGroupedValue;

    if (
        !validWhole ||
        (fraction !== undefined &&
            (fraction.length > 2 || !/^\d*$/.test(fraction)))
    ) {
        return value;
    }

    const integer = wholePart.replace(/\./g, '');
    const normalizedInteger = integer.replace(/^0+(?=\d)/, '');
    const groupedWhole = new Intl.NumberFormat('id-ID').format(
        Number(normalizedInteger),
    );

    return fraction === undefined
        ? groupedWhole
        : `${groupedWhole},${fraction}`;
}

export function formatRupiah(value: number | string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

export function formatCurrency(
    amount: number,
    opts?: {
        currency?: string;
        locale?: string;
        minimumFractionDigits?: number;
        maximumFractionDigits?: number;
        noDecimals?: boolean;
    },
) {
    const {
        currency = 'USD',
        locale = 'en-US',
        minimumFractionDigits,
        maximumFractionDigits,
        noDecimals,
    } = opts ?? {};

    const formatOptions: Intl.NumberFormatOptions = {
        style: 'currency',
        currency,
        minimumFractionDigits: noDecimals ? 0 : minimumFractionDigits,
        maximumFractionDigits: noDecimals ? 0 : maximumFractionDigits,
    };

    return new Intl.NumberFormat(locale, formatOptions).format(amount);
}

export const getInitials = (str: string): string => {
    if (typeof str !== 'string' || !str.trim()) {
        return '?';
    }

    return (
        str
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .map((word) => word[0])
            .join('')
            .toUpperCase() || '?'
    );
};
