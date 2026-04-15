<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FoodLibraryChatSidebar from '@/components/meal-logger/FoodLibraryChatSidebar.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    destroy as destroyFoodItem,
    index as myFoodsIndex,
    store as storeFoodItem,
    update as updateFoodItem,
} from '@/routes/my-foods';

type FoodItemRow = {
    id: number;
    name: string;
    unit: string;
    unit_dimension: string;
    unit_quantity: number;
    calories_per_unit: number;
    protein_g_per_unit: number;
    carbs_g_per_unit: number;
    fat_g_per_unit: number;
    sugar_g_per_unit: number;
    fiber_g_per_unit: number;
    water_oz_per_unit: number;
    source: string;
};

const props = defineProps<{
    food_items: FoodItemRow[];
}>();

function cloneRows(items: FoodItemRow[]): FoodItemRow[] {
    return items.map((item) => ({ ...item }));
}

const rows = ref<FoodItemRow[]>(cloneRows(props.food_items));
const newName = ref('');

watch(
    () => props.food_items,
    (items) => {
        rows.value = cloneRows(items);
    },
);

const sortedRows = computed(() =>
    [...rows.value].sort((a, b) => a.name.localeCompare(b.name)),
);

function patchRow(row: FoodItemRow): void {
    router.patch(updateFoodItem.url({ food_item: row.id }), {
        name: row.name,
        unit: row.unit,
        unit_dimension: row.unit_dimension,
        unit_quantity: row.unit_quantity,
        calories_per_unit: row.calories_per_unit,
        protein_g_per_unit: row.protein_g_per_unit,
        carbs_g_per_unit: row.carbs_g_per_unit,
        fat_g_per_unit: row.fat_g_per_unit,
        sugar_g_per_unit: row.sugar_g_per_unit,
        fiber_g_per_unit: row.fiber_g_per_unit,
        water_oz_per_unit: row.water_oz_per_unit,
    }, {
        preserveScroll: true,
    });
}

function addRow(): void {
    const name = newName.value.trim();

    if (name === '') {
        return;
    }

    router.post(storeFoodItem.url(), {
        name,
        unit: 'oz',
        unit_dimension: 'mass',
        unit_quantity: 1,
        calories_per_unit: 0,
        protein_g_per_unit: 0,
        carbs_g_per_unit: 0,
        fat_g_per_unit: 0,
        sugar_g_per_unit: 0,
        fiber_g_per_unit: 0,
        water_oz_per_unit: 0,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newName.value = '';
        },
    });
}

function removeRow(row: FoodItemRow): void {
    if (!window.confirm(`Delete "${row.name}" from My Foods?`)) {
        return;
    }

    router.delete(destroyFoodItem.url({ food_item: row.id }), {
        preserveScroll: true,
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'My Foods', href: myFoodsIndex() },
        ],
    },
});
</script>

<template>
    <Head title="My Foods" />

    <div
        class="flex h-[calc(100vh-8rem)] min-h-[480px] flex-1 gap-0 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
    >
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="dashboard()">Back to dashboard</Link>
                    </Button>
                    <span class="text-sm font-semibold">My Foods & Drinks</span>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        v-model="newName"
                        type="text"
                        placeholder="New food name"
                        class="h-9 w-56 rounded border border-input bg-background px-2 text-sm"
                        @keydown.enter.prevent="addRow"
                    />
                    <Button size="sm" @click="addRow">Add row</Button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full min-w-[1200px] border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-muted/50">
                        <tr class="text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <th class="border-b px-2 py-2">Name</th>
                            <th class="border-b px-2 py-2">Unit</th>
                            <th class="border-b px-2 py-2">Qty</th>
                            <th class="border-b px-2 py-2">Cal</th>
                            <th class="border-b px-2 py-2">Protein</th>
                            <th class="border-b px-2 py-2">Carbs</th>
                            <th class="border-b px-2 py-2">Fat</th>
                            <th class="border-b px-2 py-2">Sugar</th>
                            <th class="border-b px-2 py-2">Fiber</th>
                            <th class="border-b px-2 py-2">Water oz</th>
                            <th class="border-b px-2 py-2">Source</th>
                            <th class="border-b px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in sortedRows"
                            :key="row.id"
                            class="border-b border-border/50"
                        >
                            <td class="px-2 py-1.5">
                                <input
                                    v-model="row.name"
                                    class="h-8 w-full rounded border border-input bg-background px-2 text-xs"
                                    @blur="patchRow(row)"
                                />
                            </td>
                            <td class="px-2 py-1.5">
                                <input
                                    v-model="row.unit"
                                    class="h-8 w-20 rounded border border-input bg-background px-2 text-xs"
                                    @blur="patchRow(row)"
                                />
                            </td>
                            <td class="px-2 py-1.5"><input v-model.number="row.unit_quantity" type="number" step="0.001" class="h-8 w-24 rounded border border-input bg-background px-2 text-xs" @blur="patchRow(row)" /></td>
                            <td class="px-2 py-1.5"><input v-model.number="row.calories_per_unit" type="number" min="0" class="h-8 w-24 rounded border border-input bg-background px-2 text-xs" @blur="patchRow(row)" /></td>
                            <td class="px-2 py-1.5"><input v-model.number="row.protein_g_per_unit" type="number" min="0" step="0.01" class="h-8 w-24 rounded border border-input bg-background px-2 text-xs" @blur="patchRow(row)" /></td>
                            <td class="px-2 py-1.5"><input v-model.number="row.carbs_g_per_unit" type="number" min="0" step="0.01" class="h-8 w-24 rounded border border-input bg-background px-2 text-xs" @blur="patchRow(row)" /></td>
                            <td class="px-2 py-1.5"><input v-model.number="row.fat_g_per_unit" type="number" min="0" step="0.01" class="h-8 w-24 rounded border border-input bg-background px-2 text-xs" @blur="patchRow(row)" /></td>
                            <td class="px-2 py-1.5"><input v-model.number="row.sugar_g_per_unit" type="number" min="0" step="0.01" class="h-8 w-24 rounded border border-input bg-background px-2 text-xs" @blur="patchRow(row)" /></td>
                            <td class="px-2 py-1.5"><input v-model.number="row.fiber_g_per_unit" type="number" min="0" step="0.01" class="h-8 w-24 rounded border border-input bg-background px-2 text-xs" @blur="patchRow(row)" /></td>
                            <td class="px-2 py-1.5"><input v-model.number="row.water_oz_per_unit" type="number" min="0" step="0.01" class="h-8 w-24 rounded border border-input bg-background px-2 text-xs" @blur="patchRow(row)" /></td>
                            <td class="px-2 py-1.5 text-xs text-muted-foreground">{{ row.source }}</td>
                            <td class="px-2 py-1.5">
                                <Button variant="outline" size="sm" @click="removeRow(row)">Delete</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hidden w-[min(100%,380px)] shrink-0 md:flex md:flex-col">
            <FoodLibraryChatSidebar />
        </div>
    </div>
</template>

