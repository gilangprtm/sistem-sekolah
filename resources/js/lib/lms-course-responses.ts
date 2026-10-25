import type { LearningCourseContent } from '@/types/lms';

export function getCourseResponseMaterials(content: LearningCourseContent) {
    return content.materials.flatMap((material, index) => {
        const pages = [material, ...material.sections].filter(
            (page) => page.questions.length > 0,
        );

        return pages.length === 0
            ? []
            : [
                  {
                      id: material.id,
                      title: material.title,
                      number: index + 1,
                      pages,
                  },
              ];
    });
}
