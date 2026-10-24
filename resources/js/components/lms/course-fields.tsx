import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export type CourseFormData = {
    title: string;
    subject_id: string;
    grade_level: string;
    description: string;
    designer_id: string;
    revision_id: string;
};

export type SubjectOption = { id: number; name: string };
export type DesignerOption = { id: number; full_name: string };

type Props = {
    data: CourseFormData;
    setData: <K extends keyof CourseFormData>(
        key: K,
        value: CourseFormData[K],
    ) => void;
    errors: Partial<Record<keyof CourseFormData, string>>;
    subjects: SubjectOption[];
    gradeLevels: string[];
    designers?: DesignerOption[];
    disabled?: boolean;
};

export default function CourseFields({
    data,
    setData,
    errors,
    subjects,
    gradeLevels,
    designers,
    disabled = false,
}: Props) {
    return (
        <fieldset disabled={disabled} className="grid min-w-0 gap-5">
            <div className="grid gap-2">
                <Label htmlFor="course-title">Judul course</Label>
                <Input
                    id="course-title"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    maxLength={180}
                    required
                    aria-invalid={Boolean(errors.title)}
                    aria-describedby="course-title-error"
                    placeholder="Contoh: Ekosistem dan Interaksi Makhluk Hidup"
                />
                <InputError id="course-title-error" message={errors.title} />
            </div>
            <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_10rem]">
                <div className="grid gap-2">
                    <Label htmlFor="course-subject">Mata pelajaran</Label>
                    <SearchableCombobox
                        id="course-subject"
                        value={data.subject_id}
                        options={subjects.map((subject) => ({
                            value: String(subject.id),
                            label: subject.name,
                        }))}
                        onChange={(value) => setData('subject_id', value)}
                        placeholder="Pilih mata pelajaran"
                        searchPlaceholder="Cari mata pelajaran..."
                        emptyMessage="Mata pelajaran tidak ditemukan."
                        disabled={disabled}
                        aria-invalid={Boolean(errors.subject_id)}
                        aria-describedby="course-subject-error"
                    />
                    <InputError
                        id="course-subject-error"
                        message={errors.subject_id}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="course-grade">Jenjang</Label>
                    <Select
                        value={data.grade_level}
                        onValueChange={(value) => setData('grade_level', value)}
                        disabled={disabled}
                    >
                        <SelectTrigger
                            id="course-grade"
                            className="w-full"
                            aria-invalid={Boolean(errors.grade_level)}
                            aria-describedby="course-grade-error"
                        >
                            <SelectValue placeholder="Pilih jenjang" />
                        </SelectTrigger>
                        <SelectContent>
                            {gradeLevels.map((grade) => (
                                <SelectItem key={grade} value={grade}>
                                    Kelas {grade}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        id="course-grade-error"
                        message={errors.grade_level}
                    />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="course-description">
                    Deskripsi{' '}
                    <span className="text-muted-foreground">(opsional)</span>
                </Label>
                <Textarea
                    id="course-description"
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                    rows={4}
                    maxLength={10000}
                    aria-invalid={Boolean(errors.description)}
                    aria-describedby="course-description-error"
                    placeholder="Gambaran singkat dan tujuan pembelajaran course."
                />
                <InputError
                    id="course-description-error"
                    message={errors.description}
                />
            </div>
            {designers !== undefined && (
                <div className="grid gap-2">
                    <Label htmlFor="course-designer">Guru perancang</Label>
                    <SearchableCombobox
                        id="course-designer"
                        value={data.designer_id}
                        options={designers.map((designer) => ({
                            value: String(designer.id),
                            label: designer.full_name,
                        }))}
                        onChange={(value) => setData('designer_id', value)}
                        placeholder="Pilih guru perancang"
                        searchPlaceholder="Cari nama guru..."
                        emptyMessage="Guru tidak ditemukan."
                        disabled={disabled}
                        aria-invalid={Boolean(errors.designer_id)}
                        aria-describedby="course-designer-error"
                    />
                    <InputError
                        id="course-designer-error"
                        message={errors.designer_id}
                    />
                </div>
            )}
        </fieldset>
    );
}
