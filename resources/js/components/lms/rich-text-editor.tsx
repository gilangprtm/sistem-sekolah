import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    RemoveFormatting,
    Strikethrough,
    Underline,
    Undo2,
    Unlink,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import { Separator } from '@/components/ui/separator';

type Props = {
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
    id: string;
};

function ToolbarButton({
    label,
    active,
    disabled,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant={active ? 'secondary' : 'ghost'}
            size="icon"
            className="shrink-0"
            title={label}
            aria-label={label}
            aria-pressed={active}
            disabled={disabled}
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function Divider() {
    return (
        <Separator orientation="vertical" className="mx-1 h-5 self-center" />
    );
}

function isSafeLink(value: string): boolean {
    try {
        const url = new URL(value);

        return (
            /^https?:\/\//i.test(value) &&
            ['http:', 'https:'].includes(url.protocol)
        );
    } catch {
        return false;
    }
}

export default function RichTextEditor({
    value,
    onChange,
    disabled,
    id,
}: Props) {
    'use no memo';

    const [linkOpen, setLinkOpen] = useState(false);
    const [linkUrl, setLinkUrl] = useState('');
    const [linkError, setLinkError] = useState('');
    const editor = useEditor({
        shouldRerenderOnTransaction: true,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                link: {
                    openOnClick: false,
                    defaultProtocol: 'https',
                    isAllowedUri: isSafeLink,
                    HTMLAttributes: {
                        target: '_blank',
                        rel: 'noopener noreferrer',
                    },
                },
            }),
            Placeholder.configure({ placeholder: 'Tulis isi Materi di sini…' }),
        ],
        content: value,
        editable: !disabled,
        editorProps: {
            attributes: {
                id,
                role: 'textbox',
                'aria-label': 'Teks Materi',
                'aria-multiline': 'true',
                'aria-disabled': String(disabled),
                class: 'min-h-72 px-4 py-3 text-base leading-relaxed wrap-anywhere outline-none sm:min-h-80 [&_p]:my-2 [&_h2]:my-4 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:my-3 [&_h3]:text-lg [&_h3]:font-semibold [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-6 [&_ol]:pl-6 [&_li]:my-1 [&_blockquote]:my-3 [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:text-muted-foreground [&_pre]:my-3 [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-3 [&_pre]:text-sm [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:font-mono [&_a]:text-primary [&_a]:underline [&_hr]:my-4 [&_p.is-editor-empty:first-child]:before:pointer-events-none [&_p.is-editor-empty:first-child]:before:float-left [&_p.is-editor-empty:first-child]:before:h-0 [&_p.is-editor-empty:first-child]:before:text-muted-foreground [&_p.is-editor-empty:first-child]:before:content-[attr(data-placeholder)]',
            },
        },
        onUpdate: ({ editor: currentEditor }) => {
            onChange(currentEditor.getHTML());
        },
    });
    useEffect(() => {
        if (
            editor &&
            editor.getHTML() !== value &&
            !(editor.isEmpty && value === '')
        ) {
            editor.commands.setContent(value, { emitUpdate: false });
        }
    }, [editor, value]);

    useEffect(() => {
        editor?.setEditable(!disabled, false);
    }, [editor, disabled]);

    const saveLink = () => {
        if (!editor || disabled) {
            return;
        }

        const url = linkUrl.trim();

        if (!isSafeLink(url)) {
            setLinkError('Gunakan URL lengkap dengan http:// atau https://.');

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
        setLinkOpen(false);
    };

    if (!editor) {
        return (
            <div
                aria-busy="true"
                className="min-h-80 animate-pulse rounded-xl border bg-muted/30"
            />
        );
    }

    const toolbarDisabled = disabled || !editor.isEditable;

    return (
        <div className="min-w-0 overflow-hidden rounded-xl border border-input bg-background focus-within:ring-2 focus-within:ring-ring">
            <div
                role="group"
                aria-label="Pemformatan teks"
                className="flex flex-wrap items-center gap-0.5 border-b bg-muted/30 p-2"
            >
                <ToolbarButton
                    label="Tebal"
                    active={editor.isActive('bold')}
                    disabled={toolbarDisabled}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                >
                    <Bold />
                </ToolbarButton>
                <ToolbarButton
                    label="Miring"
                    active={editor.isActive('italic')}
                    disabled={toolbarDisabled}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                >
                    <Italic />
                </ToolbarButton>
                <ToolbarButton
                    label="Garis bawah"
                    active={editor.isActive('underline')}
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor.chain().focus().toggleUnderline().run()
                    }
                >
                    <Underline />
                </ToolbarButton>
                <ToolbarButton
                    label="Coret"
                    active={editor.isActive('strike')}
                    disabled={toolbarDisabled}
                    onClick={() => editor.chain().focus().toggleStrike().run()}
                >
                    <Strikethrough />
                </ToolbarButton>
                <Divider />
                <ToolbarButton
                    label="Subjudul (H2)"
                    active={editor.isActive('heading', { level: 2 })}
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor.chain().focus().toggleHeading({ level: 2 }).run()
                    }
                >
                    <Heading2 />
                </ToolbarButton>
                <ToolbarButton
                    label="Subjudul kecil (H3)"
                    active={editor.isActive('heading', { level: 3 })}
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor.chain().focus().toggleHeading({ level: 3 }).run()
                    }
                >
                    <Heading3 />
                </ToolbarButton>
                <Divider />
                <ToolbarButton
                    label="Daftar poin"
                    active={editor.isActive('bulletList')}
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                >
                    <List />
                </ToolbarButton>
                <ToolbarButton
                    label="Daftar nomor"
                    active={editor.isActive('orderedList')}
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                >
                    <ListOrdered />
                </ToolbarButton>
                <Divider />
                <ToolbarButton
                    label="Kutipan"
                    active={editor.isActive('blockquote')}
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor.chain().focus().toggleBlockquote().run()
                    }
                >
                    <Quote />
                </ToolbarButton>
                <ToolbarButton
                    label="Blok kode"
                    active={editor.isActive('codeBlock')}
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor.chain().focus().toggleCodeBlock().run()
                    }
                >
                    <Code />
                </ToolbarButton>
                <ToolbarButton
                    label="Garis pemisah"
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor.chain().focus().setHorizontalRule().run()
                    }
                >
                    <Minus />
                </ToolbarButton>
                <Divider />
                <ToolbarButton
                    label="Tambah atau ubah tautan"
                    active={editor.isActive('link')}
                    disabled={toolbarDisabled}
                    onClick={() => {
                        setLinkUrl(
                            editor.getAttributes('link').href ?? 'https://',
                        );
                        setLinkError('');
                        setLinkOpen(true);
                    }}
                >
                    <Link2 />
                </ToolbarButton>
                <ToolbarButton
                    label="Hapus tautan"
                    disabled={toolbarDisabled || !editor.isActive('link')}
                    onClick={() =>
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .unsetLink()
                            .run()
                    }
                >
                    <Unlink />
                </ToolbarButton>
                <ToolbarButton
                    label="Hapus format"
                    disabled={toolbarDisabled}
                    onClick={() =>
                        editor
                            .chain()
                            .focus()
                            .clearNodes()
                            .unsetAllMarks()
                            .run()
                    }
                >
                    <RemoveFormatting />
                </ToolbarButton>
                <Divider />
                <ToolbarButton
                    label="Urungkan"
                    disabled={toolbarDisabled || !editor.can().undo()}
                    onClick={() => editor.chain().focus().undo().run()}
                >
                    <Undo2 />
                </ToolbarButton>
                <ToolbarButton
                    label="Ulangi"
                    disabled={toolbarDisabled || !editor.can().redo()}
                    onClick={() => editor.chain().focus().redo().run()}
                >
                    <Redo2 />
                </ToolbarButton>
            </div>
            <EditorContent editor={editor} />
            <Dialog open={linkOpen && !disabled} onOpenChange={setLinkOpen}>
                <ModalShell
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();

                        if (!disabled) {
                            editor.commands.focus();
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Tautan Materi</DialogTitle>
                        <DialogDescription>
                            Tambahkan tautan HTTP atau HTTPS pada teks yang
                            dipilih.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={`${id}-link`}>URL tautan</Label>
                        <Input
                            id={`${id}-link`}
                            type="url"
                            inputMode="url"
                            autoCapitalize="none"
                            autoCorrect="off"
                            value={linkUrl}
                            aria-invalid={!!linkError}
                            aria-describedby={
                                linkError ? `${id}-link-error` : undefined
                            }
                            onChange={(event) => {
                                setLinkUrl(event.target.value);
                                setLinkError('');
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    saveLink();
                                }
                            }}
                        />
                        <InputError
                            id={`${id}-link-error`}
                            role="alert"
                            message={linkError}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setLinkOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            disabled={disabled}
                            onClick={saveLink}
                        >
                            Simpan tautan
                        </Button>
                    </DialogFooter>
                </ModalShell>
            </Dialog>
        </div>
    );
}
