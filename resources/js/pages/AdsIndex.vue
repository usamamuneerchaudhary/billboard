<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Header,
    Button,
    CommandPaletteItem,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    Icon,
    Badge,
    Listing,
} from '@statamic/cms/ui';
import { formatNumber } from '../format.js';

const props = defineProps({
    ads: { type: Array, required: true },
    columns: { type: Array, required: true },
    actionUrl: { type: String, required: true },
    canCreate: { type: Boolean, default: false },
    createUrl: { type: String, required: true },
    canViewReports: { type: Boolean, default: false },
    hasPlacements: { type: Boolean, default: false },
    canManagePlacements: { type: Boolean, default: false },
    createPlacementUrl: { type: String, required: true },
});

const reload = () => router.reload();
</script>

<template>
    <Head :title="__('Ads')" />

    <div class="max-w-page mx-auto">
        <template v-if="ads.length === 0">
            <header class="py-8 pt-16 text-center">
                <h1 class="flex items-center justify-center gap-2 text-[25px] font-medium antialiased sm:gap-3">
                    <Icon name="megaphone" class="size-5 text-gray-500" />
                    {{ __('Ads') }}
                </h1>
            </header>

            <EmptyStateMenu :heading="__('Show image, text or ad network ads in placements across your site.')">
                <EmptyStateItem
                    v-if="!hasPlacements && canManagePlacements"
                    :href="createPlacementUrl"
                    icon="layout-sidebar-right"
                    :heading="__('Create a placement')"
                    :description="__('Start with the slot where ads will appear, such as a sidebar or the space between articles.')"
                />
                <EmptyStateItem
                    v-if="canCreate"
                    :href="createUrl"
                    icon="megaphone"
                    :heading="__('Create an ad')"
                    :description="__('Upload a creative, set where it links to, then choose its placements and schedule.')"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="__('Ads')" icon="megaphone">
                <CommandPaletteItem
                    v-if="canCreate"
                    category="Actions"
                    :text="__('Create ad')"
                    icon="megaphone"
                    :url="createUrl"
                    v-slot="{ text, url }"
                >
                    <Button :href="url" :text="text" variant="primary" />
                </CommandPaletteItem>
            </Header>

            <Listing
                :items="ads"
                :columns="columns"
                :action-url="actionUrl"
                preferences-prefix="billboard.ads"
                @refreshing="reload"
            >
                <template #cell-title="{ row: ad }">
                    <Link :href="ad.edit_url" class="break-words">{{ ad.title }}</Link>
                </template>
                <template #cell-status="{ row: ad }">
                    <Badge :text="ad.status" :color="ad.status_color" size="sm" pill />
                </template>
                <template #cell-placements="{ row: ad }">
                    <span v-if="ad.placements">{{ ad.placements }}</span>
                    <span v-else class="text-gray-500">{{ __('Not placed') }}</span>
                </template>
                <template #cell-impressions="{ row: ad }">{{ formatNumber(ad.impressions) }}</template>
                <template #cell-clicks="{ row: ad }">{{ formatNumber(ad.clicks) }}</template>
                <template #prepended-row-actions="{ row: ad }">
                    <DropdownItem :text="__('Edit')" icon="edit" :href="ad.edit_url" />
                    <DropdownItem v-if="canViewReports" :text="__('View report')" icon="money-graph-bar-increase" :href="ad.report_url" />
                </template>
            </Listing>
        </template>
    </div>
</template>
