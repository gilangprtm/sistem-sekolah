import assert from 'node:assert/strict';
import { test } from 'node:test';
import { getInitials } from '../../resources/js/lib/utils.ts';

test('user avatar initial contains only the first character of the name', () => {
    assert.equal(getInitials('Ni Made Meinayanti, S.Pd.'), 'N');
});

test('user avatar initial trims whitespace and supports Unicode characters', () => {
    assert.equal(getInitials('   émilie Durand'), 'É');
});

test('user avatar initial uses a fallback for an empty name', () => {
    assert.equal(getInitials('   '), '?');
});
