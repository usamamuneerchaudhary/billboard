<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Alert,
    Button,
    Card,
    Field,
    Header,
    Heading,
    Icon,
    Select,
    Table,
    TableCell,
    TableColumn,
    TableColumns,
    TableRow,
    TableRows,
    ToggleGroup,
    ToggleItem,
} from '@statamic/cms/ui';
import TrendChart from '../components/TrendChart.vue';
import StatTile from '../components/StatTile.vue';
import { formatNumber, formatPercent } from '../format.js';

const props = defineProps({
    available: { type: Boolean, required: true },
    trackingEnabled: { type: Boolean, required: true },
    filters: { type: Object, required: true },
    ranges: { type: Array, required: true },
    placementOptions: { type: Array, required: true },
    adOptions: { type: Array, required: true },
    period: { type: Object, required: true },
    totals: { type: Object, required: true },
    previous: { type: Object, required: true },
    series: { type: Array, required: true },
    rows: { type: Array, required: true },
    indexUrl: { type: String, required: true },
    exportUrl: { type: String, required: true },
    settingsUrl: { type: String, required: true },
    migrationCommand: { type: String, required: true },
});

const metric = ref('impressions');
const loading = ref(false);
const sortKey = ref('impressions');

const hasData = computed(() => props.totals.impressions > 0 || props.totals.clicks > 0);
const comparisonLabel = computed(() => __('vs previous :count days', { count: props.filters.range }));

const periodLabel = computed(() => {
    const format = (date) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
    return `${format(props.period.from)} – ${format(props.period.to)}`;
});

const sortedRows = computed(() => [...props.rows].sort((a, b) => b[sortKey.value] - a[sortKey.value]));

function applyFilters(changes) {
    const query = { ...props.filters, ...changes };

    Object.keys(query).forEach((key) => {
        if (query[key] === null || query[key] === '' || query[key] === undefined) delete query[key];
    });

    router.get(props.indexUrl, query, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}
</script>

<template>
    <Head :title="__('Ad reports')" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Ad reports')" icon="money-graph-bar-increase">
            <Button
                v-if="available && hasData"
                as="a"
                :href="exportUrl"
                icon="download"
                :text="__('Export CSV')"
            />
        </Header>

        <Alert
            v-if="!available"
            variant="warning"
            class="mb-6"
            :live="false"
        >
            <Heading :text="__('Reporting isn\'t set up yet')" />
            <p class="mt-1 text-sm">
                {{ __('Billboard stores daily totals in a database table. Create it by running this command on your server, then reload this page:') }}
            </p>
            <code class="mt-2 inline-block rounded-sm bg-gray-600/10 px-1.5 py-0.5 font-mono text-sm dark:bg-white/10">{{ migrationCommand }}</code>
        </Alert>

        <Alert
            v-else-if="!trackingEnabled"
            variant="default"
            class="mb-6"
            :live="false"
        >
            <p class="text-sm">
                {{ __('Counting is switched off, so no new impressions or clicks are being recorded.') }}
                <Link :href="settingsUrl" class="underline">{{ __('Change this in settings') }}</Link>
            </p>
        </Alert>

        <div class="mb-6 flex flex-wrap items-end gap-3" :aria-busy="loading">
            <Field class="w-full sm:w-44" :label="__('Period')">
                <Select
                    :model-value="filters.range"
                    :options="ranges"
                    @update:model-value="(value) => applyFilters({ range: value })"
                />
            </Field>
            <Field class="w-full sm:w-56" :label="__('Placement')">
                <Select
                    clearable
                    :placeholder="__('All placements')"
                    :model-value="filters.placement"
                    :options="placementOptions"
                    @update:model-value="(value) => applyFilters({ placement: value })"
                />
            </Field>
            <Field class="w-full sm:w-64" :label="__('Ad')">
                <Select
                    clearable
                    adaptive-width
                    :placeholder="__('All ads')"
                    :model-value="filters.ad"
                    :options="adOptions"
                    @update:model-value="(value) => applyFilters({ ad: value })"
                />
            </Field>
            <p class="text-sm text-gray-600 dark:text-gray-400 sm:ms-auto sm:pb-2">{{ periodLabel }}</p>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatTile
                :label="__('Impressions')"
                :value="formatNumber(totals.impressions)"
                :current="totals.impressions"
                :previous="previous.impressions"
                :comparison-label="comparisonLabel"
            />
            <StatTile
                :label="__('Clicks')"
                :value="formatNumber(totals.clicks)"
                :current="totals.clicks"
                :previous="previous.clicks"
                :comparison-label="comparisonLabel"
            />
            <StatTile
                :label="__('Click-through rate')"
                :value="formatPercent(totals.ctr)"
                :current="totals.ctr"
                :previous="previous.impressions ? previous.ctr : null"
                :comparison-label="comparisonLabel"
            />
        </div>

        <Card class="mb-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <Heading :text="__('By day')" size="lg" :level="2" />
                <ToggleGroup v-model="metric" size="sm" required :aria-label="__('Chart metric')">
                    <ToggleItem value="impressions" :label="__('Impressions')" />
                    <ToggleItem value="clicks" :label="__('Clicks')" />
                    <ToggleItem value="ctr" :label="__('CTR')" />
                </ToggleGroup>
            </div>
            <TrendChart :series="series" :metric="metric" />
        </Card>

        <Card>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <Heading :text="__('By ad')" size="lg" :level="2" />
                <ToggleGroup v-if="rows.length > 1" v-model="sortKey" size="sm" required :aria-label="__('Sort ads by')">
                    <ToggleItem value="impressions" :label="__('Impressions')" />
                    <ToggleItem value="clicks" :label="__('Clicks')" />
                    <ToggleItem value="ctr" :label="__('CTR')" />
                </ToggleGroup>
            </div>

            <div v-if="rows.length === 0" class="flex flex-col items-center gap-2 py-10 text-center">
                <Icon name="money-graph-bar-increase" class="size-6 text-gray-400" />
                <p class="max-w-md text-sm text-gray-700 dark:text-gray-300">
                    {{ available
                        ? __('No impressions or clicks in this period. Impressions are counted once an ad has been on screen for a second.')
                        : __('Figures appear here once reporting is set up and ads start getting views.') }}
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('Ad') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Impressions') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Clicks') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('CTR') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="row in sortedRows" :key="row.id">
                            <TableCell class="min-w-48 break-words">
                                <Link v-if="row.edit_url" :href="row.edit_url">{{ row.title }}</Link>
                                <span v-else class="text-gray-600 dark:text-gray-400">{{ row.title }}</span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{ formatNumber(row.impressions) }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ formatNumber(row.clicks) }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ formatPercent(row.ctr) }}</TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </div>
        </Card>
    </div>
</template>
