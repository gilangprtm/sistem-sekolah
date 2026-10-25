import assert from 'node:assert/strict';
import { test } from 'node:test';
import { getAdjacentCoursePage } from '../../resources/js/lib/lms-course-navigation.ts';

const materials = [
    { id: 'm1', sections: [{ id: 'm1s1' }, { id: 'm1s2' }] },
    { id: 'm2', sections: [{ id: 'm2s1' }] },
];

test('next visits each section before moving to the next material', () => {
    assert.deepEqual(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 0, pageId: 'material' },
            'next',
        ),
        { materialIndex: 0, pageId: 'm1s1' },
    );
    assert.deepEqual(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 0, pageId: 'm1s1' },
            'next',
        ),
        { materialIndex: 0, pageId: 'm1s2' },
    );
    assert.deepEqual(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 0, pageId: 'm1s2' },
            'next',
        ),
        { materialIndex: 1, pageId: 'material' },
    );
});

test('previous returns from a material to the last section of the previous material', () => {
    assert.deepEqual(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 1, pageId: 'material' },
            'previous',
        ),
        { materialIndex: 0, pageId: 'm1s2' },
    );
});

test('previous walks backward through sections and back to the material', () => {
    assert.deepEqual(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 0, pageId: 'm1s2' },
            'previous',
        ),
        { materialIndex: 0, pageId: 'm1s1' },
    );
    assert.deepEqual(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 0, pageId: 'm1s1' },
            'previous',
        ),
        { materialIndex: 0, pageId: 'material' },
    );
});

test('navigation stops at the first and last course pages', () => {
    assert.equal(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 0, pageId: 'material' },
            'previous',
        ),
        null,
    );
    assert.equal(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 1, pageId: 'm2s1' },
            'next',
        ),
        null,
    );
});

test('navigation handles a material without sections', () => {
    const singlePageMaterials = [
        { id: 'm1', sections: [] },
        { id: 'm2', sections: [] },
    ];

    assert.deepEqual(
        getAdjacentCoursePage(
            singlePageMaterials,
            { materialIndex: 0, pageId: 'material' },
            'next',
        ),
        { materialIndex: 1, pageId: 'material' },
    );
});

test('navigation returns null for an unknown current page', () => {
    assert.equal(
        getAdjacentCoursePage(
            [],
            { materialIndex: 0, pageId: 'material' },
            'next',
        ),
        null,
    );
    assert.equal(
        getAdjacentCoursePage(
            materials,
            { materialIndex: 0, pageId: 'missing' },
            'next',
        ),
        null,
    );
});
