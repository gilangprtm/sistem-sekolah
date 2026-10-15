import { router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    title: string;
    fallbackHref: string;
    children: ReactNode;
};

export default function SecondaryPageShell({
    title,
    fallbackHref,
    children,
}: Props) {
    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(fallbackHref);
    };

    return (
        <>
            <header className="sticky top-0 z-40 flex items-center gap-3 border-b bg-background/95 px-4 pt-[max(.75rem,env(safe-area-inset-top))] pb-3 backdrop-blur">
                <Button
                    variant="ghost"
                    size="icon"
                    type="button"
                    aria-label="Kembali"
                    title="Kembali"
                    onClick={goBack}
                >
                    <ArrowLeft aria-hidden="true" />
                </Button>
                <h1 className="text-sm font-semibold">{title}</h1>
            </header>
            {children}
        </>
    );
}
