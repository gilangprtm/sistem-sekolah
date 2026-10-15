import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    status: number;
    title: string;
    message: string;
};

export default function ErrorPage({ status, title, message }: Props) {
    return (
        <main className="flex min-h-dvh items-center justify-center bg-muted/30 px-4 py-10">
            <Head title={`${status} — ${title}`} />
            <section
                aria-labelledby="error-title"
                className="w-full max-w-lg rounded-2xl border bg-background p-6 text-center shadow-sm sm:p-10"
            >
                <p className="text-sm font-semibold tracking-widest text-primary">
                    ERROR {status}
                </p>
                <h1
                    id="error-title"
                    className="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    {title}
                </h1>
                <p className="mt-3 text-sm leading-6 text-muted-foreground sm:text-base">
                    {message}
                </p>
                <div className="mt-8 flex justify-center">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.history.back()}
                    >
                        <ArrowLeft aria-hidden="true" />
                        Kembali
                    </Button>
                </div>
            </section>
        </main>
    );
}
