export type AgendaSnapshot = {
    academic_year: string;
    academic_period: string;
    rombel_code: string;
    subject_name: string;
    teacher_name: string;
    lesson_start: number;
    lesson_end: number;
    start_time: string;
    end_time: string;
};

export type AgendaAttendance = {
    student_id: number;
    full_name: string;
    nis: string | null;
    status: 'H' | 'I' | 'S' | 'D' | 'A';
};
