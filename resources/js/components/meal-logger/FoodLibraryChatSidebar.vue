<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as libraryChatStore } from '@/routes/my-foods/chat';

const page = usePage();

const serverMessageError = computed(
    () => (page.props.errors as Record<string, string>).message,
);
const assistantSummary = computed(
    () => (page.props.foodLibraryAssistant as string | null) ?? null,
);

function onMessageKeydown(event: KeyboardEvent): void {
    if (
        event.key !== 'Enter' ||
        event.shiftKey ||
        event.ctrlKey ||
        event.metaKey ||
        event.altKey
    ) {
        return;
    }

    const textarea = event.target as HTMLTextAreaElement | null;

    if (!textarea || textarea.value.trim() === '') {
        return;
    }

    event.preventDefault();
    textarea.form?.requestSubmit();
}
</script>

<template>
    <div
        class="flex h-full min-h-0 flex-col border-l border-sidebar-border/70 bg-sidebar/30 dark:border-sidebar-border"
    >
        <div class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
            <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                Food library chat
            </p>
            <p class="text-xs text-muted-foreground mt-1">
                Ask to add, update, or remove items from My Foods.
            </p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
            <div
                v-if="assistantSummary"
                class="rounded-lg border border-border/60 bg-background/80 px-3 py-2 text-sm shadow-sm"
            >
                <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">
                    Assistant
                </p>
                <div class="whitespace-pre-wrap break-words">
                    {{ assistantSummary }}
                </div>
            </div>
        </div>

        <div class="border-t border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <Form
                :action="libraryChatStore.url()"
                method="post"
                class="flex flex-col gap-3"
                :options="{ preserveScroll: true }"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="food-library-chat-input">Message</Label>
                    <textarea
                        id="food-library-chat-input"
                        name="message"
                        rows="5"
                        required
                        placeholder="Add 1 oz chicken breast = 9g protein..."
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[120px] w-full resize-y rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        @keydown="onMessageKeydown"
                    />
                    <InputError :message="errors.message ?? serverMessageError" />
                </div>
                <Button
                    type="submit"
                    class="w-full gap-2"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    <span>{{ processing ? 'Working…' : 'Send to assistant' }}</span>
                </Button>
            </Form>
        </div>
    </div>
</template>

