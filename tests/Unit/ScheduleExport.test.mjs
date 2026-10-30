import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import ts from 'typescript';

const source = await readFile(
    new URL('../../resources/js/lib/schedule-export.ts', import.meta.url),
    'utf8',
);
const code = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.ESNext,
        target: ts.ScriptTarget.ES2022,
    },
}).outputText;
const exporter = await import(`data:text/javascript;base64,${btoa(code)}`);

test('schedule image filenames include the period and selected grade', () => {
    assert.equal(
        exporter.scheduleImageFilename('2026/2027', 'Semester Ganjil', 'VII'),
        'jadwal-2026-2027-Semester-Ganjil-VII.png',
    );
    assert.equal(
        exporter.scheduleImageFilename('2026/2027', 'Semester Genap', 'VIII'),
        'jadwal-2026-2027-Semester-Genap-VIII.png',
    );
});

test('schedule image filenames normalize punctuation without an empty stem', () => {
    assert.equal(
        exporter.scheduleImageFilename('2026/2027', '---', 'VII'),
        'jadwal-2026-2027-VII.png',
    );
});

function mockBrowser(
    t,
    { failBlob = false, noContext = false, withSubject = false } = {},
) {
    const drawnText = [];
    const paintedCells = [];
    const filledRects = [];
    const subjectRects = [];
    const canvases = [];
    const hosts = [];
    const context = {
        fillStyle: '',
        scale() {},
        fillRect(...rect) {
            filledRects.push({ color: this.fillStyle, rect });
        },
        strokeRect(...rect) {
            paintedCells.push(rect);
        },
        beginPath() {},
        roundRect(...rect) {
            subjectRects.push(rect);
        },
        fill() {},
        fillText(text) {
            drawnText.push(text);
        },
    };
    class Element {
        children = [];
        style = { setProperty() {} };
        removed = false;
        appendChild(child) {
            this.children.push(child);
        }
        remove() {
            this.removed = true;
        }
        querySelector() {
            return null;
        }
    }
    const tableRect = { left: -100000, top: 0, width: 2400, height: 3600 };
    const cell = Object.assign(new Element(), {
        tagName: 'TD',
        textContent: 'Istirahat',
        getBoundingClientRect: () => ({
            left: -100000,
            top: 3500,
            width: 2400,
            height: 100,
        }),
    });

    if (withSubject) {
        const subject = Object.assign(new Element(), {
            textContent: 'IPA01',
            getBoundingClientRect: () => ({
                left: -99996,
                top: 3504,
                width: 88,
                height: 36,
            }),
        });
        cell.querySelector = () => subject;
    }

    const dividerCell = Object.assign(new Element(), {
        tagName: 'TD',
        textContent: '',
        getBoundingClientRect: () => ({
            left: -100000,
            top: 3400,
            width: 2400,
            height: 12,
        }),
    });
    const clone = Object.assign(new Element(), {
        tagName: 'TABLE',
        rows: [
            { cells: [cell], hasAttribute: () => false },
            {
                cells: [dividerCell],
                hasAttribute: (name) => name === 'data-schedule-day-divider',
            },
        ],
        getBoundingClientRect: () => tableRect,
    });
    const table = Object.assign(new Element(), {
        tagName: 'TABLE',
        scrollWidth: 2400,
        cloneNode: () => clone,
        getBoundingClientRect: () => ({ width: 960, height: 3600 }),
    });
    const originals = ['HTMLElement', 'window', 'document'].map((key) => [
        key,
        Object.getOwnPropertyDescriptor(globalThis, key),
    ]);
    t.after(() => {
        for (const [key, descriptor] of originals) {
            if (descriptor) {
                Object.defineProperty(globalThis, key, descriptor);
            } else {
                delete globalThis[key];
            }
        }
    });
    globalThis.HTMLElement = Element;
    globalThis.window = {
        getComputedStyle: () => ({
            getPropertyValue: () => '',
            fontSize: '14px',
            fontFamily: 'sans-serif',
            fontWeight: '400',
            textAlign: 'center',
            backgroundColor: 'rgb(255, 165, 0)',
        }),
    };
    globalThis.document = {
        fonts: { ready: Promise.resolve() },
        body: new Element(),
        createElement(tag) {
            if (tag !== 'canvas') {
                const host = new Element();
                hosts.push(host);

                return host;
            }

            const canvas = {
                getContext: () => (noContext ? null : context),
                toBlob(callback, type) {
                    callback(failBlob ? null : new Blob(['image'], { type }));
                },
            };
            canvases.push(canvas);

            return canvas;
        },
    };

    return {
        table,
        drawnText,
        paintedCells,
        filledRects,
        subjectRects,
        canvases,
        hosts,
    };
}

test('render png covers the full table rather than its viewport', async (t) => {
    const browser = mockBrowser(t);
    const blob = await exporter.renderScheduleImage(
        browser.table,
        'Semester Ganjil VII',
    );
    assert.equal(blob.type, 'image/png');
    assert.ok(browser.canvases[0].width >= 2400);
    assert.ok(browser.canvases[0].height >= 3600);
    assert.ok(browser.drawnText.includes('Istirahat'));
    assert.deepEqual(browser.paintedCells[0], [32, 3590, 2400, 100]);
    assert.ok(
        browser.filledRects.some(
            ({ color, rect }) =>
                color === '#000000' && rect[1] === 3490 && rect[3] === 12,
        ),
    );
    assert.ok(browser.hosts.every((host) => host.removed));
});

test('export preserves the subject card dimensions without shrinking its padding', async (t) => {
    const browser = mockBrowser(t, { withSubject: true });

    await exporter.renderScheduleImage(browser.table, 'Semester Ganjil VII');

    assert.deepEqual(browser.subjectRects, [[36, 3594, 88, 36, 5]]);
    assert.ok(browser.drawnText.includes('IPA01'));
});

test('render failure removes temporary DOM and returns an actionable error', async (t) => {
    const browser = mockBrowser(t, { failBlob: true });
    await assert.rejects(
        exporter.renderScheduleImage(browser.table, 'Semester Ganjil VII'),
        /gagal diekspor/,
    );
    assert.ok(browser.hosts.every((host) => host.removed));
});

test('missing canvas support removes temporary DOM', async (t) => {
    const browser = mockBrowser(t, { noContext: true });
    await assert.rejects(
        exporter.renderScheduleImage(browser.table, 'Semester Ganjil VII'),
        /Canvas tidak tersedia/,
    );
    assert.ok(browser.hosts.every((host) => host.removed));
});
