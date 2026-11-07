<script setup lang="ts">
import type { TabsContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TabsContent } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<TabsContentProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <TabsContent
    v-bind="delegatedProps"
    :class="cn('pt-4 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 lg:pt-6', props.class)"
  >
    <slot />
  </TabsContent>
</template>
