import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import RichTextEditor from '@/components/lms/rich-text-editor';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { createLmsContentId as newId } from '@/lib/lms-content-id';
import type { CourseContent } from '@/types/lms';

type Props = {
    content: CourseContent;
    disabled: boolean;
    onChange: (content: CourseContent) => void;
};

const move = <T,>(items: T[], index: number, delta: number): T[] => {
    const next = [...items];
    [next[index], next[index + delta]] = [next[index + delta], next[index]];

    return next;
};

export default function CourseContentEditor({
    content,
    disabled,
    onChange,
}: Props) {
    const confirm = useConfirmation();
    const [activeMaterial, setActiveMaterial] = useState(0);
    const [activeSection, setActiveSection] = useState<number | null>(null);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const materials = content.materials;
    const material = materials[activeMaterial];
    const page =
        material && activeSection === null
            ? material
            : material?.sections[activeSection ?? -1];
    const updateMaterial = (
        index: number,
        patch: Partial<(typeof materials)[number]>,
    ) =>
        onChange({
            ...content,
            materials: materials.map((item, itemIndex) =>
                itemIndex === index ? { ...item, ...patch } : item,
            ),
        });
    const updatePage = (patch: Partial<NonNullable<typeof page>>) => {
        if (!material || !page) {
            return;
        }

        if (activeSection === null) {
            updateMaterial(activeMaterial, patch);
        } else {
            updateMaterial(activeMaterial, {
                sections: material.sections.map((section, index) =>
                    index === activeSection
                        ? { ...section, ...patch }
                        : section,
                ),
            });
        }
    };
    const addMaterial = () => {
        const next = {
            id: newId(),
            title: `Materi ${materials.length + 1}`,
            body: '',
            media: [],
            sections: [],
            questions: [],
        };
        onChange({ ...content, materials: [...materials, next] });
        setActiveMaterial(materials.length);
        setActiveSection(null);
    };
    const addSection = () => {
        if (!material) {
            return;
        }

        const sections = [
            ...material.sections,
            {
                id: newId(),
                title: `Bagian ${material.sections.length + 1}`,
                body: '',
                media: [],
                questions: [],
            },
        ];
        updateMaterial(activeMaterial, { sections });
        setActiveSection(sections.length - 1);
    };
    const addQuestion = (type: 'multiple_choice' | 'essay') => {
        if (!page) {
            return;
        }

        const question = {
            id: newId(),
            type,
            prompt: '',
            points: null,
            ...(type === 'multiple_choice'
                ? {
                      options: [
                          { id: newId(), text: '', is_correct: false },
                          { id: newId(), text: '', is_correct: false },
                      ],
                  }
                : {}),
        };
        updatePage({ questions: [...page.questions, question] });
    };
    const addYouTube = () => {
        if (!page) {
            return;
        }

        updatePage({
            media: [
                ...(page.media ?? []),
                { id: newId(), type: 'youtube', url: '', caption: '' },
            ],
        });
    };
    const addUpload = (file: File) => {
        if (!page) {
            return;
        }

        const id = newId();
        const uploads = { ...(content.uploads ?? {}), [id]: file };
        const media = [
            ...(page.media ?? []),
            {
                id: newId(),
                type:
                    file.type === 'application/pdf'
                        ? ('pdf' as const)
                        : ('image' as const),
                asset_id: id,
                caption: file.name,
            },
        ];
        onChange({
            ...content,
            uploads,
            materials: materials.map((item, index) =>
                index !== activeMaterial
                    ? item
                    : activeSection === null
                      ? { ...item, media }
                      : {
                            ...item,
                            sections: item.sections.map(
                                (section, sectionIndex) =>
                                    sectionIndex === activeSection
                                        ? { ...section, media }
                                        : section,
                            ),
                        },
            ),
        });
    };

    return (
        <div className="grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
            <aside className="grid content-start gap-2 self-start rounded-xl border p-3">
                <div className="flex items-center justify-between gap-2">
                    <h3 className="font-semibold">Materi</h3>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label="Tambah Materi"
                        disabled={disabled}
                        onClick={addMaterial}
                    >
                        <Plus />
                    </Button>
                </div>
                {materials.map((item, index) => (
                    <div key={item.id} className="grid gap-0.5">
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                activeMaterial === index &&
                                activeSection === null
                                    ? 'default'
                                    : 'ghost'
                            }
                            onClick={() => {
                                setActiveMaterial(index);
                                setActiveSection(null);
                            }}
                            className="h-auto min-h-8 justify-start py-1.5 text-left wrap-anywhere whitespace-normal"
                        >
                            {index + 1}. {item.title}
                        </Button>
                        {activeMaterial === index &&
                            item.sections.map((section, sectionIndex) => (
                                <Button
                                    key={section.id}
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        setActiveSection(sectionIndex)
                                    }
                                    className={`h-auto min-h-7 justify-start py-1 pl-6 text-left wrap-anywhere whitespace-normal ${activeSection === sectionIndex ? 'bg-muted font-medium' : 'text-muted-foreground'}`}
                                >
                                    {index + 1}.{sectionIndex + 1}{' '}
                                    {section.title}
                                </Button>
                            ))}
                    </div>
                ))}
                {materials.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Tambahkan materi pertama untuk mulai menyusun course.
                    </p>
                )}
            </aside>
            <div className="grid min-w-0 content-start gap-5">
                {page ? (
                    <>
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div className="grid min-w-56 flex-1 gap-2">
                                <Label htmlFor="content-page-title">
                                    {activeSection === null
                                        ? 'Judul Materi'
                                        : 'Judul Bagian'}
                                </Label>
                                <Input
                                    id="content-page-title"
                                    value={page.title}
                                    disabled={disabled}
                                    maxLength={180}
                                    onChange={(event) =>
                                        updatePage({
                                            title: event.target.value,
                                        })
                                    }
                                />
                            </div>
                            {activeSection === null && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={disabled}
                                    onClick={addSection}
                                >
                                    <Plus /> Tambah Bagian
                                </Button>
                            )}
                        </div>
                        {material && (
                            <>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            disabled ||
                                            (activeSection ??
                                                activeMaterial) === 0
                                        }
                                        onClick={() => {
                                            if (activeSection === null) {
                                                onChange({
                                                    ...content,
                                                    materials: move(
                                                        materials,
                                                        activeMaterial,
                                                        -1,
                                                    ),
                                                });
                                                setActiveMaterial(
                                                    activeMaterial - 1,
                                                );
                                            } else {
                                                updateMaterial(activeMaterial, {
                                                    sections: move(
                                                        material.sections,
                                                        activeSection,
                                                        -1,
                                                    ),
                                                });
                                                setActiveSection(
                                                    activeSection - 1,
                                                );
                                            }
                                        }}
                                    >
                                        <ArrowUp />
                                        Naik
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            disabled ||
                                            (activeSection === null
                                                ? activeMaterial ===
                                                  materials.length - 1
                                                : activeSection ===
                                                  material.sections.length - 1)
                                        }
                                        onClick={() => {
                                            if (activeSection === null) {
                                                onChange({
                                                    ...content,
                                                    materials: move(
                                                        materials,
                                                        activeMaterial,
                                                        1,
                                                    ),
                                                });
                                                setActiveMaterial(
                                                    activeMaterial + 1,
                                                );
                                            } else {
                                                updateMaterial(activeMaterial, {
                                                    sections: move(
                                                        material.sections,
                                                        activeSection,
                                                        1,
                                                    ),
                                                });
                                                setActiveSection(
                                                    activeSection + 1,
                                                );
                                            }
                                        }}
                                    >
                                        <ArrowDown />
                                        Turun
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={disabled}
                                        onClick={async () => {
                                            if (
                                                !(await confirm({
                                                    title: 'Hapus materi/bagian?',
                                                    description:
                                                        'Seluruh isi di dalamnya akan dihapus. Perubahan berlaku setelah Simpan Draf.',
                                                }))
                                            ) {
                                                return;
                                            }

                                            if (activeSection === null) {
                                                onChange({
                                                    ...content,
                                                    materials: materials.filter(
                                                        (_, index) =>
                                                            index !==
                                                            activeMaterial,
                                                    ),
                                                });
                                                setActiveMaterial(0);
                                            } else {
                                                updateMaterial(activeMaterial, {
                                                    sections:
                                                        material.sections.filter(
                                                            (_, index) =>
                                                                index !==
                                                                activeSection,
                                                        ),
                                                });
                                            }

                                            setActiveSection(null);
                                        }}
                                    >
                                        <Trash2 />
                                        Hapus{' '}
                                        {activeSection === null
                                            ? 'Materi'
                                            : 'Bagian'}
                                    </Button>
                                </div>
                                {material.sections.length > 0 && (
                                    <Tabs
                                        value={
                                            activeSection === null
                                                ? 'main'
                                                : String(activeSection)
                                        }
                                        onValueChange={(value) =>
                                            setActiveSection(
                                                value === 'main'
                                                    ? null
                                                    : Number(value),
                                            )
                                        }
                                    >
                                        <TabsList
                                            variant="line"
                                            className="h-11 w-full justify-start overflow-x-auto overflow-y-hidden"
                                            aria-label="Materi dan Bagian"
                                        >
                                            <TabsTrigger
                                                type="button"
                                                value="main"
                                            >
                                                Materi {activeMaterial + 1}
                                            </TabsTrigger>
                                            {material.sections.map(
                                                (section, index) => (
                                                    <TabsTrigger
                                                        type="button"
                                                        key={section.id}
                                                        value={String(index)}
                                                    >
                                                        Bagian {index + 1}
                                                    </TabsTrigger>
                                                ),
                                            )}
                                        </TabsList>
                                        <TabsContent
                                            value={
                                                activeSection === null
                                                    ? 'main'
                                                    : String(activeSection)
                                            }
                                            className="sr-only"
                                        >
                                            {page.title}
                                        </TabsContent>
                                    </Tabs>
                                )}
                            </>
                        )}
                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="content-page-layout">
                                Urutan konten di pratinjau
                            </Label>
                            <Select
                                value={page.layout ?? 'media_first'}
                                disabled={disabled}
                                onValueChange={(
                                    value: 'media_first' | 'text_first',
                                ) => updatePage({ layout: value })}
                            >
                                <SelectTrigger id="content-page-layout">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="media_first">
                                        Media di atas
                                    </SelectItem>
                                    <SelectItem value="text_first">
                                        Teks di atas
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="content-page-body">
                                Teks Materi
                            </Label>
                            <RichTextEditor
                                key={page.id}
                                id="content-page-body"
                                value={page.body}
                                disabled={disabled}
                                onChange={(body) => updatePage({ body })}
                            />
                            <p className="mt-2 text-xs text-muted-foreground">
                                Format teks didukung saat ditempel. Elemen yang
                                tidak didukung dan tautan tidak aman akan
                                dibuang saat disimpan.
                            </p>
                        </div>
                        <div className="grid min-w-0 gap-4 rounded-xl border p-4">
                            <h4 className="font-semibold">
                                Lampiran dan video
                            </h4>
                            <div className="grid min-w-0 items-start gap-5 lg:grid-cols-2">
                                {(['youtube', 'file'] as const).map((group) => (
                                    <section
                                        key={group}
                                        aria-label={
                                            group === 'youtube'
                                                ? 'Video YouTube'
                                                : 'Gambar dan PDF'
                                        }
                                        className="grid min-w-0 gap-3"
                                    >
                                        {group === 'youtube' ? (
                                            <div className="grid gap-2">
                                                <p className="text-sm font-medium">
                                                    Video YouTube
                                                </p>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={disabled}
                                                    onClick={addYouTube}
                                                    className="w-full"
                                                >
                                                    Tambah YouTube
                                                </Button>
                                            </div>
                                        ) : (
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="content-media-upload">
                                                    Tambah media (gambar atau
                                                    PDF)
                                                </Label>
                                                <Input
                                                    id="content-media-upload"
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                                    disabled={disabled}
                                                    aria-describedby="content-media-upload-help content-media-upload-error"
                                                    aria-invalid={Boolean(
                                                        uploadError,
                                                    )}
                                                    onChange={(event) => {
                                                        const file =
                                                            event.target
                                                                .files?.[0];
                                                        setUploadError(null);

                                                        if (
                                                            file &&
                                                            file.size >
                                                                10 * 1024 * 1024
                                                        ) {
                                                            setUploadError(
                                                                'Ukuran file maksimal 10 MB.',
                                                            );
                                                        } else if (
                                                            file &&
                                                            page.media.length >=
                                                                20
                                                        ) {
                                                            setUploadError(
                                                                'Setiap Materi atau Bagian dapat memiliki maksimal 20 lampiran.',
                                                            );
                                                        } else if (file) {
                                                            addUpload(file);
                                                        }

                                                        event.target.value = '';
                                                    }}
                                                />
                                            </div>
                                        )}
                                        {page.media
                                            .filter((item) =>
                                                group === 'youtube'
                                                    ? item.type === 'youtube'
                                                    : item.type !== 'youtube',
                                            )
                                            .map((item) => (
                                                <div
                                                    key={item.id}
                                                    className="grid min-w-0 gap-2"
                                                >
                                                    <Label
                                                        htmlFor={
                                                            item.type ===
                                                            'youtube'
                                                                ? `content-media-${item.id}`
                                                                : undefined
                                                        }
                                                    >
                                                        {item.type === 'youtube'
                                                            ? 'Tautan YouTube'
                                                            : item.type ===
                                                                'pdf'
                                                              ? 'PDF'
                                                              : 'Gambar'}
                                                    </Label>
                                                    {item.type === 'youtube' ? (
                                                        <Input
                                                            id={`content-media-${item.id}`}
                                                            value={
                                                                item.url ?? ''
                                                            }
                                                            disabled={disabled}
                                                            type="url"
                                                            placeholder="https://youtu.be/..."
                                                            onChange={(event) =>
                                                                updatePage({
                                                                    media: (
                                                                        page.media ??
                                                                        []
                                                                    ).map(
                                                                        (
                                                                            media,
                                                                        ) =>
                                                                            media.id ===
                                                                            item.id
                                                                                ? {
                                                                                      ...media,
                                                                                      url: event
                                                                                          .target
                                                                                          .value,
                                                                                  }
                                                                                : media,
                                                                    ),
                                                                })
                                                            }
                                                        />
                                                    ) : (
                                                        <p className="truncate text-sm text-muted-foreground">
                                                            {item.caption ||
                                                                content
                                                                    .assets?.[
                                                                    item.asset_id ??
                                                                        ''
                                                                ]?.name ||
                                                                'Lampiran terpilih'}
                                                        </p>
                                                    )}
                                                    <div className="flex flex-wrap gap-2">
                                                        {item.asset_id &&
                                                            content.assets?.[
                                                                item.asset_id
                                                            ] && (
                                                                <a
                                                                    className="text-sm underline"
                                                                    href={
                                                                        content
                                                                            .assets[
                                                                            item
                                                                                .asset_id
                                                                        ].url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    Buka
                                                                    lampiran
                                                                    tersimpan
                                                                </a>
                                                            )}
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={disabled}
                                                            onClick={() =>
                                                                updatePage({
                                                                    media: page.media.filter(
                                                                        (
                                                                            media,
                                                                        ) =>
                                                                            media.id !==
                                                                            item.id,
                                                                    ),
                                                                })
                                                            }
                                                        >
                                                            Hapus media
                                                        </Button>
                                                    </div>
                                                    {item.type === 'image' &&
                                                        item.asset_id &&
                                                        content.assets?.[
                                                            item.asset_id
                                                        ] && (
                                                            <img
                                                                className="max-h-72 max-w-full rounded-lg object-contain"
                                                                src={
                                                                    content
                                                                        .assets[
                                                                        item
                                                                            .asset_id
                                                                    ].url
                                                                }
                                                                alt={
                                                                    item.caption ??
                                                                    'Gambar materi'
                                                                }
                                                            />
                                                        )}
                                                    {item.type === 'youtube' &&
                                                        /^https:\/\/www\.youtube\.com\/watch\?v=[A-Za-z0-9_-]{11}$/.test(
                                                            item.url ?? '',
                                                        ) && (
                                                            <iframe
                                                                title={`Video ${item.id}`}
                                                                className="aspect-video w-full rounded-lg"
                                                                src={`https://www.youtube-nocookie.com/embed/${item.url?.split('v=')[1]}`}
                                                                loading="lazy"
                                                                referrerPolicy="strict-origin-when-cross-origin"
                                                                allowFullScreen
                                                            />
                                                        )}
                                                </div>
                                            ))}
                                        {group === 'file' && (
                                            <>
                                                <p
                                                    id="content-media-upload-help"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    JPEG, PNG, WebP, atau PDF;
                                                    maksimal 10 MB per file, 10
                                                    file baru per penyimpanan.
                                                    Pratinjau tersedia setelah
                                                    disimpan.
                                                </p>
                                                {uploadError && (
                                                    <Alert
                                                        id="content-media-upload-error"
                                                        variant="destructive"
                                                    >
                                                        <AlertDescription>
                                                            {uploadError}
                                                        </AlertDescription>
                                                    </Alert>
                                                )}
                                            </>
                                        )}
                                    </section>
                                ))}
                            </div>
                        </div>
                        <div className="grid gap-3 rounded-xl border p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h4 className="font-semibold">Soal</h4>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={disabled}
                                        onClick={() =>
                                            addQuestion('multiple_choice')
                                        }
                                    >
                                        Tambah Pilihan Ganda
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={disabled}
                                        onClick={() => addQuestion('essay')}
                                    >
                                        Tambah Uraian
                                    </Button>
                                </div>
                            </div>
                            {page.questions.length > 0 && (
                                <p
                                    className="text-sm text-muted-foreground"
                                    role="status"
                                >
                                    Total bobot tab ini:{' '}
                                    {Number(
                                        page.questions
                                            .reduce(
                                                (total, question) =>
                                                    total +
                                                    (Number(question.points) ||
                                                        0),
                                                0,
                                            )
                                            .toFixed(2),
                                    )}
                                    /100. Setiap Materi atau Bagian yang
                                    memiliki soal wajib tepat 100 sebelum
                                    diaktifkan.
                                </p>
                            )}
                            {page.questions.map((question, index) => (
                                <div
                                    key={question.id}
                                    className="grid gap-3 rounded-lg bg-muted/40 p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <Label>
                                            {index + 1}.{' '}
                                            {question.type === 'essay'
                                                ? 'Uraian'
                                                : 'Pilihan Ganda'}
                                        </Label>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Hapus soal"
                                            disabled={disabled}
                                            onClick={() =>
                                                updatePage({
                                                    questions:
                                                        page.questions.filter(
                                                            (item) =>
                                                                item.id !==
                                                                question.id,
                                                        ),
                                                })
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                    <Textarea
                                        rows={3}
                                        value={question.prompt}
                                        disabled={disabled}
                                        maxLength={10000}
                                        onChange={(event) =>
                                            updatePage({
                                                questions: page.questions.map(
                                                    (item) =>
                                                        item.id === question.id
                                                            ? {
                                                                  ...item,
                                                                  prompt: event
                                                                      .target
                                                                      .value,
                                                              }
                                                            : item,
                                                ),
                                            })
                                        }
                                        placeholder="Tulis pertanyaan"
                                    />
                                    <div className="grid max-w-xs gap-2">
                                        <Label
                                            htmlFor={`points-${question.id}`}
                                        >
                                            {question.type === 'essay'
                                                ? 'Nilai maksimal'
                                                : 'Nilai jawaban benar'}
                                        </Label>
                                        <Input
                                            id={`points-${question.id}`}
                                            aria-label={`Bobot soal ${question.type === 'essay' ? 'uraian' : 'pilihan ganda'}`}
                                            type="number"
                                            min="0.01"
                                            max="100"
                                            step="0.01"
                                            value={question.points ?? ''}
                                            disabled={disabled}
                                            placeholder="Belum diatur"
                                            onChange={(event) =>
                                                updatePage({
                                                    questions:
                                                        page.questions.map(
                                                            (item) =>
                                                                item.id ===
                                                                question.id
                                                                    ? {
                                                                          ...item,
                                                                          points:
                                                                              event
                                                                                  .target
                                                                                  .value ===
                                                                              ''
                                                                                  ? null
                                                                                  : Number(
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                    ),
                                                                      }
                                                                    : item,
                                                        ),
                                                })
                                            }
                                        />
                                    </div>
                                    {question.type === 'multiple_choice' && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    disabled ||
                                                    (question.options?.length ??
                                                        0) >= 6
                                                }
                                                onClick={() =>
                                                    updatePage({
                                                        questions:
                                                            page.questions.map(
                                                                (item) =>
                                                                    item.id ===
                                                                    question.id
                                                                        ? {
                                                                              ...item,
                                                                              options:
                                                                                  [
                                                                                      ...(item.options ??
                                                                                          []),
                                                                                      {
                                                                                          id: newId(),
                                                                                          text: '',
                                                                                          is_correct: false,
                                                                                      },
                                                                                  ],
                                                                          }
                                                                        : item,
                                                            ),
                                                    })
                                                }
                                            >
                                                Tambah opsi
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    disabled ||
                                                    (question.options?.length ??
                                                        0) <= 2
                                                }
                                                onClick={() =>
                                                    updatePage({
                                                        questions:
                                                            page.questions.map(
                                                                (item) =>
                                                                    item.id ===
                                                                    question.id
                                                                        ? {
                                                                              ...item,
                                                                              options:
                                                                                  item.options?.slice(
                                                                                      0,
                                                                                      -1,
                                                                                  ),
                                                                          }
                                                                        : item,
                                                            ),
                                                    })
                                                }
                                            >
                                                Hapus opsi terakhir
                                            </Button>
                                        </div>
                                    )}
                                    {question.type === 'multiple_choice' && (
                                        <div className="grid gap-2">
                                            <p className="text-sm text-muted-foreground">
                                                Pilih satu kunci jawaban benar:
                                            </p>
                                            <RadioGroup
                                                value={
                                                    question.options?.find(
                                                        (option) =>
                                                            option.is_correct,
                                                    )?.id ?? ''
                                                }
                                                disabled={disabled}
                                                aria-label={`Kunci jawaban soal ${index + 1}`}
                                                onValueChange={(optionId) =>
                                                    updatePage({
                                                        questions:
                                                            page.questions.map(
                                                                (item) =>
                                                                    item.id ===
                                                                    question.id
                                                                        ? {
                                                                              ...item,
                                                                              options:
                                                                                  item.options?.map(
                                                                                      (
                                                                                          option,
                                                                                      ) => ({
                                                                                          ...option,
                                                                                          is_correct:
                                                                                              option.id ===
                                                                                              optionId,
                                                                                      }),
                                                                                  ),
                                                                          }
                                                                        : item,
                                                            ),
                                                    })
                                                }
                                            >
                                                {question.options?.map(
                                                    (option, optionIndex) => (
                                                        <div
                                                            key={option.id}
                                                            className="flex min-w-0 items-center gap-2"
                                                        >
                                                            <RadioGroupItem
                                                                id={`answer-${question.id}-${option.id}`}
                                                                value={
                                                                    option.id
                                                                }
                                                                aria-label={`Jadikan opsi ${optionIndex + 1} sebagai kunci jawaban`}
                                                            />
                                                            <Label
                                                                htmlFor={`option-${question.id}-${option.id}`}
                                                                className="sr-only"
                                                            >
                                                                Opsi jawaban{' '}
                                                                {optionIndex +
                                                                    1}
                                                            </Label>
                                                            <Input
                                                                id={`option-${question.id}-${option.id}`}
                                                                value={
                                                                    option.text
                                                                }
                                                                disabled={
                                                                    disabled
                                                                }
                                                                maxLength={500}
                                                                aria-label={`Opsi jawaban ${optionIndex + 1}`}
                                                                placeholder={`Opsi ${optionIndex + 1}`}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updatePage({
                                                                        questions:
                                                                            page.questions.map(
                                                                                (
                                                                                    item,
                                                                                ) =>
                                                                                    item.id !==
                                                                                    question.id
                                                                                        ? item
                                                                                        : {
                                                                                              ...item,
                                                                                              options:
                                                                                                  item.options?.map(
                                                                                                      (
                                                                                                          choice,
                                                                                                      ) =>
                                                                                                          choice.id ===
                                                                                                          option.id
                                                                                                              ? {
                                                                                                                    ...choice,
                                                                                                                    text: event
                                                                                                                        .target
                                                                                                                        .value,
                                                                                                                }
                                                                                                              : choice,
                                                                                                  ),
                                                                                          },
                                                                            ),
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                    ),
                                                )}
                                            </RadioGroup>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </>
                ) : (
                    <div className="grid min-h-60 place-content-center gap-3 rounded-xl border border-dashed p-6 text-center">
                        <h3 className="font-semibold">Belum ada Materi</h3>
                        <p className="text-sm text-muted-foreground">
                            Course tersimpan sebagai draf kosong hingga Materi
                            ditambahkan.
                        </p>
                        <Button
                            type="button"
                            className="mx-auto"
                            disabled={disabled}
                            onClick={addMaterial}
                        >
                            <Plus /> Tambah Materi
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
