function copyComputedStyles(source: HTMLElement, target: HTMLElement): void {
    const style = window.getComputedStyle(source);

    for (const property of [
        'box-sizing',
        'width',
        'min-width',
        'height',
        'min-height',
        'padding',
        'border',
        'border-collapse',
        'border-spacing',
        'font-family',
        'font-size',
        'font-weight',
        'line-height',
        'color',
        'background-color',
        'text-align',
        'vertical-align',
        'white-space',
    ]) {
        target.style.setProperty(property, style.getPropertyValue(property));
    }

    if (target.tagName === 'TH' || target.tagName === 'TD') {
        target.style.position = 'static';
        target.style.left = 'auto';
        target.style.transform = 'none';
        target.style.height = `${source.getBoundingClientRect().height}px`;
    }

    const sourceChildren = Array.from(source.children);
    const targetChildren = Array.from(target.children);

    sourceChildren.forEach((child, index) => {
        const targetChild = targetChildren[index];

        if (
            child instanceof HTMLElement &&
            targetChild instanceof HTMLElement
        ) {
            copyComputedStyles(child, targetChild);
        }
    });
}

function canvasBlob(
    canvas: HTMLCanvasElement,
    mimeType: string,
    quality?: number,
): Promise<Blob> {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) =>
                blob
                    ? resolve(blob)
                    : reject(
                          new Error('Jadwal gagal diekspor sebagai gambar.'),
                      ),
            mimeType,
            quality,
        );
    });
}

export async function renderScheduleImage(
    table: HTMLTableElement,
    title: string,
): Promise<Blob> {
    const host = document.createElement('div');
    host.style.cssText =
        'position:fixed;left:-100000px;top:0;pointer-events:none;';
    const clone = table.cloneNode(true) as HTMLTableElement;
    copyComputedStyles(table, clone);
    clone.style.width = `${table.scrollWidth}px`;
    clone.style.maxWidth = 'none';
    clone.style.minWidth = `${table.scrollWidth}px`;
    clone.querySelector('caption')?.remove();
    host.appendChild(clone);
    document.body.appendChild(host);

    try {
        await document.fonts.ready;
        const width = Math.ceil(clone.getBoundingClientRect().width);
        const height = Math.ceil(clone.getBoundingClientRect().height);

        if (width === 0 || height === 0) {
            throw new Error('Tabel jadwal kosong.');
        }

        const padding = 32;
        const headingHeight = 58;
        const scale = Math.min(
            2,
            8192 /
                Math.max(
                    width + padding * 2,
                    height + headingHeight + padding * 2,
                ),
            Math.sqrt(
                32_000_000 /
                    ((width + padding * 2) *
                        (height + headingHeight + padding * 2)),
            ),
        );
        const canvas = document.createElement('canvas');
        canvas.width = Math.ceil((width + padding * 2) * scale);
        canvas.height = Math.ceil(
            (height + headingHeight + padding * 2) * scale,
        );
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Canvas tidak tersedia pada browser ini.');
        }

        context.scale(scale, scale);
        context.fillStyle = '#ffffff';
        context.fillRect(
            0,
            0,
            width + padding * 2,
            height + headingHeight + padding * 2,
        );
        context.fillStyle = '#111827';
        context.font = '600 20px system-ui, sans-serif';
        context.textBaseline = 'middle';
        context.fillText(title, padding, padding + 14, width);
        context.fillStyle = '#4b5563';
        context.font = '13px system-ui, sans-serif';
        context.fillText('Jadwal Pelajaran', padding, padding + 39);

        const tableRect = clone.getBoundingClientRect();

        for (const row of Array.from(clone.rows)) {
            for (const cell of Array.from(row.cells)) {
                const rect = cell.getBoundingClientRect();
                const x = rect.left - tableRect.left + padding;
                const y = rect.top - tableRect.top + headingHeight + padding;

                if (row.hasAttribute('data-schedule-day-divider')) {
                    context.fillStyle = '#000000';
                    context.fillRect(x, y, rect.width, rect.height);

                    continue;
                }

                const computed = window.getComputedStyle(cell);
                context.fillStyle =
                    cell.tagName === 'TH' ? '#f3f4f6' : '#ffffff';
                context.fillRect(x, y, rect.width, rect.height);
                context.strokeStyle = '#d1d5db';
                context.lineWidth = 1;
                context.strokeRect(x, y, rect.width, rect.height);

                const content = cell.querySelector('button');
                const text = (
                    content?.textContent ??
                    cell.textContent ??
                    ''
                ).trim();
                const contentRect = content?.getBoundingClientRect();
                const contentX = contentRect
                    ? contentRect.left - tableRect.left + padding
                    : x;
                const contentY = contentRect
                    ? contentRect.top - tableRect.top + headingHeight + padding
                    : y;
                const contentWidth = contentRect?.width ?? rect.width;
                const contentHeight = contentRect?.height ?? rect.height;
                const background = content
                    ? window.getComputedStyle(content).backgroundColor
                    : '';
                const hasChip =
                    text !== '' &&
                    background !== '' &&
                    background !== 'transparent' &&
                    background !== 'rgba(0, 0, 0, 0)';

                if (hasChip) {
                    context.fillStyle = background;
                    context.beginPath();
                    context.roundRect(
                        contentX,
                        contentY,
                        contentWidth,
                        contentHeight,
                        5,
                    );
                    context.fill();

                    const rgb = background.match(/[\d.]+/g)?.map(Number) ?? [];
                    const luminance =
                        rgb.length >= 3
                            ? rgb[0] * 0.299 + rgb[1] * 0.587 + rgb[2] * 0.114
                            : 0;
                    context.fillStyle = luminance > 150 ? '#111827' : '#ffffff';
                } else {
                    context.fillStyle = '#111827';
                }

                const fontWeight =
                    computed.fontWeight === '700' ? '600' : '400';
                const fontSize = Math.max(
                    11,
                    Number.parseFloat(computed.fontSize) || 14,
                );
                context.font = `${fontWeight} ${fontSize}px ${computed.fontFamily || 'system-ui, sans-serif'}`;
                context.textAlign =
                    content || computed.textAlign === 'center'
                        ? 'center'
                        : 'left';
                context.textBaseline = 'middle';
                context.fillText(
                    text,
                    context.textAlign === 'center'
                        ? contentX + contentWidth / 2
                        : x + 8,
                    contentY + contentHeight / 2,
                    Math.max(0, contentWidth - 12),
                );
            }
        }

        return await canvasBlob(canvas, 'image/png');
    } finally {
        host.remove();
    }
}

export function scheduleImageFilename(
    year: string,
    period: string,
    grade: string,
): string {
    const context = `${year}-${period}-${grade}`
        .replace(/[^a-zA-Z0-9-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');

    return `jadwal-${context}.png`;
}
