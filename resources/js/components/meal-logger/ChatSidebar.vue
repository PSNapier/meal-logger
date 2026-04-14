<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as chatPost } from '@/routes/chat';
import { resetDay as debugResetDay } from '@/routes/debug';

export type ChatLine = {
    id: number;
    role: string;
    content: string;
};

const props = defineProps<{
    logDate: string;
    messages: ChatLine[];
    hasDailyLog?: boolean;
}>();

const page = usePage();

const serverMessageError = computed(
    () => (page.props.errors as Record<string, string>).message,
);

const showDebugReset = computed(() => Boolean(page.props.appDebug));

function confirmDebugReset(): boolean {
    return window.confirm(
        `Reset ${props.logDate}? Clears day totals, meals, and chat for that date.`,
    );
}
</script>

<template>
    <div
        class="flex h-full min-h-0 flex-col border-l border-sidebar-border/70 bg-sidebar/30 dark:border-sidebar-border"
    >
        <div
            class="flex items-start justify-between gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
        >
            <div class="min-w-0">
                <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    Nutrition chat
                </p>
                <p class="text-sm font-semibold">
                    {{ logDate }}
                </p>
            </div>
            <Form
                v-if="showDebugReset && props.hasDailyLog"
                :action="debugResetDay.url()"
                method="post"
                class="shrink-0"
                :options="{
                    preserveScroll: true,
                    onBefore: confirmDebugReset,
                }"
                v-slot="{ processing }"
            >
                <input
                    type="hidden"
                    name="log_date"
                    :value="logDate"
                />
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    :disabled="processing"
                >
                    {{ processing ? '…' : 'Debug reset' }}
                </Button>
            </Form>
        </div>

        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-3">
            <div
                v-if="messages.length === 0"
                class="text-muted-foreground text-sm"
            >
                Describe food and drinks for this day. The model fills water,
                fiber, calories, and meal lines in the grid.                 Later messages merge into the same day (add snacks, corrections,
                or say 'remove the bagel').
            </div>
            <div
                v-for="m in messages"
                :key="m.id"
                class="rounded-lg border border-border/60 bg-background/80 px-3 py-2 text-sm shadow-sm"
                :class="m.role === 'user' ? 'ml-4' : 'mr-4'"
            >
                <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">
                    {{ m.role }}
                </p>
                <div class="whitespace-pre-wrap break-words">
                    {{ m.content }}
                </div>
            </div>
        </div>

        <div class="border-t border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <Form
                :key="logDate"
                :action="chatPost.url()"
                method="post"
                class="flex flex-col gap-3"
                :options="{ preserveScroll: true }"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="log_date"
                    :value="logDate"
                />
                <div class="grid gap-2">
                    <Label for="meal-chat-input">Message</Label>
                    <textarea
                        id="meal-chat-input"
                        name="message"
                        rows="5"
                        required
                        placeholder="- 16 oz water&#10;- lunch: …&#10;&#10;Follow-up: - also protein bar&#10;Or: remove oatmeal"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[120px] w-full resize-y rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError :message="errors.message ?? serverMessageError" />
                </div>
                <Button
                    type="submit"
                    class="w-full gap-2"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    <span>{{ processing ? 'Working…' : 'Send to model' }}</span>
                </Button>
            </Form>
        </div>
    </div>
</template>
