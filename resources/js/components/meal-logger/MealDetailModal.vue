<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type MealItemRow = {
    id: number;
    description: string;
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    sugar_g: number;
    fiber_g: number;
    water_oz: number;
};

const open = defineModel<boolean>('open', { default: false });

defineProps<{
    dateLabel: string;
    items: MealItemRow[];
}>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Meals — {{ dateLabel }}</DialogTitle>
                <DialogDescription>
                    Per-item estimates from your last nutrition pass.
                </DialogDescription>
            </DialogHeader>

            <div v-if="items.length === 0" class="text-muted-foreground py-6 text-sm">
                No meal lines stored for this day yet. Send a food log in the
                chat panel.
            </div>

            <table
                v-else
                class="w-full border-collapse text-sm"
            >
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="py-2 pr-2 font-medium">Item</th>
                        <th class="px-1 py-2 text-right font-medium">Cal</th>
                        <th class="px-1 py-2 text-right font-medium">P</th>
                        <th class="px-1 py-2 text-right font-medium">C</th>
                        <th class="px-1 py-2 text-right font-medium">F</th>
                        <th class="px-1 py-2 text-right font-medium">Sug</th>
                        <th class="px-1 py-2 text-right font-medium">Fib</th>
                        <th class="py-2 pl-1 text-right font-medium">H₂O</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="m in items"
                        :key="m.id"
                        class="border-b border-border/60"
                    >
                        <td class="max-w-[220px] py-2 pr-2 font-medium">
                            {{ m.description }}
                        </td>
                        <td class="px-1 py-2 text-right tabular-nums">
                            {{ m.calories }}
                        </td>
                        <td class="px-1 py-2 text-right tabular-nums">
                            {{ m.protein_g }}
                        </td>
                        <td class="px-1 py-2 text-right tabular-nums">
                            {{ m.carbs_g }}
                        </td>
                        <td class="px-1 py-2 text-right tabular-nums">
                            {{ m.fat_g }}
                        </td>
                        <td class="px-1 py-2 text-right tabular-nums">
                            {{ m.sugar_g }}
                        </td>
                        <td class="px-1 py-2 text-right tabular-nums">
                            {{ m.fiber_g }}
                        </td>
                        <td class="py-2 pl-1 text-right tabular-nums">
                            {{ m.water_oz }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </DialogContent>
    </Dialog>
</template>
