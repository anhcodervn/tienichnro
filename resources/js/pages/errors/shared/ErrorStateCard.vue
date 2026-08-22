<script setup lang="ts">
import { computed } from "vue";
import { RouterLink } from "vue-router";

type ErrorAction = { href: string; label: string };

defineProps<{
    code: string;
    eyebrow: string;
    title: string;
    description: string;
    primaryAction: ErrorAction;
    secondaryAction: ErrorAction;
    theme?: "admin" | "client";
}>();

const palette = computed(() => ({
    shell: "border-slate-200 bg-white shadow-[0_28px_90px_rgba(15,23,42,0.08)]",
    badge: "border-violet-200 bg-violet-50 text-violet-700",
    title: "text-slate-950",
    text: "text-slate-600",
    accent: "bg-violet-600 text-white hover:bg-violet-500",
    secondary: "border-slate-200 bg-white text-slate-700 hover:bg-slate-50",
}));
</script>

<template>
    <section class="rounded-3xl border p-6 sm:p-8" :class="palette.shell">
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wider" :class="palette.badge">{{ eyebrow }}</span>
            <span class="text-sm font-semibold text-slate-500">{{ code }}</span>
        </div>
        <h1 class="mt-6 text-3xl font-bold tracking-tight" :class="palette.title">{{ title }}</h1>
        <p class="mt-4 max-w-2xl leading-7" :class="palette.text">{{ description }}</p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="primaryAction.href" class="rounded-full px-5 py-3 text-center text-sm font-semibold transition" :class="palette.accent">
                {{ primaryAction.label }}
            </RouterLink>
            <RouterLink :to="secondaryAction.href" class="rounded-full border px-5 py-3 text-center text-sm font-semibold transition" :class="palette.secondary">
                {{ secondaryAction.label }}
            </RouterLink>
        </div>
    </section>
</template>
