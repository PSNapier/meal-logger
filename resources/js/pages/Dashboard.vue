<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ChatSidebar from '@/components/meal-logger/ChatSidebar.vue';
import type { ChatLine } from '@/components/meal-logger/ChatSidebar.vue';
import MealDetailModal from '@/components/meal-logger/MealDetailModal.vue';
import type { MealItemRow } from '@/components/meal-logger/MealDetailModal.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { update as patchDailyLog } from '@/routes/daily-logs';

type DailyLogPayload = {
    id: number;
    date: string;
    water_oz: number | null;
    fiber_g: number | null;
    calories: number | null;
    eating_window_start: string | null;
    eating_window_end: string | null;
    weight_lbs: number | null;
    meal_items: MealItemRow[];
    chat_messages: ChatLine[];
};

type DayRow = {
    date: string;
    day_name: string;
    daily_log: DailyLogPayload | null;
};

const props = defineProps<{
    year: number;
    month: number;
    month_label: string;
    days: DayRow[];
}>();

function pickDefaultDate(): string {
    const todayIso = new Date().toISOString().slice(0, 10);
    if (props.days.some((d) => d.date === todayIso)) {
        return todayIso;
    }

    return props.days[0]?.date ?? todayIso;
}

const selectedDate = ref(pickDefaultDate());
const mealModalOpen = ref(false);

watch(
    () => [props.year, props.month, props.days],
    () => {
        selectedDate.value = pickDefaultDate();
    },
);

const selectedDay = computed(() =>
    props.days.find((d) => d.date === selectedDate.value),
);

const sidebarMessages = computed(
    () => selectedDay.value?.daily_log?.chat_messages ?? [],
);

const prevMonth = computed(() => {
    const d = new Date(props.year, props.month - 2, 1);

    return { year: d.getFullYear(), month: d.getMonth() + 1 };
});

const nextMonth = computed(() => {
    const d = new Date(props.year, props.month, 1);

    return { year: d.getFullYear(), month: d.getMonth() + 1 };
});

const averages = computed(() => {
    const logs = props.days
        .map((d) => d.daily_log)
        .filter((l): l is DailyLogPayload => !!l);
    const withWater = logs.filter(
        (l) => l.water_oz !== null && l.water_oz !== undefined,
    );
    const withFiber = logs.filter(
        (l) => l.fiber_g !== null && l.fiber_g !== undefined,
    );
    const withCal = logs.filter(
        (l) => l.calories !== null && l.calories !== undefined,
    );
    const withWeight = logs.filter(
        (l) => l.weight_lbs !== null && l.weight_lbs !== undefined,
    );

    const avg = (arr: DailyLogPayload[], key: keyof DailyLogPayload): number | null => {
        if (!arr.length) {
            return null;
        }
        const sum = arr.reduce(
            (s, l) => s + Number((l as Record<string, unknown>)[key]),
            0,
        );

        return Math.round((sum / arr.length) * 10) / 10;
    };

    const lowestWeight =
        withWeight.length === 0
            ? null
            : Math.min(...withWeight.map((l) => Number(l.weight_lbs)));

    return {
        waterOz: avg(withWater, 'water_oz'),
        fiberG: avg(withFiber, 'fiber_g'),
        calories: avg(withCal, 'calories'),
        lowestWeight,
    };
});

function formatDuration(
    start: string | null | undefined,
    end: string | null | undefined,
): string {
    if (!start || !end) {
        return '—';
    }
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    let mins = eh * 60 + em - (sh * 60 + sm);
    if (mins < 0) {
        mins += 24 * 60;
    }
    const h = Math.floor(mins / 60);
    const m = mins % 60;

    return `${h}h ${m}m`;
}

function displayCalories(n: number | null | undefined): string {
    if (n === null || n === undefined) {
        return '';
    }

    return `${n.toLocaleString()} kcal`;
}

function displayOz(n: number | null | undefined): string {
    if (n === null || n === undefined) {
        return '';
    }

    return `${n % 1 === 0 ? n : n.toFixed(1)} oz`;
}

function displayFiber(n: number | null | undefined): string {
    if (n === null || n === undefined) {
        return '';
    }

    return `${n % 1 === 0 ? n : n.toFixed(1)} g`;
}

function displayWeight(n: number | null | undefined): string {
    if (n === null || n === undefined) {
        return '';
    }

    return `${n} lbs`;
}

function rowClasses(day: DayRow): string {
    const base =
        'cursor-pointer border-b border-border/50 transition-colors hover:bg-muted/40';
    if (day.date === selectedDate.value) {
        return `${base} bg-muted/60`;
    }

    return base;
}

function onRowClick(day: DayRow): void {
    selectedDate.value = day.date;
    mealModalOpen.value = true;
}

function patchLog(
    log: DailyLogPayload,
    payload: Record<string, string | null>,
): void {
    router.patch(patchDailyLog.url({ daily_log: log.id }), payload, {
        preserveScroll: true,
    });
}

function onTimeChange(
    log: DailyLogPayload,
    field: 'eating_window_start' | 'eating_window_end',
    value: string,
): void {
    const v = value === '' ? null : value.slice(0, 5);
    patchLog(log, { [field]: v });
}

function onWeightBlur(log: DailyLogPayload, raw: string): void {
    const t = raw.trim();
    patchLog(log, { weight_lbs: t === '' ? null : t });
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Meal log" />

    <div class="flex h-[calc(100vh-8rem)] min-h-[480px] flex-1 gap-0 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="
                                dashboard.url({
                                    year: prevMonth.year,
                                    month: prevMonth.month,
                                })
                            "
                        >
                            Prev
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="
                                dashboard.url({
                                    year: nextMonth.year,
                                    month: nextMonth.month,
                                })
                            "
                        >
                            Next
                        </Link>
                    </Button>
                    <span class="text-sm font-semibold">{{ month_label }}</span>
                </div>
                <div class="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    <span v-if="averages.waterOz !== null">
                        Avg water: {{ displayOz(averages.waterOz) }}
                    </span>
                    <span v-if="averages.fiberG !== null">
                        Avg fiber: {{ displayFiber(averages.fiberG) }}
                    </span>
                    <span v-if="averages.calories !== null">
                        Avg cal: {{ displayCalories(averages.calories) }}
                    </span>
                    <span v-if="averages.lowestWeight !== null">
                        Lowest wt: {{ displayWeight(averages.lowestWeight) }}
                    </span>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full min-w-[900px] border-collapse text-sm">
                    <thead class="bg-muted/50 sticky top-0 z-10">
                        <tr class="text-muted-foreground text-left text-xs font-medium tracking-wide uppercase">
                            <th class="border-b px-2 py-2">Date</th>
                            <th class="border-b px-2 py-2">Day</th>
                            <th class="border-b px-2 py-2">Water</th>
                            <th class="border-b px-2 py-2">Fiber</th>
                            <th class="border-b px-2 py-2">Calories</th>
                            <th class="border-b px-2 py-2">Start</th>
                            <th class="border-b px-2 py-2">End</th>
                            <th class="border-b px-2 py-2">Duration</th>
                            <th class="border-b px-2 py-2">Weight</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="day in days"
                            :key="day.date"
                            :class="rowClasses(day)"
                            @click="onRowClick(day)"
                        >
                            <td class="px-2 py-1.5 tabular-nums">
                                {{ day.date.split('-')[2] }}
                            </td>
                            <td class="px-2 py-1.5">
                                {{ day.day_name }}
                            </td>
                            <td class="text-muted-foreground px-2 py-1.5 tabular-nums">
                                {{
                                    day.daily_log
                                        ? displayOz(day.daily_log.water_oz ?? null)
                                        : ''
                                }}
                            </td>
                            <td class="text-muted-foreground px-2 py-1.5 tabular-nums">
                                {{
                                    day.daily_log
                                        ? displayFiber(day.daily_log.fiber_g ?? null)
                                        : ''
                                }}
                            </td>
                            <td class="text-muted-foreground px-2 py-1.5 tabular-nums">
                                {{
                                    day.daily_log
                                        ? displayCalories(
                                              day.daily_log.calories ?? null,
                                          )
                                        : ''
                                }}
                            </td>
                            <td class="px-1 py-1" @click.stop>
                                <input
                                    v-if="day.daily_log"
                                    type="time"
                                    class="border-input bg-background h-8 w-[7.5rem] rounded border px-1 text-xs"
                                    :value="day.daily_log.eating_window_start ?? ''"
                                    @change="
                                        onTimeChange(
                                            day.daily_log,
                                            'eating_window_start',
                                            ($event.target as HTMLInputElement).value,
                                        )
                                    "
                                />
                            </td>
                            <td class="px-1 py-1" @click.stop>
                                <input
                                    v-if="day.daily_log"
                                    type="time"
                                    class="border-input bg-background h-8 w-[7.5rem] rounded border px-1 text-xs"
                                    :value="day.daily_log.eating_window_end ?? ''"
                                    @change="
                                        onTimeChange(
                                            day.daily_log,
                                            'eating_window_end',
                                            ($event.target as HTMLInputElement).value,
                                        )
                                    "
                                />
                            </td>
                            <td class="text-muted-foreground px-2 py-1.5 text-xs">
                                {{
                                    day.daily_log
                                        ? formatDuration(
                                              day.daily_log.eating_window_start,
                                              day.daily_log.eating_window_end,
                                          )
                                        : '—'
                                }}
                            </td>
                            <td class="px-1 py-1" @click.stop>
                                <input
                                    v-if="day.daily_log"
                                    type="text"
                                    class="border-input bg-background h-8 w-20 rounded border px-1 text-xs"
                                    :value="
                                        day.daily_log.weight_lbs === null ||
                                        day.daily_log.weight_lbs === undefined
                                            ? ''
                                            : String(day.daily_log.weight_lbs)
                                    "
                                    placeholder="n/a"
                                    @blur="
                                        onWeightBlur(
                                            day.daily_log!,
                                            ($event.target as HTMLInputElement).value,
                                        )
                                    "
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hidden w-[min(100%,380px)] shrink-0 md:flex md:flex-col">
            <ChatSidebar
                :log-date="selectedDate"
                :messages="sidebarMessages"
                :has-daily-log="Boolean(selectedDay?.daily_log)"
            />
        </div>
    </div>

    <MealDetailModal
        v-model:open="mealModalOpen"
        :date-label="selectedDate"
        :items="selectedDay?.daily_log?.meal_items ?? []"
    />
</template>
