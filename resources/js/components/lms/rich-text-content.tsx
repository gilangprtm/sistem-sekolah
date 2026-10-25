type Props = {
    value: string;
    format?: 'html';
};

/** HTML is allowlist-sanitized by LmsCourseContent before it reaches this renderer. */
export default function RichTextContent({ value, format }: Props) {
    if (format !== 'html') {
        return <div className="wrap-anywhere whitespace-pre-wrap">{value}</div>;
    }

    return (
        <div
            className="min-w-0 leading-relaxed wrap-anywhere [&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:pl-4 [&_code]:font-mono [&_h2]:my-4 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:my-3 [&_h3]:text-lg [&_h3]:font-semibold [&_hr]:my-4 [&_li]:my-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:bg-muted [&_pre]:p-3 [&_sub]:align-sub [&_sub]:text-[0.75em] [&_sup]:align-super [&_sup]:text-[0.75em] [&_ul]:list-disc [&_ul]:pl-6"
            dangerouslySetInnerHTML={{ __html: value }}
        />
    );
}
