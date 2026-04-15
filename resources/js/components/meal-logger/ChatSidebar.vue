<script setup lang="ts">
import { computed } from 'vue';
import DashboardChatSidebar from '@/components/meal-logger/DashboardChatSidebar.vue';
import { store as chatPost } from '@/routes/chat';

export type { ChatLine } from '@/components/meal-logger/DashboardChatSidebar.vue';

const props = defineProps<{
    logDate: string;
    messages: { id: number; role: string; content: string }[];
    hasDailyLog?: boolean;
    userName?: string;
}>();

const emit = defineEmits<{
    dateChange: [newDate: string];
}>();

const chatAction = computed(() => chatPost());
</script>

<template>
    <DashboardChatSidebar
        :log-date="props.logDate"
        :messages="props.messages"
        :chat-action="chatAction"
        :has-daily-log="props.hasDailyLog"
        :user-name="props.userName"
        title="Nutrition chat"
        placeholder="- 16 oz water&#10;- lunch: …&#10;&#10;Follow-up: - also protein bar&#10;Or: remove oatmeal"
        empty-state-text="Describe food and drinks for this day. Chat maps entries to your My Foods library, then server computes totals from those saved nutrition rows."
        :show-debug-reset="true"
        @date-change="emit('dateChange', $event)"
    />
</template>
