import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import process from 'node:process';
import { test } from 'node:test';
import ts from 'typescript';

const browserModule = process.env.PLAYWRIGHT_MODULE;
const executablePath = process.env.CHROMIUM_EXECUTABLE;

test(
    'card PNG exports stay origin-clean for single and sequential bulk cards',
    { skip: !browserModule || !executablePath },
    async () => {
        const { chromium } = await import(browserModule);
        const browser = await chromium.launch({
            executablePath,
            headless: true,
            args: ['--no-sandbox'],
        });

        try {
            const source = await readFile(
                new URL(
                    '../../resources/js/lib/student-card-export.ts',
                    import.meta.url,
                ),
                'utf8',
            );
            const code = ts.transpileModule(source, {
                compilerOptions: {
                    module: ts.ModuleKind.ESNext,
                    target: ts.ScriptTarget.ES2022,
                },
            }).outputText;
            const page = await browser.newPage();
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            const asset =
                '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20" fill="#ff0000"/></svg>';
            await page.route('http://card.test/**', (route) => {
                const url = route.request().url();

                return url.endsWith('/missing.png')
                    ? route.fulfill({ status: 404, body: '' })
                    : url.endsWith('.svg')
                      ? route.fulfill({
                            contentType: 'image/svg+xml',
                            body: asset,
                        })
                      : route.fulfill({
                            contentType: 'text/html',
                            body: '<html><body></body></html>',
                        });
            });
            await page.goto('http://card.test/');
            const results = await page.evaluate(
                async ({ code, asset }) => {
                    const exporter = await import(
                        `data:text/javascript;base64,${btoa(code)}`
                    );
                    const qr = `data:image/svg+xml,${encodeURIComponent(asset)}`;
                    document.body.innerHTML = `<article style="width:1011px;height:639px;background-image:url('/background.svg');transform:scale(0.4)"><h1>Siswa Éka &amp; Putri</h1><img src="/photo.svg" alt="Foto"/><img src="${qr}" alt="QR"/></article>`;
                    const card = document.querySelector('article');
                    const original = card.outerHTML;
                    const exports = [];

                    for (let index = 0; index < 3; index += 1) {
                        const blob = await exporter.renderStudentCardPng(card);
                        const bytes = new Uint8Array(await blob.arrayBuffer());
                        const bitmap = await createImageBitmap(blob);
                        const canvas = document.createElement('canvas');
                        canvas.width = bitmap.width;
                        canvas.height = bitmap.height;
                        const context = canvas.getContext('2d');
                        context.drawImage(bitmap, 0, 0);
                        exports.push({
                            type: blob.type,
                            signature: [...bytes.slice(0, 8)],
                            width: bitmap.width,
                            height: bitmap.height,
                            backgroundPixel: [
                                ...context.getImageData(1000, 600, 1, 1).data,
                            ],
                        });
                        bitmap.close();
                    }

                    const unchanged = card.outerHTML === original;
                    const bodyCount = document.body.childElementCount;
                    card.querySelector('img').src = '/missing.png';
                    let failure;

                    try {
                        await exporter.renderStudentCardPng(card);
                    } catch (error) {
                        failure = error.message;
                    }

                    return {
                        exports,
                        unchanged,
                        bodyCount,
                        finalBodyCount: document.body.childElementCount,
                        failure,
                    };
                },
                { code, asset },
            );

            assert.equal(results.exports.length, 3);

            for (const png of results.exports) {
                assert.equal(png.type, 'image/png');
                assert.deepEqual(
                    png.signature,
                    [137, 80, 78, 71, 13, 10, 26, 10],
                );
                assert.equal(png.width, 1011);
                assert.equal(png.height, 639);
                assert.deepEqual(png.backgroundPixel, [255, 0, 0, 255]);
            }

            assert.equal(results.unchanged, true);
            assert.equal(results.bodyCount, 1);
            assert.equal(results.finalBodyCount, 1);
            assert.equal(results.failure, 'Aset kartu tidak dapat dimuat.');
            assert.deepEqual(errors, []);
        } finally {
            await browser.close();
        }
    },
);
