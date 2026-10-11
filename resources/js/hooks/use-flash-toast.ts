import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        const removeFlashListener = router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });
        const removeHttpExceptionListener = router.on('httpException', () => {
            toast.error('Terjadi kesalahan server. Silakan coba lagi.');
        });
        const removeNetworkErrorListener = router.on('networkError', () => {
            toast.error('Koneksi bermasalah. Periksa jaringan dan coba lagi.');
        });

        return () => {
            removeFlashListener();
            removeHttpExceptionListener();
            removeNetworkErrorListener();
        };
    }, []);
}
