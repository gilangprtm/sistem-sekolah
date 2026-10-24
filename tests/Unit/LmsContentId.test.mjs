import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createLmsContentId } from '../../resources/js/lib/lms-content-id.ts';

const replaceCrypto = (context, replacement) => {
    const original = Object.getOwnPropertyDescriptor(globalThis, 'crypto');
    Object.defineProperty(globalThis, 'crypto', {
        configurable: true,
        value: replacement,
    });
    context.after(() => Object.defineProperty(globalThis, 'crypto', original));
};

test('LMS content IDs use native randomUUID when available', (context) => {
    replaceCrypto(context, {
        randomUUID: () => '724ca154-cfaa-49d5-8bc1-e4c19debc3ce',
    });

    const id = createLmsContentId();

    assert.equal(id, '724ca154-cfaa-49d5-8bc1-e4c19debc3ce');
});

test('LMS content IDs are valid version 4 UUIDs without randomUUID on HTTP', (context) => {
    replaceCrypto(context, {
        getRandomValues: (bytes) => bytes.fill(255),
    });

    const id = createLmsContentId();

    assert.equal(id, 'ffffffff-ffff-4fff-bfff-ffffffffffff');
});

test('fallback LMS content IDs preserve leading zeros and use fresh randomness', (context) => {
    let calls = 0;
    replaceCrypto(context, {
        getRandomValues: (bytes) => bytes.fill(calls++),
    });

    const first = createLmsContentId();
    const second = createLmsContentId();

    assert.equal(first, '00000000-0000-4000-8000-000000000000');
    assert.equal(second, '01010101-0101-4101-8101-010101010101');
});
