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
    media: CourseMedia[];
    questions: CourseQuestion[];
};
export type CourseMaterial = CoursePage & { sections: CoursePage[] };
export type CourseContent = {
    materials: CourseMaterial[];
    assets?: Record<string, { name: string; type: string; url: string }>;
    uploads?: Record<string, File>;
};
