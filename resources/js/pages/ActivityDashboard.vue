<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DashboardChatSidebar from '@/components/meal-logger/DashboardChatSidebar.vue';
import DataGridShell from '@/components/meal-logger/DataGridShell.vue';
import { isValidIsoDate, localIsoDate } from '@/lib/utils';
import { activityDashboard } from '@/routes';
import { store as activityChatStore } from '@/routes/activity-chat';
import { upsert as patchMeasurement } from '@/routes/measurements';

type ChatLine = { id: number; role: string; content: string };

type ActivityLog = {
    id: number;
    date: string;
    total_sessions: number;
    total_minutes: number;
    calories_burned: number;
    updated_at: string | null;
};

type Measurement = {
    id: number;
    weight_lbs: number | null;
    updated_at: string | null;
};

type DayRow = {
    date: string;
    day_name: string;
    activity_log: ActivityLog | null;
    measurement: Measurement | null;
    chat_messages: ChatLine[];
};

const SELECTED_DATE_KEY = 'meal-logger:activity:selected-date';
const SELECTED_DATE_ANCHOR_KEY = 'meal-logger:activity:selected-date-anchor';

const props = defineProps<{
    year: number;
    month: number;
    month_label: string;
    days: DayRow[];
}>();

const page = usePage();
const userDisplayName = computed(() => {
    const name = (page.props.auth as { user?: { name?: string } } | undefined)
        ?.user?.name;

    return typeof name === 'string' && name.trim() !== '' ? name : 'User';
});

function rememberSelectedDate(date: string): void {
    if (!isValidIsoDate(date)) return;
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
watch(
    () => [props.year, props.month, props.days],
    () => {
        selectedDate.value = pickDefaultDate();
    },
);

const selectedDay = computed(() =>
    props.days.find((d) => d.date === selectedDate.value),
);
const sidebarMessages = computed(() => selectedDay.value?.chat_messages ?? []);
const chatAction = computed(() => activityChatStore());

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
        .map((d) => d.activity_log)
        .filter(Boolean) as ActivityLog[];
    const sum = (key: keyof ActivityLog) =>
        logs.reduce((n, row) => n + Number(row[key]), 0);
    const dayCount = Math.max(props.days.length, 1);

    return {
        sessions: sum('total_sessions') / dayCount,
        minutes: sum('total_minutes') / dayCount,
        calories: sum('calories_burned') / dayCount,
    };
});

function formatAverage(value: number): string {
    return value.toLocaleString(undefined, {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    });
}

function onSidebarDateChange(newDate: string): void {
    if (!isValidIsoDate(newDate)) return;
    rememberSelectedDate(newDate);

    const [y, m] = newDate.split('-').map(Number);
    if (y === props.year && m === props.month) {
        selectedDate.value = newDate;
    } else {
        router.visit(activityDashboard.url({ year: y, month: m }));
    }
}

function onRowClick(day: DayRow): void {
    selectedDate.value = day.date;
    rememberSelectedDate(day.date);
}

function onWeightBlur(day: DayRow, raw: string): void {
    const expected = day.measurement?.updated_at ?? undefined;
    router.patch(
        patchMeasurement.url(),
        {
            log_date: day.date,
            weight_lbs: raw.trim() === '' ? null : raw.trim(),
            expected_updated_at: expected,
        },
        { preserveScroll: true },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Activity',
                href: activityDashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Activity dashboard" />

    <DataGridShell
        title="Activity"
        :month-label="month_label"
        :prev-href="activityDashboard.url(prevMonth)"
        :next-href="activityDashboard.url(nextMonth)"
    >
        <template #stats>
            <div
                class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <span>Avg sessions/day: {{ formatAverage(averages.sessions) }}</span>
                <span>Avg minutes/day: {{ formatAverage(averages.minutes) }}</span>
                <span>Avg kcal/day: {{ formatAverage(averages.calories) }}</span>
            </div>
        </template>

        <template #table>
            <table class="w-full min-w-[760px] border-collapse text-sm">
                <thead class="sticky top-0 z-10 bg-muted/50">
                    <tr
                        class="text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <th class="border-b px-2 py-2">Date</th>
                        <th class="border-b px-2 py-2">Day</th>
                        <th class="border-b px-2 py-2">Sessions</th>
                        <th class="border-b px-2 py-2">Minutes</th>
                        <th class="border-b px-2 py-2">Calories</th>
                        <th class="border-b px-2 py-2">Weight</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="day in days"
                        :key="day.date"
                        class="cursor-pointer border-b border-border/50 transition-colors hover:bg-muted/40"
                        :class="{ 'bg-muted/60': day.date === selectedDate }"
                        @click="onRowClick(day)"
                    >
                        <td class="px-2 py-1.5 tabular-nums">
                            {{ day.date.split('-')[2] }}
                        </td>
                        <td class="px-2 py-1.5">{{ day.day_name }}</td>
                        <td class="px-2 py-1.5 tabular-nums">
                            {{ day.activity_log?.total_sessions ?? 0 }}
                        </td>
                        <td class="px-2 py-1.5 tabular-nums">
                            {{ day.activity_log?.total_minutes ?? 0 }}
                        </td>
                        <td class="px-2 py-1.5 tabular-nums">
                            {{ day.activity_log?.calories_burned ?? 0 }}
                        </td>
                        <td class="px-1 py-1" @click.stop>
                            <input
                                type="text"
                                class="h-8 w-20 rounded border border-input bg-background px-1 text-xs"
                                :value="day.measurement?.weight_lbs ?? ''"
                                placeholder="n/a"
                                @blur="
                                    onWeightBlur(
                                        day,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </template>

        <template #sidebar>
            <DashboardChatSidebar
                :log-date="selectedDate"
                :messages="sidebarMessages"
                :chat-action="chatAction"
                :has-daily-log="Boolean(selectedDay?.activity_log)"
                :user-name="userDisplayName"
                title="Activity chat"
                placeholder="ex: 2 runs today, 45 min total, around 500 calories burned"
                empty-state-text="Describe workouts, sessions, and duration for this day."
                @date-change="onSidebarDateChange"
            />
        </template>
    </DataGridShell>
</template>
