const WITA_TIME_ZONE = 'Asia/Makassar';
const ISO_INSTANT_PATTERN =
    /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})$/;

const parseInstant = (value: string): Date | null => {
    if (!ISO_INSTANT_PATTERN.test(value.trim())) {
        return null;
    }

    const timestamp = Date.parse(value);

    return Number.isNaN(timestamp) ? null : new Date(timestamp);
};

const formatInstant = (
    value: string,
    options: Intl.DateTimeFormatOptions,
): string => {
    const instant = parseInstant(value);

    return instant === null
        ? '-'
        : new Intl.DateTimeFormat('id-ID', {
              ...options,
              timeZone: WITA_TIME_ZONE,
          }).format(instant);
};

export const formatWitaDate = (value: string): string =>
    formatInstant(value, {
        dateStyle: 'long',
    });

export const formatWitaDateTime = (value: string): string =>
    formatInstant(value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
