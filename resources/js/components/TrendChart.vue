<script setup>
import { computed, ref } from 'vue';
import { formatNumber, formatPercent } from '../format.js';

const props = defineProps({
    series: { type: Array, required: true },
    metric: { type: String, default: 'impressions' },
});

const width = 720;
const height = 220;
const pad = { top: 12, right: 12, bottom: 28, left: 48 };
const active = ref(null);

function valueOf(point) {
    if (props.metric === 'ctr') {
        return point.impressions ? (point.clicks / point.impressions) * 100 : 0;
    }

    return point[props.metric] ?? 0;
}

function format(value) {
    return props.metric === 'ctr' ? formatPercent(value) : formatNumber(Math.round(value));
}

const max = computed(() => {
    const highest = Math.max(0, ...props.series.map(valueOf));
    if (highest === 0) return props.metric === 'ctr' ? 1 : 10;

    const magnitude = 10 ** Math.floor(Math.log10(highest));
    return Math.ceil(highest / magnitude) * magnitude;
});

const plotWidth = width - pad.left - pad.right;
const plotHeight = height - pad.top - pad.bottom;

const bars = computed(() => {
    const count = props.series.length || 1;
    const slot = plotWidth / count;
    const barWidth = Math.max(1, Math.min(28, slot * 0.7));

    return props.series.map((point, index) => {
        const value = valueOf(point);
        const barHeight = (value / max.value) * plotHeight;

        return {
            key: point.date,
            date: point.date,
            value,
            x: pad.left + index * slot + (slot - barWidth) / 2,
            y: pad.top + plotHeight - barHeight,
            width: barWidth,
            height: Math.max(value > 0 ? 1 : 0, barHeight),
            hitX: pad.left + index * slot,
            hitWidth: slot,
        };
    });
});

const gridLines = computed(() =>
    [0, 0.5, 1].map((fraction) => ({
        y: pad.top + plotHeight - fraction * plotHeight,
        label: format(max.value * fraction),
    })),
);

const labelEvery = computed(() => Math.max(1, Math.ceil(props.series.length / 8)));

function shortDate(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

const summary = computed(() => {
    const total = props.series.reduce((sum, point) => sum + valueOf(point), 0);
    return __(':count days of data', { count: props.series.length }) + (props.metric === 'ctr' ? '' : `, ${format(total)} ${__('total')}`);
});
</script>

<template>
    <figure class="m-0">
        <div class="relative w-full overflow-x-auto">
            <svg
                :viewBox="`0 0 ${width} ${height}`"
                class="block h-auto w-full min-w-[480px] text-gray-500"
                role="img"
                :aria-label="summary"
                @mouseleave="active = null"
            >
                <g v-for="line in gridLines" :key="line.y">
                    <line :x1="pad.left" :x2="width - pad.right" :y1="line.y" :y2="line.y" stroke="currentColor" stroke-opacity="0.2" />
                    <text :x="pad.left - 8" :y="line.y + 4" text-anchor="end" font-size="11" fill="currentColor">{{ line.label }}</text>
                </g>

                <g v-for="(bar, index) in bars" :key="bar.key">
                    <rect
                        :x="bar.x"
                        :y="bar.y"
                        :width="bar.width"
                        :height="bar.height"
                        rx="2"
                        class="fill-blue-600 dark:fill-blue-400"
                        :fill-opacity="active === null || active === index ? 1 : 0.45"
                    />
                    <rect
                        :x="bar.hitX"
                        :y="pad.top"
                        :width="bar.hitWidth"
                        :height="plotHeight"
                        fill="transparent"
                        @mouseenter="active = index"
                    />
                    <text
                        v-if="index % labelEvery === 0"
                        :x="bar.hitX + bar.hitWidth / 2"
                        :y="height - 8"
                        text-anchor="middle"
                        font-size="11"
                        fill="currentColor"
                    >{{ shortDate(bar.date) }}</text>
                </g>
            </svg>
        </div>

        <figcaption class="mt-2 min-h-5 text-sm text-gray-700 dark:text-gray-300" aria-live="polite">
            <template v-if="active !== null">
                {{ shortDate(bars[active].date) }}: <strong class="font-semibold">{{ format(bars[active].value) }}</strong>
            </template>
            <template v-else>{{ summary }}</template>
        </figcaption>

        <details class="mt-3 text-sm">
            <summary class="cursor-pointer rounded-sm text-gray-700 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-gray-300">
                {{ __('Show daily figures') }}
            </summary>
            <div class="mt-2 max-h-72 overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th scope="col" class="py-1.5 pe-3 font-medium">{{ __('Date') }}</th>
                            <th scope="col" class="py-1.5 text-right font-medium">{{ __('Value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="bar in bars" :key="bar.key" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td class="py-1.5 pe-3">{{ shortDate(bar.date) }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ format(bar.value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </figure>
</template>
