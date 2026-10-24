export const courseManagementStatusLabels: Record<string, string> = {
    draft: 'Draft',
    active: 'Aktif',
    archived: 'Arsip',
};

export const courseStatusLabels = courseManagementStatusLabels;

export type CourseQuestion = {
    id: string;
    type: 'multiple_choice' | 'essay';
    prompt: string;
    options?: { id: string; text: string; is_correct: boolean }[];
};
export type CourseMedia = {
    id: string;
    type: 'image' | 'pdf' | 'youtube';
    asset_id?: string;
    url?: string;
    caption?: string;
};
export type CoursePage = {
    id: string;
    title: string;
    body: string;
    layout?: 'media_first' | 'text_first';
    media: CourseMedia[];
    questions: CourseQuestion[];
};
export type CourseMaterial = CoursePage & { sections: CoursePage[] };
export type LearningCourseQuestion = Omit<CourseQuestion, 'options'> & {
    options?: { id: string; text: string }[];
};
export type LearningCoursePage = Omit<CoursePage, 'questions'> & {
    questions: LearningCourseQuestion[];
};
export type LearningCourseContent = Omit<
    CourseContent,
    'materials' | 'uploads'
> & {
    materials: (LearningCoursePage & { sections: LearningCoursePage[] })[];
};
export type CourseContent = {
    materials: CourseMaterial[];
    assets?: Record<string, { name: string; type: string; url: string }>;
    uploads?: Record<string, File>;
};
