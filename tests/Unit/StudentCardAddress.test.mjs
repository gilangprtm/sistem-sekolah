import assert from 'node:assert/strict';
import { test } from 'node:test';
import { wrapStudentCardAddress } from '../../resources/js/lib/student-card-address.ts';

test('address stays on one line at or below 19 characters', () => {
    assert.equal(
        wrapStudentCardAddress('JLN WATURENGGONG GG'),
        'JLN WATURENGGONG GG',
    );
    assert.equal(
        wrapStudentCardAddress('1234567890123456789'),
        '1234567890123456789',
    );
});

test('address wraps the next characters to aligned continuation lines after 19', () => {
    assert.equal(
        wrapStudentCardAddress('12345678901234567890 JL DENPASAR'),
        '1234567890123456789\n0 JL DENPASAR',
    );
});

test('address wraps a long unbroken segment without exceeding 19 characters', () => {
    const lines = wrapStudentCardAddress('A'.repeat(42)).split('\n');

    assert.deepEqual(
        lines.map((line) => line.length),
        [19, 19, 4],
    );
});

test('empty address uses the existing placeholder', () => {
    assert.equal(wrapStudentCardAddress(null), '-');
    assert.equal(wrapStudentCardAddress('  '), '-');
});
