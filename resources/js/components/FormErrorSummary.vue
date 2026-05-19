<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    errors: Record<string, string | undefined>;
    max?: number;
}>(), {
    max: 3,
});

const entries = computed(() => Object.entries(props.errors)
    .filter((entry): entry is [string, string] => Boolean(entry[1]))
    .slice(0, props.max));

const hidden = computed(() => Math.max(0, Object.values(props.errors).filter(Boolean).length - props.max));
</script>

<template>
    <div
        v-if="entries.length"
        role="alert"
        class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
    >
        <p class="font-medium">Couldn't save - please fix:</p>
        <ul class="mt-1 list-disc pl-5">
            <li v-for="[field, message] in entries" :key="field">{{ message }}</li>
        </ul>
        <p v-if="hidden > 0" class="mt-1 text-xs text-destructive/80">and {{ hidden }} more.</p>
    </div>
</template>
