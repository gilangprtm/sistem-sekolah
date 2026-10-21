import { router } from '@inertiajs/react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import ConfirmationModal from '@/components/ui/confirmation-modal';

type ConfirmationOptions = {
    title: string;
    description: string;
};

type ConfirmationContextValue = (
    options: ConfirmationOptions,
) => Promise<boolean>;

const ConfirmationContext = createContext<ConfirmationContextValue | null>(
    null,
);

export function ConfirmationProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const [confirmation, setConfirmation] =
        useState<ConfirmationOptions | null>(null);
    const resolveRef = useRef<((confirmed: boolean) => void) | null>(null);
    const triggerRef = useRef<HTMLElement | null>(null);

    const confirm = useCallback((options: ConfirmationOptions) => {
        resolveRef.current?.(false);
        setConfirmation(options);

        triggerRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;

        return new Promise<boolean>((resolve) => {
            resolveRef.current = resolve;
        });
    }, []);

    const close = useCallback((confirmed: boolean) => {
        resolveRef.current?.(confirmed);
        resolveRef.current = null;
        setConfirmation(null);
    }, []);

    useEffect(() => router.on('navigate', () => close(false)), [close]);

    useEffect(
        () => () => {
            resolveRef.current?.(false);
            resolveRef.current = null;
        },
        [],
    );

    return (
        <ConfirmationContext.Provider value={confirm}>
            {children}
            {confirmation && (
                <ConfirmationModal
                    open
                    title={confirmation.title}
                    description={confirmation.description}
                    onCloseAutoFocus={(event) => {
                        if (triggerRef.current?.isConnected) {
                            event.preventDefault();
                            triggerRef.current.focus();
                        }
                    }}
                    onConfirm={() => close(true)}
                    onCancel={() => close(false)}
                />
            )}
        </ConfirmationContext.Provider>
    );
}

export function useConfirmation() {
    const confirm = useContext(ConfirmationContext);

    if (!confirm) {
        throw new Error(
            'useConfirmation must be used within ConfirmationProvider.',
        );
    }

    return confirm;
}
