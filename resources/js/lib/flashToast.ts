import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    router.on('httpException', (event) => {
        const response = (event as CustomEvent).detail?.response;

        if (response?.status !== 419) {
            return;
        }

        toast.error('Your session expired - refresh and try again.', { duration: 8000 });
        event.preventDefault();
    });

    router.on('networkError', () => {
        toast.error("Couldn't save - check your connection.");
    });
}
