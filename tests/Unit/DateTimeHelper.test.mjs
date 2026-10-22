import assert from 'node:assert/strict';
import process from 'node:process';
import { test } from 'node:test';
import { witaDateKey } from '../../resources/js/lib/date-time.ts';

test('WITA changes day at 16:00 UTC, not at midnight UTC', () => {
    assert.equal(witaDateKey(new Date('2026-10-21T15:59:59Z')), '2026-10-21');
    assert.equal(witaDateKey(new Date('2026-10-21T16:00:00Z')), '2026-10-22');
});

test('WITA date stays the same regardless of the runtime timezone', () => {
    const originalTimeZone = process.env.TZ;

    try {
        for (const timeZone of [
            'UTC',
            'America/Los_Angeles',
            'Asia/Makassar',
            'Pacific/Auckland',
        ]) {
            process.env.TZ = timeZone;
            assert.equal(
                witaDateKey(new Date('2026-10-21T16:30:00Z')),
                '2026-10-22',
            );
        }
    } finally {
        if (originalTimeZone === undefined) {
            delete process.env.TZ;
        } else {
            process.env.TZ = originalTimeZone;
        }
    }
});

test('WITA preserves month and year rollover', () => {
    assert.equal(witaDateKey(new Date('2026-10-31T16:00:00Z')), '2026-11-01');
    assert.equal(witaDateKey(new Date('2026-12-31T16:00:00Z')), '2027-01-01');
});
