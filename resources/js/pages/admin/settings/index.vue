<script setup lang="ts">
import { ref } from 'vue';
import BrandingSettings from './BrandingSettings.vue';
import ContactSettings from './ContactSettings.vue';
import CustomCodeSettings from './CustomCodeSettings.vue';
import GeneralSettings from './GeneralSettings.vue';
import SeoTrackingSettings from './SeoTrackingSettings.vue';

const tabs = [
    { key: 'general', label: 'Thông tin website', component: GeneralSettings },
    { key: 'branding', label: 'Nhận diện', component: BrandingSettings },
    { key: 'contact', label: 'Liên hệ', component: ContactSettings },
    { key: 'seo', label: 'SEO & tracking', component: SeoTrackingSettings },
    { key: 'custom-code', label: 'Mã tùy chỉnh', component: CustomCodeSettings },
];
const activeTab = ref('general');
</script>

<template>
    <section class="space-y-5">
        <h1 class="text-2xl font-bold text-slate-950">Cấu hình website</h1>
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Cấu hình">
            <button
                v-for="tab in tabs"
                :id="`setting-tab-${tab.key}`"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="activeTab === tab.key"
                :aria-controls="`setting-panel-${tab.key}`"
                :class="activeTab === tab.key ? 'bg-emerald-700 text-white' : 'bg-white text-slate-700'"
                class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>
        <template v-for="tab in tabs" :key="tab.key">
            <div v-if="activeTab === tab.key" :id="`setting-panel-${tab.key}`" role="tabpanel" :aria-labelledby="`setting-tab-${tab.key}`">
                <component :is="tab.component" />
            </div>
        </template>
    </section>
</template>
