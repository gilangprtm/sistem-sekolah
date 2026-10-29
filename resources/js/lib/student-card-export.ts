/**
 * Exports the rendered card DOM, not a second hand-drawn card layout.
 * Keep the visual source of truth in StudentCardVisual.
 */
export const STUDENT_CARD_WIDTH = 1011;
export const STUDENT_CARD_HEIGHT = 639;

async function asDataUrl(url: string): Promise<string> {
    if (url.startsWith('data:')) {
        return url;
    }

    const response = await fetch(url, { credentials: 'same-origin' });

    if (!response.ok) {
        throw new Error('Aset kartu tidak dapat dimuat.');
    }

    const blob = await response.blob();

    return await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result));
        reader.onerror = () =>
            reject(new Error('Aset kartu tidak dapat dibaca.'));
        reader.readAsDataURL(blob);
    });
}

async function cloneWithStyles(source: Element): Promise<Element> {
    const clone = source.cloneNode(false) as Element;
    const style = window.getComputedStyle(source);
    const css = Array.from(style)
        .map((property) => `${property}:${style.getPropertyValue(property)};`)
        .join('');
    clone.setAttribute('style', css);

    if (
        source instanceof HTMLImageElement &&
        clone instanceof HTMLImageElement
    ) {
        clone.src = await asDataUrl(source.currentSrc || source.src);
        clone.removeAttribute('srcset');
    }

    const background = style.backgroundImage;

    if (background && background !== 'none') {
        const match = /^url\(["']?(.*?)["']?\)$/.exec(background);

        if (match) {
            const image = await asDataUrl(match[1]);
            (clone as HTMLElement).style.backgroundImage = `url("${image}")`;
        }
    }

    for (const child of Array.from(source.childNodes)) {
        if (child instanceof Element) {
            clone.appendChild(await cloneWithStyles(child));
        } else {
            clone.appendChild(child.cloneNode(true));
        }
    }

    return clone;
}

export async function renderStudentCardPng(card: HTMLElement): Promise<Blob> {
    // The export always uses the native card dimensions, regardless of preview size.
    const host = document.createElement('div');
    host.style.cssText = `position:fixed;left:-100000px;top:0;width:${STUDENT_CARD_WIDTH}px;height:${STUDENT_CARD_HEIGHT}px;pointer-events:none;`;
    const source = card.cloneNode(true) as HTMLElement;
    source.style.width = `${STUDENT_CARD_WIDTH}px`;
    source.style.height = `${STUDENT_CARD_HEIGHT}px`;
    source.style.maxWidth = 'none';
    source.style.transform = 'none';
    host.appendChild(source);
    document.body.appendChild(host);

    try {
        await document.fonts.ready;
        const copy = (await cloneWithStyles(source)) as HTMLElement;
        copy.style.width = `${STUDENT_CARD_WIDTH}px`;
        copy.style.height = `${STUDENT_CARD_HEIGHT}px`;
        copy.style.transform = 'none';
        const markup = new XMLSerializer().serializeToString(copy);
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${STUDENT_CARD_WIDTH}" height="${STUDENT_CARD_HEIGHT}"><foreignObject width="100%" height="100%"><div xmlns="http://www.w3.org/1999/xhtml" style="width:100%;height:100%">${markup}</div></foreignObject></svg>`;
        // Blob-backed SVG foreignObjects taint the canvas in Chromium.
        const svgUrl = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;

        const image = new Image();
        await new Promise<void>((resolve, reject) => {
            image.onload = () => resolve();
            image.onerror = () =>
                reject(
                    new Error(
                        'Browser tidak dapat merender kartu. Coba browser lain.',
                    ),
                );
            image.src = svgUrl;
        });
        const canvas = document.createElement('canvas');
        canvas.width = STUDENT_CARD_WIDTH;
        canvas.height = STUDENT_CARD_HEIGHT;
        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Canvas tidak tersedia.');
        }

        context.drawImage(image, 0, 0);

        return await new Promise<Blob>((resolve, reject) =>
            canvas.toBlob(
                (blob) =>
                    blob
                        ? resolve(blob)
                        : reject(new Error('Gagal mengekspor PNG.')),
                'image/png',
            ),
        );
    } finally {
        host.remove();
    }
}

export function saveStudentCardBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
