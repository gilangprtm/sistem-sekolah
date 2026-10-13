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

export function canonicalizeRupiahInput(value: string): string | null {
    const trimmed = value.trim();

    if (!trimmed || !/^\d[\d.,]*$/.test(trimmed)) {
        return null;
    }

    if (/^\d+$/.test(trimmed)) {
        return `${trimmed}.00`;
    }

    const groupedDecimal = /^(\d{1,3}(?:\.\d{3})+),(\d{1,2})$/;
    const groupedMatch = groupedDecimal.exec(trimmed);

    if (groupedMatch) {
        return `${groupedMatch[1].replace(/\./g, '')}.${groupedMatch[2].padEnd(2, '0')}`;
    }

    if (/^\d{1,3}(?:\.\d{3})+$/.test(trimmed)) {
        return `${trimmed.replace(/\./g, '')}.00`;
    }

    const decimalMatch = /^(\d+)[,.](\d{1,2})$/.exec(trimmed);

    if (decimalMatch) {
        return `${decimalMatch[1]}.${decimalMatch[2].padEnd(2, '0')}`;
    }

    return null;
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
    const rawValue = String(value).trim();
    const match = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(rawValue);

    if (!match) {
        return `Rp ${rawValue}`;
    }

    const [, sign, integerPart, fraction = ''] = match;
    const groupedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const formattedFraction = fraction.padEnd(2, '0');

    return `${sign ? '-' : ''}Rp ${groupedInteger},${formattedFraction}`;
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
