<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ChatSidebar from '@/components/meal-logger/ChatSidebar.vue';
import type { ChatLine } from '@/components/meal-logger/ChatSidebar.vue';
import MealDetailModal from '@/components/meal-logger/MealDetailModal.vue';
import type { MealItemRow } from '@/components/meal-logger/MealDetailModal.vue';
import { Button } from '@/components/ui/button';
import { isValidIsoDate, localIsoDate } from '@/lib/utils';
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

const SELECTED_DATE_KEY = 'meal-logger:selected-date';
const SELECTED_DATE_ANCHOR_KEY = 'meal-logger:selected-date-anchor';

const props = defineProps<{
    year: number;
    month: number;
    month_label: string;
    days: DayRow[];
}>();

function rememberSelectedDate(date: string): void {
    if (!isValidIsoDate(date)) {
        return;
    }

    localStorage.setItem(SELECTED_DATE_KEY, date);
    localStorage.setItem(SELECTED_DATE_ANCHOR_KEY, localIsoDate());
}

function readRememberedDate(): string | null {
    const remembered = localStorage.getItem(SELECTED_DATE_KEY);
    const anchor = localStorage.getItem(SELECTED_DATE_ANCHOR_KEY);
    const today = localIsoDate();

    if (!remembered || !anchor || anchor !== today) {
        localStorage.removeItem(SELECTED_DATE_KEY);
        localStorage.removeItem(SELECTED_DATE_ANCHOR_KEY);

        return null;
    }

    return isValidIsoDate(remembered) ? remembered : null;
}

function pickDefaultDate(): string {
    const rememberedDate = readRememberedDate();

    if (rememberedDate && props.days.some((d) => d.date === rememberedDate)) {
        return rememberedDate;
    }

    const today = localIsoDate();

    if (props.days.some((d) => d.date === today)) {
        return today;
    }

    return props.days[0]?.date ?? today;
}

const selectedDate = ref(pickDefaultDate());
const dashboardMainPaneEl = ref<HTMLElement | null>(null);
const spreadsheetPaneEl = ref<HTMLElement | null>(null);
const chatSidebarHeight = ref<number | null>(null);
const chatSidebarTopOffset = ref<number>(0);
const mealModalOpen = ref(false);
const page = usePage();
const userDisplayName = computed(() => {
    const name = (page.props.auth as { user?: { name?: string } } | undefined)
        ?.user?.name;

    return typeof name === 'string' && name.trim() !== '' ? name : 'User';
});

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
const chatSidebarStyle = computed(() =>
    chatSidebarHeight.value === null
        ? undefined
        : {
              height: `${chatSidebarHeight.value}px`,
              marginTop: `${chatSidebarTopOffset.value}px`,
          },
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

    const avg = (
        arr: DailyLogPayload[],
        key: keyof DailyLogPayload,
    ): number | null => {
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

function durationMinutes(
    start: string | null | undefined,
    end: string | null | undefined,
): number | null {
    if (!start || !end) {
        return null;
    }

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    let mins = eh * 60 + em - (sh * 60 + sm);

    if (mins < 0) {
        mins += 24 * 60;
    }

    return mins;
}

function percentile(values: number[], p: number): number | null {
    if (!values.length) {
        return null;
    }

    const sorted = [...values].sort((a, b) => a - b);
    const idx = (sorted.length - 1) * p;
    const low = Math.floor(idx);
    const high = Math.ceil(idx);

    if (low === high) {
        return sorted[low];
    }

    const weight = idx - low;

    return sorted[low] + (sorted[high] - sorted[low]) * weight;
}

const metricExtremes = computed(() => {
    const logs = props.days
        .map((d) => d.daily_log)
        .filter((l): l is DailyLogPayload => !!l);
    const numbers = (
        key: keyof Pick<
            DailyLogPayload,
            'water_oz' | 'fiber_g' | 'calories' | 'weight_lbs'
        >,
    ): number[] =>
        logs
            .map((l) => l[key])
            .filter((v): v is number => typeof v === 'number');
    const durationValues = logs
        .map((l) => durationMinutes(l.eating_window_start, l.eating_window_end))
        .filter((v): v is number => v !== null);
    const range = (arr: number[]) => ({
        low: percentile(arr, 0.1),
        high: percentile(arr, 0.9),
    });

    return {
        water: range(numbers('water_oz')),
        fiber: range(numbers('fiber_g')),
        calories: range(numbers('calories')),
        weight: range(numbers('weight_lbs')),
        duration: range(durationValues),
    };
});

const greenScaleClasses = [
    '',
    'bg-green-100',
    'bg-green-200',
    'bg-green-300',
    'bg-green-500',
];
const cyanScaleClasses = [
    '',
    'bg-cyan-100',
    'bg-cyan-200',
    'bg-cyan-300',
    'bg-cyan-500',
];
type MetricPalette = 'green' | 'cyan';

function metricScaleClass(
    value: number | null | undefined,
    low: number | null,
    high: number | null,
    invert = false,
    palette: MetricPalette = 'green',
): string {
    if (
        value === null ||
        value === undefined ||
        low === null ||
        high === null ||
        high <= low
    ) {
        return '';
    }

    const clamped = Math.min(Math.max(value, low), high);
    const normalized = (clamped - low) / (high - low);
    const adjusted = invert ? 1 - normalized : normalized;
    const idx = Math.max(1, Math.min(4, Math.round(adjusted * 4)));
    const scale = palette === 'cyan' ? cyanScaleClasses : greenScaleClasses;

    return scale[idx];
}

function metricBadgeClass(
    value: number | null | undefined,
    low: number | null,
    high: number | null,
    invert = false,
    palette: MetricPalette = 'green',
): string {
    return [
        'inline-flex min-h-6 items-center rounded-full px-2 py-0.5 font-medium text-black [text-shadow:1px_0_0_#fff,-1px_0_0_#fff,0_1px_0_#fff,0_-1px_0_#fff,1px_1px_0_#fff,-1px_1px_0_#fff,1px_-1px_0_#fff,-1px_-1px_0_#fff]',
        metricScaleClass(value, low, high, invert, palette),
    ].join(' ');
}

function metricInputClass(
    value: number | null | undefined,
    low: number | null,
    high: number | null,
    invert = false,
    palette: MetricPalette = 'green',
): string {
    return [
        'h-8 w-20 rounded bg-background px-1 text-xs text-black placeholder:text-black/70 [text-shadow:1px_0_0_#fff,-1px_0_0_#fff,0_1px_0_#fff,0_-1px_0_#fff,1px_1px_0_#fff,-1px_1px_0_#fff,1px_-1px_0_#fff,-1px_-1px_0_#fff]',
        metricScaleClass(value, low, high, invert, palette),
    ].join(' ');
}

function formatDuration(
    start: string | null | undefined,
    end: string | null | undefined,
): string {
    const mins = durationMinutes(start, end);

    if (mins === null) {
        return '—';
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
    rememberSelectedDate(day.date);
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

function onSidebarDateChange(newDate: string): void {
    if (!isValidIsoDate(newDate)) {
        return;
    }

    rememberSelectedDate(newDate);
    const [y, m] = newDate.split('-').map(Number);

    if (y === props.year && m === props.month) {
        selectedDate.value = newDate;
    } else {
        router.visit(dashboard.url({ year: y, month: m }));
    }
}

let midnightCheckTimer: number | null = null;
let spreadsheetResizeObserver: ResizeObserver | null = null;

function syncChatSidebarHeight(): void {
    const spreadsheetHeight = spreadsheetPaneEl.value?.clientHeight ?? null;
    const mainPaneHeight = dashboardMainPaneEl.value?.clientHeight ?? null;

    chatSidebarHeight.value = spreadsheetHeight;
    chatSidebarTopOffset.value =
        spreadsheetHeight !== null && mainPaneHeight !== null
            ? Math.max(0, mainPaneHeight - spreadsheetHeight)
            : 0;
}

onMounted(() => {
    let lastSeenLocalDate = localIsoDate();
    midnightCheckTimer = window.setInterval(() => {
        const today = localIsoDate();

        if (today === lastSeenLocalDate) {
            return;
        }

        lastSeenLocalDate = today;
        localStorage.removeItem(SELECTED_DATE_KEY);
        localStorage.removeItem(SELECTED_DATE_ANCHOR_KEY);
        const [year, month] = today.split('-').map(Number);
        router.visit(dashboard.url({ year, month }));
    }, 60_000);

    syncChatSidebarHeight();
    window.addEventListener('resize', syncChatSidebarHeight);

    if (spreadsheetPaneEl.value && typeof ResizeObserver !== 'undefined') {
        spreadsheetResizeObserver = new ResizeObserver(syncChatSidebarHeight);
        spreadsheetResizeObserver.observe(spreadsheetPaneEl.value);
    }
});

onBeforeUnmount(() => {
    if (midnightCheckTimer !== null) {
        window.clearInterval(midnightCheckTimer);
    }

    window.removeEventListener('resize', syncChatSidebarHeight);
    spreadsheetResizeObserver?.disconnect();
    spreadsheetResizeObserver = null;
});

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

    <div
        class="flex h-[calc(100vh-8rem)] min-h-[480px] flex-1 gap-0 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
    >
        <div
            ref="dashboardMainPaneEl"
            class="flex min-w-0 flex-1 flex-col overflow-hidden"
        >
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
                <div
                    class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                >
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

            <div ref="spreadsheetPaneEl" class="min-h-0 flex-1 overflow-auto">
                <table class="w-full min-w-[900px] border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-muted/50">
                        <tr
                            class="text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
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
                            <td class="px-2 py-1.5 tabular-nums">
                                <span
                                    v-if="day.daily_log"
                                    :class="
                                        metricBadgeClass(
                                            day.daily_log.water_oz,
                                            metricExtremes.water.low,
                                            metricExtremes.water.high,
                                        )
                                    "
                                >
                                    {{
                                        displayOz(
                                            day.daily_log.water_oz ?? null,
                                        )
                                    }}
                                </span>
                            </td>
                            <td class="px-2 py-1.5 tabular-nums">
                                <span
                                    v-if="day.daily_log"
                                    :class="
                                        metricBadgeClass(
                                            day.daily_log.fiber_g,
                                            metricExtremes.fiber.low,
                                            metricExtremes.fiber.high,
                                        )
                                    "
                                >
                                    {{
                                        displayFiber(
                                            day.daily_log.fiber_g ?? null,
                                        )
                                    }}
                                </span>
                            </td>
                            <td class="px-2 py-1.5 tabular-nums">
                                <span
                                    v-if="day.daily_log"
                                    :class="
                                        metricBadgeClass(
                                            day.daily_log.calories,
                                            metricExtremes.calories.low,
                                            metricExtremes.calories.high,
                                            true,
                                        )
                                    "
                                >
                                    {{
                                        displayCalories(
                                            day.daily_log.calories ?? null,
                                        )
                                    }}
                                </span>
                            </td>
                            <td class="px-1 py-1" @click.stop>
                                <input
                                    v-if="day.daily_log"
                                    type="time"
                                    class="h-8 w-[7.5rem] rounded border border-input bg-background px-1 text-xs"
                                    :value="
                                        day.daily_log.eating_window_start ?? ''
                                    "
                                    @change="
                                        onTimeChange(
                                            day.daily_log,
                                            'eating_window_start',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                            </td>
                            <td class="px-1 py-1" @click.stop>
                                <input
                                    v-if="day.daily_log"
                                    type="time"
                                    class="h-8 w-[7.5rem] rounded border border-input bg-background px-1 text-xs"
                                    :value="
                                        day.daily_log.eating_window_end ?? ''
                                    "
                                    @change="
                                        onTimeChange(
                                            day.daily_log,
                                            'eating_window_end',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                            </td>
                            <td class="px-2 py-1.5 text-xs tabular-nums">
                                <span
                                    v-if="day.daily_log"
                                    :class="
                                        metricBadgeClass(
                                            durationMinutes(
                                                day.daily_log
                                                    .eating_window_start,
                                                day.daily_log.eating_window_end,
                                            ),
                                            metricExtremes.duration.low,
                                            metricExtremes.duration.high,
                                            true,
                                            'cyan',
                                        )
                                    "
                                >
                                    {{
                                        formatDuration(
                                            day.daily_log.eating_window_start,
                                            day.daily_log.eating_window_end,
                                        )
                                    }}
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-1 py-1" @click.stop>
                                <input
                                    v-if="day.daily_log"
                                    type="text"
                                    :class="
                                        metricInputClass(
                                            day.daily_log.weight_lbs,
                                            metricExtremes.weight.low,
                                            metricExtremes.weight.high,
                                            true,
                                            'cyan',
                                        )
                                    "
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
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            class="hidden w-[min(100%,380px)] shrink-0 overflow-hidden md:flex md:flex-col"
            :style="chatSidebarStyle"
        >
            <ChatSidebar
                :log-date="selectedDate"
                :messages="sidebarMessages"
                :has-daily-log="Boolean(selectedDay?.daily_log)"
                :user-name="userDisplayName"
                @date-change="onSidebarDateChange"
            />
        </div>
    </div>

    <MealDetailModal
        v-model:open="mealModalOpen"
        :date-label="selectedDate"
        :items="selectedDay?.daily_log?.meal_items ?? []"
    />
</template>
