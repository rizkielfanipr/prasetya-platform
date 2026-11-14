<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon, XIcon } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { Toaster as Sonner } from "vue-sonner"
// vue-sonner 2 ships its layout and position styles separately; without them toasts render unstyled below the page.
import "vue-sonner/style.css"

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--border-radius': 'var(--radius)',
      // Rich colours from the status tokens (docs/design.md): sonner's own green text is 4.26:1, under WCAG AA.
      '--success-bg': 'var(--success-surface)',
      '--success-text': 'var(--success)',
      '--success-border': 'var(--success-surface)',
      '--error-bg': 'var(--danger-surface)',
      '--error-text': 'var(--danger)',
      '--error-border': 'var(--danger-surface)',
      '--warning-bg': 'var(--warning-surface)',
      '--warning-text': 'var(--warning)',
      '--warning-border': 'var(--warning-surface)',
      '--info-bg': 'var(--info-surface)',
      '--info-text': 'var(--info)',
      '--info-border': 'var(--info-surface)',
    }"
    v-bind="props"
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4" />
    </template>
    <template #info-icon>
      <InfoIcon class="size-4" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="size-4" />
    </template>
    <template #error-icon>
      <OctagonXIcon class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <Loader2Icon class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <XIcon class="size-4" />
    </template>
  </Sonner>
</template>
