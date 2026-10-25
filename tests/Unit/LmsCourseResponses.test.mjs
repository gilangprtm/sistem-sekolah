import assert from 'node:assert/strict';
import { test } from 'node:test';
import { getCourseResponseMaterials } from '../../resources/js/lib/lms-course-responses.ts';

const page = (id, title, questionCount) => ({
    id,
    title,
    questions: Array.from({ length: questionCount }, (_, index) => ({
        id: `${id}-question-${index + 1}`,
    })),
});

test('response tabs include only the material pages that contain questions', () => {
    const responseMaterials = getCourseResponseMaterials({
        materials: [
            {
                ...page('material-1', 'Materi 1', 0),
                sections: [
                    page('section-1', 'Bagian 1', 2),
                    page('section-2', 'Bagian 2', 0),
                ],
            },
            {
                ...page('material-2', 'Materi 2', 1),
                sections: [page('section-3', 'Bagian 3', 0)],
            },
            {
                ...page('material-3', 'Materi 3', 0),
                sections: [],
            },
        ],
    });

    assert.deepEqual(
        responseMaterials.map(({ number, title, pages }) => ({
            number,
            title,
            pageIds: pages.map(({ id }) => id),
        })),
        [
            { number: 1, title: 'Materi 1', pageIds: ['section-1'] },
            { number: 2, title: 'Materi 2', pageIds: ['material-2'] },
        ],
    );
});

test('response tabs preserve material order and question content without requiring answers', () => {
    const material = {
        ...page('material-2', 'Materi 2', 1),
        sections: [
            page('section-1', 'Bagian 1', 1),
            page('section-2', 'Bagian 2', 1),
        ],
    };

    const result = getCourseResponseMaterials({
        materials: [
            { ...page('material-1', 'Materi 1', 0), sections: [] },
            material,
        ],
    });

    assert.equal(result[0].number, 2);
    assert.deepEqual(
        result[0].pages.map(({ id }) => id),
        ['material-2', 'section-1', 'section-2'],
    );
    assert.equal(result[0].pages[1].questions, material.sections[0].questions);
});

test('response tabs are empty when no pages contain questions', () => {
    assert.deepEqual(
        getCourseResponseMaterials({
            materials: [
                {
                    ...page('material-1', 'Materi 1', 0),
                    sections: [page('section-1', 'Bagian 1', 0)],
                },
            ],
        }),
        [],
    );
});
