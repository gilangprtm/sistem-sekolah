import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';

export default function Assistant() {
    const [message, setMessage] = useState('');
    const [history, setHistory] = useState<
        { role: 'user' | 'assistant'; content: string }[]
    >([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    async function send(event: React.FormEvent) {
        event.preventDefault();
        const content = message.trim();
        if (!content || loading) return;
        setLoading(true);
        setError('');
        const nextHistory = [...history, { role: 'user' as const, content }];
        setHistory(nextHistory);
        setMessage('');
        try {
            const response = await fetch('/api/v1/assistant/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ message: content, history }),
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(data.message ?? 'Assistant tidak tersedia.');
            setHistory([
                ...nextHistory,
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
        <AppLayout breadcrumbs={[{ title: 'Assistant', href: '/assistant' }]}>
            <Head title="Assistant" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        Assistant Sistem Sekolah
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Tanyakan data sesuai permission akun Anda. Percakapan
                        ini hanya sementara.
                    </p>
                </div>
                <div className="min-h-80 flex-1 space-y-3 rounded-xl border p-4">
                    {history.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Contoh: “Berapa jumlah kursi?”
                        </p>
                    )}
                    {history.map((item, index) => (
                        <div
                            key={`${item.role}-${index}`}
                            className={
                                item.role === 'user'
                                    ? 'ml-auto max-w-[85%] rounded-lg bg-primary p-3 text-primary-foreground'
                                    : 'max-w-[85%] rounded-lg bg-muted p-3'
                            }
                        >
                            {item.content}
                        </div>
                    ))}
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
                    />
                    <Button disabled={loading || !message.trim()}>
                        {loading ? 'Memproses...' : 'Kirim'}
                    </Button>
                </form>
                <Button
                    variant="outline"
                    className="self-start"
                    onClick={() => setHistory([])}
                >
                    Mulai chat baru
                </Button>
            </div>
        </AppLayout>
    );
}
