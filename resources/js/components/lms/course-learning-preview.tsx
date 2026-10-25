import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { getAdjacentCoursePage } from '@/lib/lms-course-navigation';
import type {
    CourseContent,
    LearningCourseContent,
    LearningCoursePage,
} from '@/types/lms';

type Props = {
    title: string;
    description: string | null;
    content: LearningCourseContent;
    mode?: 'preview' | 'student';
    initialAnswers?: Record<string, string>;
    status?: string;
    submittedPageIds?: string[];
    onSave?: (pageId: string, answers: Record<string, string>) => void;
    saving?: boolean;
};

export default function CourseLearningPreview({
    title,
    description,
    content,
    mode = 'preview',
    initialAnswers = {},
    status,
    submittedPageIds = [],
    onSave,
    saving = false,
}: Props) {
    const [activeMaterial, setActiveMaterial] = useState(0);
    const [activePageId, setActivePageId] = useState('material');
    const [answers, setAnswers] =
        useState<Record<string, string>>(initialAnswers);

    const isStudent = mode === 'student';
    const isSubmitted = status === 'submitted';
    const material = content.materials[activeMaterial];
    const activePage =
        activePageId === 'material'
            ? material
            : material?.sections.find((section) => section.id === activePageId);
    const isPageSubmitted =
        isSubmitted ||
        (activePage ? submittedPageIds.includes(activePage.id) : false);
    const currentPage = {
        materialIndex: activeMaterial,
        pageId: activePageId,
    };
    const previousPage = getAdjacentCoursePage(
        content.materials,
        currentPage,
        'previous',
    );
    const nextPage = getAdjacentCoursePage(
        content.materials,
        currentPage,
        'next',
    );
    const answer = (questionId: string, value: string) =>
        !isPageSubmitted &&
        !saving &&
        setAnswers((current) => ({ ...current, [questionId]: value }));

    return (
        <section
            aria-label={
                isStudent ? 'Materi pembelajaran' : 'Pratinjau tampilan siswa'
            }
            className="mx-auto grid w-full max-w-6xl min-w-0 gap-6"
        >
            {(!isStudent || !isPageSubmitted) && (
                <div className="rounded-xl border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                    {isStudent
                        ? 'Jawaban pada tab ini akan disimpan saat dikumpulkan.'
                        : 'Mode pratinjau siswa. Jawaban hanya untuk mencoba dan tidak disimpan.'}
                </div>
            )}
            <div className="grid min-w-0 items-start gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <nav
                    aria-label="Daftar materi"
                    className="grid min-w-0 gap-2 rounded-xl border bg-card p-3"
                >
                    <h2 className="px-2 py-1 text-sm font-semibold">
                        Daftar materi
                    </h2>
                    {content.materials.map((item, index) => (
                        <Button
                            key={item.id}
                            type="button"
                            variant={
                                activeMaterial === index ? 'secondary' : 'ghost'
                            }
                            size="default"
                            aria-current={
                                activeMaterial === index ? 'step' : undefined
                            }
                            onClick={() => {
                                setActiveMaterial(index);
                                setActivePageId('material');
                            }}
                            className="justify-start"
                            title={`${index + 1}. ${item.title}`}
                        >
                            <span className="truncate">
                                {index + 1}. {item.title}
                            </span>
                        </Button>
                    ))}
                    {content.materials.length === 0 && (
                        <p className="px-2 text-sm text-muted-foreground">
                            Belum ada materi.
                        </p>
                    )}
                </nav>
                <article className="grid min-w-0 gap-6 rounded-2xl border bg-card p-4 sm:p-6 lg:p-8">
                    <header className="grid min-w-0 gap-2 border-b pb-5">
                        <h2 className="text-2xl font-semibold tracking-tight wrap-anywhere sm:text-3xl">
                            {title}
                        </h2>
                        {description && (
                            <p className="text-base wrap-anywhere text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </header>
                    {material ? (
                        <section
                            key={material.id}
                            className="grid min-w-0 gap-6"
                        >
                            <h3 className="text-xl font-semibold wrap-anywhere sm:text-2xl">
                                {activeMaterial + 1}. {material.title}
                            </h3>
                            {material.sections.length > 0 ? (
                                <Tabs
                                    value={activePageId}
                                    onValueChange={setActivePageId}
                                    className="min-w-0 gap-5"
                                >
                                    <div className="w-full min-w-0 overflow-x-auto pb-1">
                                        <TabsList
                                            variant="line"
                                            aria-label={`Materi dan bagian ${material.title}`}
                                            className="h-auto w-full justify-start gap-1 rounded-none bg-transparent p-0"
                                        >
                                            <TabsTrigger
                                                value="material"
                                                className="h-auto min-h-8 min-w-0 flex-1 rounded-none px-2.5 py-1.5 text-sm wrap-anywhere whitespace-normal"
                                            >
                                                {material.title}
                                            </TabsTrigger>
                                            {material.sections.map(
                                                (section) => (
                                                    <TabsTrigger
                                                        key={section.id}
                                                        value={section.id}
                                                        className="h-auto min-h-8 min-w-0 flex-1 rounded-none px-2.5 py-1.5 text-sm wrap-anywhere whitespace-normal"
                                                    >
                                                        {section.title}
                                                    </TabsTrigger>
                                                ),
                                            )}
                                        </TabsList>
                                    </div>
                                    <TabsContent
                                        value="material"
                                        className="min-w-0"
                                    >
                                        <LearningPage
                                            page={material}
                                            assets={content.assets}
                                            answers={
                                                isPageSubmitted
                                                    ? initialAnswers
                                                    : answers
                                            }
                                            onAnswer={answer}
                                            disabled={isPageSubmitted || saving}
                                        />
                                    </TabsContent>
                                    {material.sections.map((section) => (
                                        <TabsContent
                                            key={section.id}
                                            value={section.id}
                                            className="min-w-0"
                                        >
                                            <LearningPage
                                                page={section}
                                                assets={content.assets}
                                                answers={
                                                    isPageSubmitted
                                                        ? initialAnswers
                                                        : answers
                                                }
                                                onAnswer={answer}
                                                disabled={
                                                    isPageSubmitted || saving
                                                }
                                            />
                                        </TabsContent>
                                    ))}
                                </Tabs>
                            ) : (
                                <LearningPage
                                    page={material}
                                    assets={content.assets}
                                    answers={
                                        isPageSubmitted
                                            ? initialAnswers
                                            : answers
                                    }
                                    onAnswer={answer}
                                    disabled={isPageSubmitted || saving}
                                />
                            )}
                            {isStudent &&
                                activePage &&
                                activePage.questions.length > 0 && (
                                    <div className="border-t pt-5">
                                        <Button
                                            type="button"
                                            className="w-full"
                                            disabled={isPageSubmitted || saving}
                                            onClick={() => {
                                                const pageAnswers =
                                                    Object.fromEntries(
                                                        activePage.questions
                                                            .filter(
                                                                (question) =>
                                                                    answers[
                                                                        question
                                                                            .id
                                                                    ] !==
                                                                    undefined,
                                                            )
                                                            .map((question) => [
                                                                question.id,
                                                                answers[
                                                                    question.id
                                                                ],
                                                            ]),
                                                    );
                                                onSave?.(
                                                    activePage.id,
                                                    pageAnswers,
                                                );
                                            }}
                                        >
                                            {saving
                                                ? 'Mengumpulkan…'
                                                : isPageSubmitted
                                                  ? 'Sudah dikumpulkan'
                                                  : 'Kumpulkan jawaban'}
                                        </Button>
                                    </div>
                                )}
                            <footer className="flex flex-wrap justify-between gap-3 border-t pt-5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={previousPage === null}
                                    onClick={() => {
                                        if (previousPage) {
                                            setActiveMaterial(
                                                previousPage.materialIndex,
                                            );
                                            setActivePageId(
                                                previousPage.pageId,
                                            );
                                        }
                                    }}
                                >
                                    Sebelumnya
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={nextPage === null}
                                    onClick={() => {
                                        if (nextPage) {
                                            setActiveMaterial(
                                                nextPage.materialIndex,
                                            );
                                            setActivePageId(nextPage.pageId);
                                        }
                                    }}
                                >
                                    Selanjutnya
                                </Button>
                            </footer>
                        </section>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Course ini belum memiliki materi.
                        </p>
                    )}
                </article>
            </div>
        </section>
    );
}

function LearningPage({
    page,
    assets,
    answers,
    onAnswer,
    disabled,
}: {
    page: LearningCoursePage;
    assets: CourseContent['assets'];
    answers: Record<string, string>;
    onAnswer: (questionId: string, value: string) => void;
    disabled: boolean;
}) {
    const body = page.body ? (
        <div
            className="text-base leading-relaxed wrap-anywhere [&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:text-lg [&_h3]:font-semibold [&_li]:ml-6 [&_ol]:list-decimal [&_p]:my-2 [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-3 [&_ul]:list-disc"
            dangerouslySetInnerHTML={{ __html: page.body }}
        />
    ) : null;
    const media =
        page.media.length > 0 ? (
            <div className="grid min-w-0 gap-6">
                {page.media.map((media) => (
                    <figure key={media.id} className="grid min-w-0 gap-2">
                        {media.type === 'youtube' ? (
                            <iframe
                                className="aspect-video w-full rounded-xl"
                                src={`https://www.youtube-nocookie.com/embed/${media.url?.match(/^https:\/\/www\.youtube\.com\/watch\?v=([A-Za-z0-9_-]{11})$/)?.[1] ?? ''}`}
                                title={media.caption || 'Video materi'}
                                loading="lazy"
                                referrerPolicy="strict-origin-when-cross-origin"
                                allow="accelerometer; encrypted-media; picture-in-picture"
                                allowFullScreen
                            />
                        ) : media.type === 'image' ? (
                            <img
                                className="max-h-[32rem] max-w-full rounded-xl border object-contain"
                                src={
                                    media.asset_id
                                        ? assets?.[media.asset_id]?.url
                                        : undefined
                                }
                                alt={media.caption || 'Gambar materi'}
                                loading="lazy"
                            />
                        ) : (
                            <a
                                className="w-fit rounded-lg border px-4 py-3 text-sm wrap-anywhere text-primary underline"
                                href={
                                    media.asset_id
                                        ? assets?.[media.asset_id]?.url
                                        : undefined
                                }
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {media.caption || 'Buka PDF'}
                            </a>
                        )}
                        {media.caption && (
                            <figcaption className="text-sm wrap-anywhere text-muted-foreground">
                                {media.caption}
                            </figcaption>
                        )}
                    </figure>
                ))}
            </div>
        ) : null;

    return (
        <div className="grid min-w-0 gap-6">
            {page.layout === 'text_first' ? (
                <>
                    {body}
                    {media}
                </>
            ) : (
                <>
                    {media}
                    {body}
                </>
            )}
            {page.questions.map((question, index) => (
                <fieldset
                    key={question.id}
                    disabled={disabled}
                    className="grid min-w-0 gap-4 rounded-xl border p-4 sm:p-5"
                >
                    <legend className="sr-only">
                        Soal {index + 1}. {question.prompt}
                    </legend>
                    <p className="text-base font-medium wrap-anywhere whitespace-pre-wrap">
                        Soal {index + 1}. {question.prompt}
                    </p>
                    {question.type === 'multiple_choice' && (
                        <RadioGroup
                            value={answers[question.id] ?? ''}
                            onValueChange={(value) =>
                                onAnswer(question.id, value)
                            }
                            aria-label={
                                question.prompt ||
                                `Pilihan jawaban soal ${index + 1}`
                            }
                            className="gap-2"
                        >
                            {question.options?.map((option, optionIndex) => (
                                <label
                                    key={option.id}
                                    htmlFor={`preview-${question.id}-${option.id}`}
                                    className="flex cursor-pointer items-start gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-muted/60 has-[[aria-checked=true]]:bg-muted"
                                >
                                    <RadioGroupItem
                                        id={`preview-${question.id}-${option.id}`}
                                        value={option.id}
                                        className="mt-0.5"
                                    />
                                    <span className="min-w-0 wrap-anywhere whitespace-pre-wrap">
                                        {String.fromCharCode(65 + optionIndex)}.{' '}
                                        {option.text}
                                    </span>
                                </label>
                            ))}
                        </RadioGroup>
                    )}
                    {question.type === 'essay' && (
                        <Textarea
                            value={answers[question.id] ?? ''}
                            onChange={(event) =>
                                onAnswer(question.id, event.target.value)
                            }
                            aria-label={
                                question.prompt ||
                                `Jawaban uraian soal ${index + 1}`
                            }
                            placeholder="Tulis jawabanmu di sini"
                            rows={4}
                            maxLength={10000}
                        />
                    )}
                </fieldset>
            ))}
        </div>
    );
}
