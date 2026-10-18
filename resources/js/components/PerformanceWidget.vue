<script setup>
import { Link } from '@statamic/cms/inertia';
import { Widget } from '@statamic/cms/ui';
import { formatNumber, formatPercent } from '../format.js';

defineProps({
    title: { type: String, required: true },
    days: { type: Number, required: true },
    available: { type: Boolean, required: true },
    totals: { type: Object, required: true },
    rows: { type: Array, required: true },
    reportsUrl: { type: String, required: true },
});
</script>

<template>
    <Widget :title="title" icon="megaphone" :href="reportsUrl">
        <div class="flex flex-1 flex-col gap-4 px-4.5 py-4">
            <p v-if="!available" class="text-sm text-gray-700 dark:text-gray-300">
                {{ __('Reporting isn\'t set up yet. Run php artisan migrate to create the stats table.') }}
            </p>

            <template v-else>
                <dl class="grid grid-cols-3 gap-3">
                    <div class="min-w-0">
                        <dt class="text-xs text-gray-600 dark:text-gray-400">{{ __('Impressions') }}</dt>
                        <dd class="text-lg font-semibold tabular-nums break-words">{{ formatNumber(totals.impressions) }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-gray-600 dark:text-gray-400">{{ __('Clicks') }}</dt>
                        <dd class="text-lg font-semibold tabular-nums break-words">{{ formatNumber(totals.clicks) }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-gray-600 dark:text-gray-400">{{ __('CTR') }}</dt>
                        <dd class="text-lg font-semibold tabular-nums">{{ formatPercent(totals.ctr) }}</dd>
                    </div>
                </dl>

                <ol v-if="rows.length" class="divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <li v-for="row in rows" :key="row.id" class="flex items-baseline justify-between gap-3 py-2">
                        <Link v-if="row.edit_url" :href="row.edit_url" class="min-w-0 truncate" :title="row.title">{{ row.title }}</Link>
                        <span v-else class="min-w-0 truncate text-gray-600 dark:text-gray-400">{{ row.title }}</span>
                        <span class="flex shrink-0 gap-3 tabular-nums text-gray-700 dark:text-gray-300">
                            <span>{{ formatNumber(row.impressions) }}<span class="sr-only"> {{ __('impressions') }}</span></span>
                            <span class="w-14 text-right">{{ formatPercent(row.ctr) }}<span class="sr-only"> {{ __('click-through rate') }}</span></span>
                        </span>
                    </li>
                </ol>

                <p v-else class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('No ad views in the last :count days.', { count: days }) }}
                </p>
            </template>
        </div>
    </Widget>
</template>
