import { copy } from '@/lib/copy';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, type Ref, shallowRef, watch } from 'vue';

export type EvidenceState = 'idle' | 'loading' | 'ready' | 'error';

class EvidenceRefused extends Error {}

/**
 * One evidence sheet of the detail page (ADR-0013), loaded as JSON when the sheet opens. It keeps its own loading and
 * error state, so a failed request never replaces the page or loses unsaved Draft edits. While the sheet is open, every
 * successful Inertia visit (a save, a citation, a feedback) refreshes it quietly, keeping what is shown meanwhile.
 */
// T names the JSON shape the endpoint answers; the response is not validated at runtime.
// eslint-disable-next-line @typescript-eslint/no-unnecessary-type-parameters
export function useItemEvidence<T>(url: () => string, open: Ref<boolean>) {
    const data = shallowRef<T | null>(null);
    const state = ref<EvidenceState>('idle');
    const error = ref<string | null>(null);
    let controller: AbortController | null = null;
    let stopListening: (() => void) | null = null;

    async function load(options: { quiet?: boolean } = {}): Promise<void> {
        controller?.abort();
        const current = new AbortController();
        controller = current;
        const quiet = options.quiet === true && data.value !== null;
        if (!quiet) state.value = 'loading';
        try {
            const response = await fetch(url(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                // A deactivated or signed-out user is redirected to the login page; that is not evidence.
                redirect: 'manual',
                signal: current.signal,
            });
            if (response.type === 'opaqueredirect' || response.status === 401 || response.status === 419) throw new EvidenceRefused(copy.sessionExpired);
            if (response.status === 404) throw new EvidenceRefused(copy.notFoundBody);
            if (!response.ok) throw new EvidenceRefused(copy.errorBody);
            data.value = (await response.json()) as T;
            error.value = null;
            state.value = 'ready';
        } catch (failure) {
            if (current.signal.aborted) return;
            error.value = failure instanceof EvidenceRefused ? failure.message : failure instanceof TypeError ? copy.networkError : copy.errorBody;
            // A quiet refresh that fails keeps the evidence already shown and says so beside it.
            if (!quiet) state.value = 'error';
        }
    }

    function stop(): void {
        controller?.abort();
        controller = null;
        stopListening?.();
        stopListening = null;
    }

    watch(
        open,
        (value) => {
            if (!value) return stop();
            void load();
            stopListening ??= router.on('success', () => void load({ quiet: true }));
        },
        { immediate: true },
    );
    onBeforeUnmount(stop);

    return { data, state, error, load };
}
