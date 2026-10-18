<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Alert,
    Header,
    Button,
    CommandPaletteItem,
    DropdownItem,
    EmptyStateMenu,
    EmptyStateItem,
    Icon,
    Listing,
} from '@statamic/cms/ui';

const props = defineProps({
    placements: { type: Array, required: true },
    columns: { type: Array, required: true },
    actionUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    assetsPublished: { type: Boolean, default: true },
});

async function copyTag(placement) {
    try {
        await navigator.clipboard.writeText(placement.tag);
        Statamic.$toast.success(__('Tag copied'));
    } catch (e) {
        Statamic.$toast.error(__('Copy the tag manually: :tag', { tag: placement.tag }));
    }
}

const reload = () => router.reload();
</script>

<template>
    <Head :title="__('Placements')" />

    <div class="max-w-page mx-auto">
        <Alert
            v-if="!assetsPublished"
            variant="warning"
            class="mb-6"
            :live="false"
            :heading="__('Billboard\'s front-end script isn\'t published')"
            :text="__('Placements that choose ads in the browser, device targeting and reporting need it. Run: php artisan vendor:publish --tag=billboard --force')"
        />

        <template v-if="placements.length === 0">
            <header class="py-8 pt-16 text-center">
                <h1 class="flex items-center justify-center gap-2 text-[25px] font-medium antialiased sm:gap-3">
                    <Icon name="layout-sidebar-right" class="size-5 text-gray-500" />
                    {{ __('Placements') }}
                </h1>
            </header>

            <EmptyStateMenu :heading="__('A placement is a slot in your templates where ads appear.')">
                <EmptyStateItem
                    :href="createUrl"
                    icon="layout-sidebar-right"
                    :heading="__('Create a placement')"
                    :description="__('Give it a name, then add its tag to a template.')"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="__('Placements')" icon="layout-sidebar-right">
                <CommandPaletteItem
                    category="Actions"
                    :text="__('Create placement')"
                    icon="layout-sidebar-right"
                    :url="createUrl"
                    v-slot="{ text, url }"
                >
                    <Button :href="url" :text="text" variant="primary" />
                </CommandPaletteItem>
            </Header>

            <Listing
                :items="placements"
                :columns="columns"
                :action-url="actionUrl"
                :allow-presets="false"
                @refreshing="reload"
            >
                <template #cell-title="{ row: placement }">
                    <div class="min-w-0">
                        <Link :href="placement.edit_url" class="break-words">{{ placement.title }}</Link>
                        <p v-if="placement.description" class="mt-0.5 text-xs text-gray-600 dark:text-gray-400 break-words">
                            {{ placement.description }}
                        </p>
                    </div>
                </template>
                <template #cell-tag="{ row: placement }">
                    <code class="rounded-sm bg-gray-600/10 px-1 py-0.5 font-mono text-[0.85em] dark:bg-white/10">{{ placement.tag }}</code>
                </template>
                <template #prepended-row-actions="{ row: placement }">
                    <DropdownItem :text="__('Edit')" icon="edit" :href="placement.edit_url" />
                    <DropdownItem :text="__('Copy template tag')" icon="clipboard" @click="copyTag(placement)" />
                </template>
            </Listing>
        </template>
    </div>
</template>
