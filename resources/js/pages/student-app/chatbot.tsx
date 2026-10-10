import { Head } from '@inertiajs/react';
import { Bot, RotateCcw, Send } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

function renderAssistantContent(content: string) {
    const cleaned = content.replace(/\*\*([^*]+)\*\*/g, '$1');

    return cleaned.split('\n').map((line, lineIndex) => (
        <span key={lineIndex}>
            {lineIndex > 0 && <br />}
            {line}
        </span>
    ));
}

export default function StudentChatbot() {
    const [message, setMessage] = useState('');
    const [history, setHistory] = useState<
        { role: 'user' | 'assistant'; content: string }[]
    >([]);
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    async function send(event: React.FormEvent) {
        event.preventDefault();
        const content = message.trim();

        if (!content || loading) {
            return;
        }

        setLoading(true);
        setError('');
        setMessage('');
        const previousHistory = history;
        setHistory([...previousHistory, { role: 'user', content }]);

        try {
            await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
            const csrfCookie = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');
            const response = await fetch('/assistant/chat', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(csrfCookie
                        ? { 'X-XSRF-TOKEN': decodeURIComponent(csrfCookie) }
                        : {}),
                },
                body: JSON.stringify({
                    message: content,
                    ...(conversationId
                        ? { conversation_id: conversationId }
                        : { history: previousHistory }),
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message ?? 'Chatbot tidak tersedia saat ini.',
                );
            }

            setConversationId(data.conversation_id ?? null);
            setHistory((current) => [
                ...current,
                { role: 'assistant', content: data.message },
            ]);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Chatbot tidak tersedia saat ini.',
            );
        } finally {
            setLoading(false);
        }
    }

    return (
        <>
            <Head title="Chatbot" />
            <div className="flex min-h-[calc(100dvh-8rem)] flex-col gap-4">
                <div className="flex items-center gap-3">
                    <div className="grid size-11 place-items-center rounded-2xl bg-violet-100 text-violet-700">
                        <Bot className="size-6" aria-hidden="true" />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold">Chatbot</h1>
                        <p className="text-sm text-muted-foreground">
                            Tanyakan informasi tentang Sistem Sekolah.
                        </p>
                    </div>
                </div>

                <div className="flex-1 space-y-3 rounded-2xl border bg-background p-3 shadow-sm">
                    {history.length === 0 && (
                        <div className="rounded-xl bg-violet-50 p-4 text-sm text-violet-900">
                            Halo! Saya siap membantu menjawab pertanyaan Anda.
                        </div>
                    )}
                    {history.map((item, index) => (
                        <div
                            key={`${item.role}-${index}`}
                            className={
                                item.role === 'user'
                                    ? 'ml-auto w-fit max-w-[88%] rounded-2xl bg-violet-700 p-3 text-sm text-white'
                                    : 'mr-auto w-fit max-w-[88%] rounded-2xl bg-muted p-3 text-sm'
                            }
                        >
                            {item.role === 'assistant'
                                ? renderAssistantContent(item.content)
                                : item.content}
                        </div>
                    ))}
                    {loading && (
                        <p
                            className="text-sm text-muted-foreground"
                            role="status"
                        >
                            Chatbot sedang mengetik...
                        </p>
                    )}
                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}
                </div>

                <form className="flex gap-2" onSubmit={send}>
                    <Input
                        value={message}
                        onChange={(event) => setMessage(event.target.value)}
                        placeholder="Tulis pertanyaan..."
                        maxLength={2000}
                        disabled={loading}
                        aria-label="Pertanyaan untuk chatbot"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        disabled={loading || !message.trim()}
                        aria-label="Kirim pertanyaan"
                    >
                        <Send className="size-4" />
                    </Button>
                </form>
                <Button
                    type="button"
                    variant="outline"
                    className="self-start"
                    onClick={() => {
                        setHistory([]);
                        setConversationId(null);
                        setError('');
                    }}
                >
                    <RotateCcw className="size-4" />
                    Mulai chat baru
                </Button>
            </div>
        </>
    );
}
