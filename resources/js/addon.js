import './field-actions.js';
import AdsIndex from './pages/AdsIndex.vue';
import PlacementsIndex from './pages/PlacementsIndex.vue';
import ReportsIndex from './pages/ReportsIndex.vue';
import PerformanceWidget from './components/PerformanceWidget.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('billboard::AdsIndex', AdsIndex);
    Statamic.$inertia.register('billboard::PlacementsIndex', PlacementsIndex);
    Statamic.$inertia.register('billboard::ReportsIndex', ReportsIndex);

    Statamic.$components.register('billboard-performance-widget', PerformanceWidget);
});
