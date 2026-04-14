<script setup lang="ts">
import { Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggle(): void {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="group h-9 w-9"
                    @click="toggle"
                >
                    <Sun
                        v-if="isDark"
                        class="size-5 opacity-80 group-hover:opacity-100"
                    />
                    <Moon
                        v-else
                        class="size-5 opacity-80 group-hover:opacity-100"
                    />
                    <span class="sr-only">{{
                        isDark ? 'Switch to light mode' : 'Switch to dark mode'
                    }}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent align="end">
                <p>{{ isDark ? 'Light mode' : 'Dark mode' }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
