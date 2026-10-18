<script setup>
import { computed } from 'vue';
import { Card } from '@statamic/cms/ui';
import { change } from '../format.js';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    current: { type: Number, default: 0 },
    previous: { type: Number, default: null },
    comparisonLabel: { type: String, default: null },
});

const delta = computed(() => (props.previous === null ? null : change(props.current, props.previous)));

const deltaText = computed(() => {
    if (delta.value === null) return __('No earlier data to compare');

    const rounded = Math.round(delta.value * 10) / 10;
    const sign = rounded > 0 ? '+' : '';

    return `${sign}${rounded}% ${props.comparisonLabel ?? ''}`.trim();
});
</script>

<template>
    <Card class="flex min-w-0 flex-col gap-1">
        <span class="text-sm text-gray-700 dark:text-gray-300">{{ label }}</span>
        <span class="text-2xl font-semibold tabular-nums text-gray-925 dark:text-white break-words">{{ value }}</span>
        <span
            class="text-xs"
            :class="{
                'text-green-700 dark:text-green-400': delta !== null && delta > 0,
                'text-red-700 dark:text-red-400': delta !== null && delta < 0,
                'text-gray-600 dark:text-gray-400': delta === null || delta === 0,
            }"
        >{{ deltaText }}</span>
    </Card>
</template>
