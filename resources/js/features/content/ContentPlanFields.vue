<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Input } from '@/components/ui/input';
import type { PlanOptions } from '@/types/content';
import { computed } from 'vue';

/**
 * Title, planned time, pillar, campaign, and topic of an item. Inactive pillars and campaigns are offered only when
 * the item already has them, matching what the server accepts.
 */
const props = defineProps<{
    idPrefix: string;
    options: PlanOptions;
    errors: Partial<Record<'title' | 'planned_at' | 'pillar' | 'campaign' | 'topic', string>>;
}>();
const title = defineModel<string>('title', { required: true });
const plannedAt = defineModel<string>('plannedAt', { required: true });
const pillar = defineModel<string>('pillar', { required: true });
const campaign = defineModel<string>('campaign', { required: true });
const topic = defineModel<string>('topic', { required: true });

const initialPillar = pillar.value;
const initialCampaign = campaign.value;
const pillars = computed(() => props.options.pillars.filter((p) => p.isActive || p.id === initialPillar));
const campaigns = computed(() => props.options.campaigns.filter((c) => c.isActive || c.id === initialCampaign));
const selectClass = 'h-11 w-full rounded-md border border-input bg-card px-3 text-sm aria-invalid:border-destructive';
</script>

<template>
    <div class="space-y-4">
        <FormField
            :id="`${idPrefix}-title`"
            v-slot="{ describedBy, invalid }"
            label="Judul kerja"
            :error="errors.title"
            hint="Untuk tim saja; tidak ikut diposting."
        >
            <Input
                :id="`${idPrefix}-title`"
                v-model="title"
                class="h-11"
                maxlength="160"
                autocomplete="off"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>
        <FormField :id="`${idPrefix}-planned`" v-slot="{ describedBy, invalid }" label="Rencana posting (WIB)" :error="errors.planned_at">
            <Input :id="`${idPrefix}-planned`" v-model="plannedAt" type="datetime-local" class="h-11" :aria-describedby="describedBy" :aria-invalid="invalid" />
        </FormField>
        <div class="grid gap-4 sm:grid-cols-2">
            <FormField :id="`${idPrefix}-pillar`" v-slot="{ describedBy, invalid }" label="Pilar" :error="errors.pillar">
                <select :id="`${idPrefix}-pillar`" v-model="pillar" :class="selectClass" :aria-describedby="describedBy" :aria-invalid="invalid">
                    <option value="">Tanpa pilar</option>
                    <option v-for="option in pillars" :key="option.id" :value="option.id">{{ option.name }}{{ option.isActive ? '' : ' (nonaktif)' }}</option>
                </select>
            </FormField>
            <FormField :id="`${idPrefix}-campaign`" v-slot="{ describedBy, invalid }" label="Kampanye" :error="errors.campaign">
                <select :id="`${idPrefix}-campaign`" v-model="campaign" :class="selectClass" :aria-describedby="describedBy" :aria-invalid="invalid">
                    <option value="">Tanpa kampanye</option>
                    <option v-for="option in campaigns" :key="option.id" :value="option.id">{{ option.name }}{{ option.isActive ? '' : ' (nonaktif)' }}</option>
                </select>
            </FormField>
        </div>
        <FormField
            :id="`${idPrefix}-topic`"
            v-slot="{ describedBy, invalid }"
            label="Topik"
            :error="errors.topic"
            hint="Kata kunci singkat, misalnya SEO. Dipakai untuk memeriksa jarak pengulangan topik."
        >
            <Input
                :id="`${idPrefix}-topic`"
                v-model="topic"
                class="h-11"
                maxlength="80"
                autocomplete="off"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>
    </div>
</template>
