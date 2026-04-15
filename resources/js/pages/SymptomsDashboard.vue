<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DashboardChatSidebar from '@/components/meal-logger/DashboardChatSidebar.vue';
import DataGridShell from '@/components/meal-logger/DataGridShell.vue';
import { isValidIsoDate, localIsoDate } from '@/lib/utils';
import { symptomsDashboard } from '@/routes';
import { update as patchSymptomDailyLog } from '@/routes/symptom-daily-logs';
import { store as symptomsChatStore } from '@/routes/symptoms-chat';

type ChatLine = { id: number; role: string; content: string };

type SymptomLog = {
    id: number;
    date: string;
    trend: 'better' | 'same' | 'worse' | null;
    fatigue: 'good' | 'baseline' | 'bad' | null;
    dizziness: 'none' | 'low' | 'high' | null;
    max_pain: number | null;
    updated_at: string | null;
};

type DayRow = {
    date: string;
    day_name: string;
    symptom_log: SymptomLog | null;
    chat_messages: ChatLine[];
};

const SELECTED_DATE_KEY = 'meal-logger:symptoms:selected-date';
const SELECTED_DATE_ANCHOR_KEY = 'meal-logger:symptoms:selected-date-anchor';

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
const chatAction = computed(() => symptomsChatStore());

const prevMonth = computed(() => {
    const d = new Date(props.year, props.month - 2, 1);
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
});
const nextMonth = computed(() => {
    const d = new Date(props.year, props.month, 1);
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
});

function onSidebarDateChange(newDate: string): void {
    if (!isValidIsoDate(newDate)) return;
    rememberSelectedDate(newDate);

    const [y, m] = newDate.split('-').map(Number);
    if (y === props.year && m === props.month) {
        selectedDate.value = newDate;
    } else {
        router.visit(symptomsDashboard.url({ year: y, month: m }));
    }
}

function onRowClick(day: DayRow): void {
    selectedDate.value = day.date;
    rememberSelectedDate(day.date);
}

function patchLog(log: SymptomLog, payload: Partial<SymptomLog>): void {
    router.patch(
        patchSymptomDailyLog.url({ symptom_daily_log: log.id }),
        {
            ...payload,
            expected_updated_at: log.updated_at,
        },
        { preserveScroll: true },
    );
}

function asTrend(v: string): SymptomLog['trend'] {
    return ['better', 'same', 'worse'].includes(v)
        ? (v as SymptomLog['trend'])
        : null;
}

function asFatigue(v: string): SymptomLog['fatigue'] {
    return ['good', 'baseline', 'bad'].includes(v)
        ? (v as SymptomLog['fatigue'])
        : null;
}

function asDizziness(v: string): SymptomLog['dizziness'] {
    return ['none', 'low', 'high'].includes(v)
        ? (v as SymptomLog['dizziness'])
        : null;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Symptoms',
                href: symptomsDashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Symptoms dashboard" />

    <DataGridShell
        title="Symptoms"
        :month-label="month_label"
        :prev-href="symptomsDashboard.url(prevMonth)"
        :next-href="symptomsDashboard.url(nextMonth)"
    >
        <template #table>
            <table class="w-full min-w-[860px] border-collapse text-sm">
                <thead class="sticky top-0 z-10 bg-muted/50">
                    <tr
                        class="text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <th class="border-b px-2 py-2">Date</th>
                        <th class="border-b px-2 py-2">Day</th>
                        <th class="border-b px-2 py-2">Trend</th>
                        <th class="border-b px-2 py-2">Fatigue</th>
                        <th class="border-b px-2 py-2">Dizziness</th>
                        <th class="border-b px-2 py-2">Max pain</th>
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
                        <td class="px-1 py-1" @click.stop>
                            <select
                                v-if="day.symptom_log"
                                class="h-8 rounded border border-input bg-background px-1 text-xs"
                                :value="day.symptom_log.trend ?? ''"
                                @change="
                                    patchLog(day.symptom_log, {
                                        trend: asTrend(
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        ),
                                    })
                                "
                            >
                                <option value="">—</option>
                                <option value="better">Better</option>
                                <option value="same">Same</option>
                                <option value="worse">Worse</option>
                            </select>
                        </td>
                        <td class="px-1 py-1" @click.stop>
                            <select
                                v-if="day.symptom_log"
                                class="h-8 rounded border border-input bg-background px-1 text-xs"
                                :value="day.symptom_log.fatigue ?? ''"
                                @change="
                                    patchLog(day.symptom_log, {
                                        fatigue: asFatigue(
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        ),
                                    })
                                "
                            >
                                <option value="">—</option>
                                <option value="good">Good</option>
                                <option value="baseline">Baseline</option>
                                <option value="bad">Bad</option>
                            </select>
                        </td>
                        <td class="px-1 py-1" @click.stop>
                            <select
                                v-if="day.symptom_log"
                                class="h-8 rounded border border-input bg-background px-1 text-xs"
                                :value="day.symptom_log.dizziness ?? ''"
                                @change="
                                    patchLog(day.symptom_log, {
                                        dizziness: asDizziness(
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        ),
                                    })
                                "
                            >
                                <option value="">—</option>
                                <option value="none">None</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                            </select>
                        </td>
                        <td class="px-1 py-1" @click.stop>
                            <input
                                v-if="day.symptom_log"
                                type="number"
                                min="0"
                                max="10"
                                class="h-8 w-16 rounded border border-input bg-background px-1 text-xs"
                                :value="day.symptom_log.max_pain ?? ''"
                                @blur="
                                    patchLog(day.symptom_log, {
                                        max_pain: (() => {
                                            const raw = (
                                                $event.target as HTMLInputElement
                                            ).value.trim();
                                            if (raw === '') return null;
                                            const n = Number.parseInt(raw, 10);
                                            return Number.isNaN(n)
                                                ? null
                                                : Math.max(0, Math.min(10, n));
                                        })(),
                                    })
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
                :has-daily-log="Boolean(selectedDay?.symptom_log)"
                :user-name="userDisplayName"
                title="Symptoms chat"
                placeholder="ex: today fatigue bad, dizziness low, pain peaked at 6, trend worse"
                empty-state-text="Describe symptoms for this day. Assistant can set trend, fatigue, dizziness, and max pain."
                @date-change="onSidebarDateChange"
            />
        </template>
    </DataGridShell>
</template>
