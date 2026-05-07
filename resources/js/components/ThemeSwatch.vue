<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import type { ThemeDefinition } from '@/types';

defineProps<{
    theme: ThemeDefinition;
    selected: boolean;
}>();

defineEmits<{
    select: [slug: ThemeDefinition['slug']];
    navigate: [direction: 1 | -1];
}>();
</script>

<template>
    <button
        type="button"
        role="radio"
        :aria-checked="selected"
        :aria-label="`${theme.name} theme${selected ? ', Active' : ''}`"
        :tabindex="selected ? 0 : -1"
        :class="[
            'travel-touch group flex min-h-28 w-full flex-col justify-between rounded-lg border bg-card p-3 text-left text-card-foreground shadow-sm transition hover:border-ring/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            selected ? 'border-ring ring-2 ring-ring' : 'border-border',
        ]"
        @click="$emit('select', theme.slug)"
        @keydown.enter.prevent="$emit('select', theme.slug)"
        @keydown.space.prevent="$emit('select', theme.slug)"
        @keydown.right.prevent="$emit('navigate', 1)"
        @keydown.down.prevent="$emit('navigate', 1)"
        @keydown.left.prevent="$emit('navigate', -1)"
        @keydown.up.prevent="$emit('navigate', -1)"
    >
        <span class="flex items-center justify-between gap-3">
            <span class="flex gap-1.5" aria-hidden="true">
                <span
                    v-for="swatch in theme.previewSwatches"
                    :key="swatch"
                    class="size-7 rounded-md border border-black/10 shadow-xs"
                    :style="{ backgroundColor: swatch }"
                ></span>
            </span>
            <span
                class="grid size-6 place-items-center rounded-full border border-border text-primary opacity-0 transition group-hover:opacity-60"
                :class="{ 'opacity-100 group-hover:opacity-100': selected }"
                aria-hidden="true"
            >
                <Check class="size-4" />
            </span>
        </span>

        <span class="mt-4 block">
            <span class="block text-sm font-semibold">{{ theme.name }}</span>
            <span class="mt-1 block text-xs leading-5 text-muted-foreground">
                {{ theme.description }}
            </span>
        </span>
    </button>
</template>
