import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { onBeforeUnmount, ref } from 'vue';

export type FilterValues = Record<string, string | null | undefined>;

/** Query string → server → props. The URL holds filter state, so links and refresh keep it. */
export function useUrlFilters(path: () => string, current: () => FilterValues, only: string[]) {
    const loading = ref(false);
    const failed = ref(false);
    let cancelStale: (() => void) | null = null;

    function apply(changes: FilterValues): void {
        cancelStale?.();
        failed.value = false;

        const query = Object.fromEntries(
            Object.entries({ ...current(), ...changes }).filter(([, value]) => value !== null && value !== undefined && value !== ''),
        );

        router.get(path(), query, {
            only,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onCancelToken: (token) => {
                cancelStale = () => token.cancel();
            },
            onStart: () => {
                loading.value = true;
            },
            onHttpException: () => {
                failed.value = true;
            },
            onNetworkError: () => {
                failed.value = true;
            },
            onFinish: () => {
                loading.value = false;
                cancelStale = null;
            },
        });
    }

    const applyDebounced = useDebounceFn(apply, 300);

    onBeforeUnmount(() => cancelStale?.());

    return { apply, applyDebounced, loading, failed };
}
