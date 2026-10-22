export type AttendanceStatus = 'H' | 'I' | 'S' | 'D' | 'A';

export type AttendanceRow = {
    student_id: number;
    full_name: string;
    nis: string | null;
    status: AttendanceStatus;
    version: number;
    phone?: string | null;
    parent_phone?: string | null;
};
