import type { LearningCourseContent } from '@/types/lms';

type CoursePagePosition = {
    materialIndex: number;
    pageId: string;
};

type Direction = 'previous' | 'next';

export function getAdjacentCoursePage(
    materials: LearningCourseContent['materials'],
    current: CoursePagePosition,
    direction: Direction,
): CoursePagePosition | null {
    const pages = materials.flatMap((material, materialIndex) => [
        { materialIndex, pageId: 'material' },
        ...material.sections.map((section) => ({
            materialIndex,
            pageId: section.id,
        })),
    ]);
    const currentIndex = pages.findIndex(
        (page) =>
            page.materialIndex === current.materialIndex &&
            page.pageId === current.pageId,
    );

    if (currentIndex === -1) {
        return null;
    }

    const adjacentIndex = currentIndex + (direction === 'next' ? 1 : -1);

    return pages[adjacentIndex] ?? null;
}
