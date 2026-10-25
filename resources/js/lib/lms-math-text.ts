import { Mark } from '@tiptap/core';

// Word/browser clipboard HTML may express the same mark with vertical-align.
declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        superscript: {
            toggleSuperscript: () => ReturnType;
        };
        subscript: {
            toggleSubscript: () => ReturnType;
        };
    }
}

export const SuperscriptExtension = Mark.create({
    name: 'superscript',
    parseHTML() {
        return [
            { tag: 'sup' },
            {
                style: 'vertical-align',
                getAttrs: (value) => (value === 'super' ? null : false),
            },
        ];
    },
    renderHTML() {
        return ['sup', 0];
    },
    addCommands() {
        return {
            toggleSuperscript:
                () =>
                ({ commands }) =>
                    commands.toggleMark(this.name),
        };
    },
});

export const SubscriptExtension = Mark.create({
    name: 'subscript',
    parseHTML() {
        return [
            { tag: 'sub' },
            {
                style: 'vertical-align',
                getAttrs: (value) => (value === 'sub' ? null : false),
            },
        ];
    },
    renderHTML() {
        return ['sub', 0];
    },
    addCommands() {
        return {
            toggleSubscript:
                () =>
                ({ commands }) =>
                    commands.toggleMark(this.name),
        };
    },
});

export function toEditorHtml(value: string, format?: 'html'): string {
    if (format === 'html') {
        return value;
    }

    return value
        .split('\n')
        .map(
            (line) =>
                `<p>${line.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;')}</p>`,
        )
        .join('');
}
