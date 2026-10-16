import { forwardRef, useEffect, useRef, useState } from 'react';

export type StudentCardStudent = {
    id?: number;
    nis: string | null;
    nisn: string | null;
    full_name: string;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    photo_url: string | null;
};

type Props = {
    student: StudentCardStudent;
    qrCode: string | null;
    barcodeValue?: string;
    className?: string;
};

const CARD_BACKGROUND = '/images/base_kartupelajar.png';
const MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

function formatBirthDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;
    const monthName = MONTHS[Number(month) - 1];

    return monthName ? `${day} ${monthName} ${year}` : value;
}

function formatStudentNumbers(nis: string | null, nisn: string | null): string {
    return `${nis?.trim() || '-'} / ${nisn?.trim() || '-'}`;
}

function initialsForStudent(name: string): string {
    return name
        .split(/\s+/)
        .map((part) => part[0])
        .filter(Boolean)
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function barcodePattern(value: string): boolean[] {
    const pattern = [true, false, true, false, true, true, false];

    for (const character of value || '-') {
        const code = character.charCodeAt(0);

        for (let bit = 6; bit >= 0; bit -= 1) {
            pattern.push(((code >> bit) & 1) === 1);
        }

        pattern.push(false);
    }

    pattern.push(true, false, true);

    return pattern;
}

function Barcode({ value, qrCode }: { value: string; qrCode: string | null }) {
    if (qrCode !== null) {
        return (
            <img
                src={qrCode}
                alt="QR Code akun siswa"
                className="size-full object-contain"
            />
        );
    }

    return (
        <div className="flex h-full w-full" aria-label={`Barcode ${value}`}>
            {barcodePattern(value).map((isBar, index) => (
                <span
                    key={`${value}-${index}`}
                    className={isBar ? 'bg-[#024059]' : 'bg-transparent'}
                    style={{ flex: '1 1 0%' }}
                />
            ))}
        </div>
    );
}

export const StudentCardVisual = forwardRef<HTMLElement, Props>(
    function StudentCardVisual(
        { student, qrCode, barcodeValue, className = '' },
        ref,
    ) {
        const wrapperRef = useRef<HTMLDivElement>(null);
        const [scale, setScale] = useState<number | null>(null);
        const [loadedAssetKey, setLoadedAssetKey] = useState<string | null>(
            null,
        );

        useEffect(() => {
            const wrapper = wrapperRef.current;

            if (!wrapper) {
                return;
            }

            const observer = new ResizeObserver(([entry]) => {
                if (entry) {
                    setScale(entry.contentRect.width / 1011);
                }
            });

            observer.observe(wrapper);

            return () => observer.disconnect();
        }, []);

        useEffect(() => {
            let active = true;
            const sources = [CARD_BACKGROUND, student.photo_url, qrCode].filter(
                (source): source is string => Boolean(source),
            );
            const assetKey = JSON.stringify(sources);
            Promise.all(
                sources.map(
                    (source) =>
                        new Promise<void>((resolve) => {
                            const image = new Image();
                            image.onload = () => resolve();
                            image.onerror = () => resolve();
                            image.src = source;

                            if (image.complete) {
                                resolve();
                            }
                        }),
                ),
            ).then(() => {
                if (active) {
                    setLoadedAssetKey(assetKey);
                }
            });

            return () => {
                active = false;
            };
        }, [student.photo_url, qrCode]);

        const currentAssetKey = JSON.stringify(
            [CARD_BACKGROUND, student.photo_url, qrCode].filter(
                (source): source is string => Boolean(source),
            ),
        );
        const ready = scale !== null && loadedAssetKey === currentAssetKey;
        const birthDetails = [
            student.birth_place,
            formatBirthDate(student.birth_date),
        ]
            .filter(Boolean)
            .join(', ');
        const codeValue =
            barcodeValue ?? student.nis ?? student.nisn ?? String(student.id);

        return (
            <div
                ref={wrapperRef}
                className={`relative aspect-[1011/639] w-full ${className}`}
            >
                {!ready && (
                    <div
                        className="absolute inset-0 animate-pulse rounded-[2.5%] bg-slate-200/20"
                        role="status"
                        aria-label="Memuat kartu pelajar"
                    />
                )}
                <article
                    ref={ref}
                    className="absolute top-0 left-0 overflow-hidden rounded-[25px] border border-slate-300 bg-[#024059] bg-cover bg-center bg-no-repeat text-left shadow-sm"
                    style={{
                        backgroundImage: `url(${CARD_BACKGROUND})`,
                        width: 1011,
                        height: 639,
                        transform: `scale(${scale ?? 1})`,
                        visibility: ready ? 'visible' : 'hidden',
                        transformOrigin: 'top left',
                    }}
                    aria-label={`Kartu Pelajar ${student.full_name}`}
                >
                    <h1 className="sr-only">Kartu Pelajar Siswa</h1>
                    <div
                        className="absolute overflow-hidden rounded-[10%]"
                        style={{
                            left: '4.25%',
                            top: '31.61%',
                            width: '24.63%',
                            height: '52.74%',
                        }}
                    >
                        {student.photo_url !== null ? (
                            <img
                                src={student.photo_url}
                                alt={`Foto ${student.full_name}`}
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center bg-[#d7d8df] text-[48px] font-bold text-[#53627a]">
                                {initialsForStudent(student.full_name)}
                            </div>
                        )}
                    </div>
                    <div className="absolute top-[31.92%] left-[32.54%] max-w-[64%] truncate text-[48px] leading-none font-bold text-[#024059]">
                        KARTU PELAJAR SISWA
                    </div>
                    <div className="absolute top-[44.3%] right-[3.5%] left-[32.54%] text-[25px] leading-[1.45] font-bold text-[#024059]">
                        <div className="flex items-center text-left">
                            <span className="w-[21.5%] shrink-0 whitespace-nowrap">
                                Nama
                            </span>
                            <span className="w-[1.9%] shrink-0">:</span>
                            <span className="min-w-0 truncate">
                                {student.full_name}
                            </span>
                        </div>
                        <div className="flex items-center text-left">
                            <span className="w-[21.5%] shrink-0 whitespace-nowrap">
                                NIS/NISN
                            </span>
                            <span className="w-[1.9%] shrink-0">:</span>
                            <span className="min-w-0 truncate">
                                {formatStudentNumbers(
                                    student.nis,
                                    student.nisn,
                                )}
                            </span>
                        </div>
                        <div className="flex items-center text-left">
                            <span className="w-[21.5%] shrink-0 whitespace-nowrap">
                                J. Kelamin
                            </span>
                            <span className="w-[1.9%] shrink-0">:</span>
                            <span className="min-w-0 truncate">
                                {student.gender === 'L'
                                    ? 'Laki-laki'
                                    : student.gender === 'P'
                                      ? 'Perempuan'
                                      : '-'}
                            </span>
                        </div>
                        <div className="flex items-center text-left">
                            <span className="w-[21.5%] shrink-0 whitespace-nowrap">
                                T.T.L
                            </span>
                            <span className="w-[1.9%] shrink-0">:</span>
                            <span className="min-w-0 truncate">
                                {birthDetails || '-'}
                            </span>
                        </div>
                        <div className="flex items-center text-left">
                            <span className="w-[21.5%] shrink-0 whitespace-nowrap">
                                Alamat
                            </span>
                            <span className="w-[1.9%] shrink-0">:</span>
                            <span className="min-w-0 truncate">
                                {student.address || '-'}
                            </span>
                        </div>
                    </div>
                    <div className="absolute right-[6%] bottom-[10%] aspect-square w-[13%]">
                        <Barcode value={codeValue} qrCode={qrCode} />
                    </div>
                </article>
            </div>
        );
    },
);
