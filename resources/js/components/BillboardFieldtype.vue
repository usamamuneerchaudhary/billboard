<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

const config = computed(() => ({
    ...(attrs.config ?? {}),
    max_items: 1,
    dictionary: { type: 'ad_placements' },
}));
</script>

<template>
    <dictionary-fieldtype v-bind="attrs" :config="config" />
</template>
