<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { PanelLeftClose, PanelLeftOpen } from "lucide-vue-next"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"
import { computed, onMounted, ref } from 'vue'

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
const isHydrated = ref(false)
// Stay stable until mounted: `isMobile`/`state` can diverge between SSR and the client and trigger hydration mismatches.
const showOpenIcon = computed(() => {
  if (!isHydrated.value) {
    return false
  }

  return isMobile.value || state.value === 'collapsed'
})

onMounted(() => {
  isHydrated.value = true
})
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <PanelLeftOpen v-if="showOpenIcon" />
    <PanelLeftClose v-else />
    <span class="sr-only">Toggle sidebar</span>
  </Button>
</template>
