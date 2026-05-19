<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    modelValue: string | null;
    timezones: string[];
    label?: string;
    defaultTimezone?: string | null;
    errorTarget?: string;
    placeholder?: string;
}>(), {
    placeholder: 'Select timezone',
});

defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const sortedTimezones = computed(() => [...props.timezones].sort((a, b) => a.localeCompare(b)));
const value = computed(() => props.modelValue ?? props.defaultTimezone ?? '');

function selectedValue(event: Event): string | null {
    const value = (event.target as HTMLSelectElement).value;

    return value === '' ? null : value;
}
</script>

<template>
    <select
        :aria-label="label ?? placeholder"
        :data-error-target="errorTarget"
        :name="errorTarget"
        :value="value"
        class="travel-touch rounded-md border border-input bg-transparent px-3 text-sm"
        @change="$emit('update:modelValue', selectedValue($event))"
    >
        <option value="">{{ placeholder }}</option>
        <option v-for="timezone in sortedTimezones" :key="timezone" :value="timezone">
            {{ timezone }}
        </option>
    </select>
</template>
