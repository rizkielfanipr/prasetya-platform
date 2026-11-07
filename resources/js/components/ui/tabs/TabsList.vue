<script setup lang="ts">
import type { TabsListProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TabsList } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<TabsListProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <!-- Scrolls sideways on narrow screens instead of clipping labels or widening the page. -->
  <TabsList
    v-bind="delegatedProps"
    :class="cn('flex max-w-full gap-1 overflow-x-auto border-b', props.class)"
  >
    <slot />
  </TabsList>
</template>
