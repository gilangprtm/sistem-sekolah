import { Bot, RotateCcw, Send, X } from 'lucide-react';
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

export function AssistantWidget() {
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [history, setHistory] = useState<
        { role: 'user' | 'assistant'; content: string }[]
    >([]);
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
                    history: previousHistory,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                const diagnostic = [
                    data.message,
                    data.error_code ? `Kode: ${data.error_code}` : '',
                    data.request_id ? `ID: ${data.request_id}` : '',
                ]
                    .filter(Boolean)
                    .join(' — ');

                throw new Error(
                    diagnostic || 'Assistant gagal memproses permintaan.',
                );
            }

            setHistory((current) => [
                ...current,
                { role: 'assistant', content: data.message },
            ]);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Assistant tidak tersedia.',
            );
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="fixed right-4 bottom-4 z-50 flex flex-col items-end gap-3 sm:right-6 sm:bottom-6">
            {open && (
                <section
                    aria-label="Chatbot Sistem Sekolah"
                    className="fixed inset-0 flex h-dvh w-full flex-col overflow-hidden border bg-background shadow-xl sm:inset-auto sm:right-0 sm:bottom-0 sm:h-[min(42rem,calc(100vh-3rem))] sm:w-[min(28rem,calc(100vw-3rem))] sm:rounded-xl"
                >
                    <header className="flex items-center justify-between border-b px-4 py-3">
                        <div className="flex items-center gap-2">
                            <Bot
                                className="size-5 text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Assistant Sistem Sekolah
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Percakapan sementara
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setOpen(false)}
                            aria-label="Tutup chatbot"
                        >
                            <X className="size-4" />
                        </Button>
                    </header>
                    <div className="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
                        {history.length === 0 && (
                            <p className="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                                Tanyakan data inventaris sesuai permission akun
                                Anda.
                            </p>
                        )}
                        {history.map((item, index) => (
                            <div
                                key={`${item.role}-${index}`}
                                className={
                                    item.role === 'user'
                                        ? 'ml-auto w-fit max-w-[88%] rounded-lg bg-primary p-2.5 text-sm text-primary-foreground'
                                        : 'mr-auto w-fit max-w-[88%] rounded-lg bg-muted p-2.5 text-sm'
                                }
                            >
                                {item.role === 'assistant'
                                    ? renderAssistantContent(item.content)
                                    : item.content}
                            </div>
                        ))}
                        {error && (
                            <p className="text-sm text-destructive">{error}</p>
                        )}
                    </div>
                    <form className="flex gap-2 border-t p-3" onSubmit={send}>
                        <Input
                            value={message}
                            onChange={(event) => setMessage(event.target.value)}
                            placeholder="Tulis pertanyaan..."
                            maxLength={2000}
                            disabled={loading}
                            aria-label="Pertanyaan untuk chatbot"
                        />
                        <Button
                            size="icon"
                            disabled={loading || !message.trim()}
                            aria-label="Kirim pertanyaan"
                        >
                            <Send className="size-4" />
                        </Button>
                    </form>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="mx-3 mb-3 justify-start"
                        onClick={() => {
                            setHistory([]);
                            setError('');
                        }}
                    >
                        <RotateCcw className="size-3.5" />
                        Mulai chat baru
                    </Button>
                </section>
            )}
            {!open && (
                <Button
                    size="icon"
                    className="size-14 rounded-full shadow-lg"
                    onClick={() => setOpen(true)}
                    aria-label="Buka chatbot"
                >
                    <Bot className="size-6" />
                </Button>
            )}
        </div>
    );
}
