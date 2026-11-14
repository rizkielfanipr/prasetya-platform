<script setup lang="ts">
import { noPaymentLabel, paymentStatuses, type StatusTone } from '@/lib/status';
import type { PaymentStatus } from '@/types/backoffice';
import { computed } from 'vue';

/**
 * The signature of the Rambu Jalur world (DESIGN.md): a payment's line, Created → Pending → Paid, with a
 * "you are here" marker on the station the server reports. Earlier stations are never drawn as passed: a row
 * carries only the current status, so the route must not imply a history it was not given. Failed, Expired,
 * and Cancelled end the line at a terminus. The word beside it is the status; the drawing is decoration.
 */
const props = defineProps<{ status: PaymentStatus | null }>();

const STATIONS = [6, 34, 62] as const;

type Marker = { kind: 'station' | 'terminus'; at: number; tone: StatusTone } | null;

const marker = computed<Marker>(() => {
    switch (props.status) {
        case 'CREATED':
            return { kind: 'station', at: STATIONS[0], tone: 'neutral' };
        case 'PENDING':
            return { kind: 'station', at: STATIONS[1], tone: 'warning' };
        case 'PAID':
            return { kind: 'station', at: STATIONS[2], tone: 'success' };
        case 'FAILED':
        case 'EXPIRED':
            return { kind: 'terminus', at: STATIONS[2], tone: 'danger' };
        case 'CANCELLED':
            return { kind: 'terminus', at: STATIONS[2], tone: 'neutral' };
        default:
            return null;
    }
});

const label = computed(() => (props.status ? paymentStatuses[props.status].label : noPaymentLabel));
const tone = computed<StatusTone>(() => marker.value?.tone ?? 'neutral');
// The line ended without arriving, or never started: its track is broken.
const broken = computed(() => !marker.value || marker.value.kind === 'terminus');

const toneText: Record<StatusTone, string> = {
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    info: 'text-info',
    neutral: 'text-neutral',
};
</script>

<template>
    <span class="inline-flex items-center gap-2.5 whitespace-nowrap" :data-status="status ?? 'NONE'">
        <svg class="h-3.5 w-[68px] shrink-0 overflow-visible" viewBox="0 0 68 14" aria-hidden="true" focusable="false">
            <line x1="6" y1="7" x2="62" y2="7" class="stroke-tint-400" stroke-width="3" stroke-linecap="round" :stroke-dasharray="broken ? '3 4' : undefined" />
            <circle
                v-for="x in STATIONS"
                v-show="!(marker && marker.at === x)"
                :key="x"
                :cx="x"
                cy="7"
                r="3.5"
                class="fill-background stroke-tint-500"
                stroke-width="2"
            />
            <template v-if="marker && marker.kind === 'station'">
                <circle :cx="marker.at" cy="7" r="7" class="fill-tint-100" />
                <circle
                    v-if="marker.tone === 'warning'"
                    :cx="marker.at"
                    cy="7"
                    r="4.25"
                    class="fill-background stroke-current"
                    :class="toneText[marker.tone]"
                    stroke-width="2.5"
                />
                <circle v-else :cx="marker.at" cy="7" r="5" class="fill-current" :class="toneText[marker.tone]" />
            </template>
            <g v-else-if="marker" class="stroke-current" :class="toneText[marker.tone]" stroke-width="2.5" stroke-linecap="round">
                <line :x1="marker.at - 4" y1="3" :x2="marker.at + 4" y2="11" />
                <line :x1="marker.at + 4" y1="3" :x2="marker.at - 4" y2="11" />
            </g>
        </svg>
        <span class="text-[13px] font-bold" :class="status ? toneText[tone] : 'text-muted-foreground'">{{ label }}</span>
    </span>
</template>
