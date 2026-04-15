<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

defineProps<{
    monthLabel: string;
    prevHref: string;
    nextHref: string;
    title: string;
}>();
</script>

<template>
    <div
        class="flex h-[calc(100vh-8rem)] min-h-[480px] flex-1 gap-0 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
    >
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="prevHref">Prev</Link>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="nextHref">Next</Link>
                    </Button>
                    <span class="text-sm font-semibold">{{ monthLabel }}</span>
                    <span class="text-muted-foreground text-xs uppercase">{{
                        title
                    }}</span>
                </div>

                <slot name="stats" />
            </div>

            <div class="min-h-0 flex-1 overflow-auto">
                <slot name="table" />
            </div>
        </div>

        <div
            class="hidden w-[min(100%,380px)] shrink-0 overflow-hidden md:flex md:flex-col"
        >
            <slot name="sidebar" />
        </div>
    </div>
</template>
