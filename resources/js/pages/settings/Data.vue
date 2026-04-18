<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postFormDataJson } from '@/lib/postFormDataJson';
import { edit, exportMethod } from '@/routes/data';
import { preview, store } from '@/routes/data/import';
import { toast } from 'vue-sonner';
import { computed, ref } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Data import / export',
                href: edit.url(),
            },
        ],
    },
});

type TableSummary = {
    new: number;
    identical: number;
    conflicting: number;
    blank: number;
    updates: number;
};

type PreviewResponse = {
    summary: Record<string, TableSummary>;
    has_conflicts: boolean;
    totals: TableSummary;
};

const tableLabels: Record<string, string> = {
    daily_logs: 'Meals (daily logs)',
    activity_daily_logs: 'Activity',
    symptom_daily_logs: 'Symptoms',
    measurements: 'Measurements',
};

const exportHref = exportMethod.url();

const pendingFile = ref<File | null>(null);
const previewLoading = ref(false);
const importLoading = ref(false);
const dialogOpen = ref(false);
const previewData = ref<PreviewResponse | null>(null);

const summaryRows = computed(() => {
    const s = previewData.value?.summary;
    if (!s) {
        return [];
    }

    return Object.entries(s).map(([key, v]) => ({
        key,
        label: tableLabels[key] ?? key,
        ...v,
    }));
});

async function runPreview(file: File): Promise<void> {
    previewLoading.value = true;
    previewData.value = null;

    try {
        const formData = new FormData();
        formData.append('file', file);
        const raw = (await postFormDataJson(
            preview.url(),
            formData,
        )) as PreviewResponse;
        previewData.value = raw;
        pendingFile.value = file;
        dialogOpen.value = true;
    } catch (e) {
        toast.error(e instanceof Error ? e.message : 'Preview failed');
    } finally {
        previewLoading.value = false;
    }
}

function onFileChange(ev: Event): void {
    const input = ev.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    void runPreview(file);
    input.value = '';
}

async function commitImport(mode: 'merge' | 'overwrite'): Promise<void> {
    const file = pendingFile.value;
    if (!file) {
        return;
    }

    importLoading.value = true;

    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('mode', mode);
        const raw = (await postFormDataJson(store.url(), formData)) as {
            message?: string;
        };
        toast.success(raw.message ?? 'Import complete');
        dialogOpen.value = false;
        pendingFile.value = null;
        previewData.value = null;
    } catch (e) {
        toast.error(e instanceof Error ? e.message : 'Import failed');
    } finally {
        importLoading.value = false;
    }
}

function onDialogOpenChange(open: boolean): void {
    dialogOpen.value = open;
    if (!open) {
        pendingFile.value = null;
        previewData.value = null;
    }
}
</script>

<template>
    <Head title="Data import / export" />

    <h1 class="sr-only">Data import / export</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Data import / export"
            description="Download a JSON backup of your logs or restore from a file. Use this to sync devices manually."
        />

        <Card>
            <CardHeader>
                <CardTitle>Export</CardTitle>
                <CardDescription>
                    Includes meals (daily logs + items), activity, symptoms, and
                    measurements for your account only.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Button as-child variant="default">
                    <a :href="exportHref"> Download JSON </a>
                </Button>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Import</CardTitle>
                <CardDescription>
                    Choose a JSON file exported from this app (version 1). You
                    will see a summary before anything is written.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="space-y-2">
                    <Label for="import-file">JSON file</Label>
                    <Input
                        id="import-file"
                        type="file"
                        accept=".json,application/json"
                        :disabled="previewLoading"
                        @change="onFileChange"
                    />
                </div>
                <p
                    v-if="previewLoading"
                    class="text-muted-foreground text-sm"
                >
                    Analyzing file…
                </p>
            </CardContent>
        </Card>

        <Dialog :open="dialogOpen" @update:open="onDialogOpenChange">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Confirm import</DialogTitle>
                    <DialogDescription>
                        Review counts below. Blank rows in the file are always
                        skipped and never erase existing data.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="previewData"
                    class="space-y-4 text-sm"
                >
                    <div
                        class="bg-muted/50 grid grid-cols-2 gap-x-4 gap-y-1 rounded-md border p-3"
                    >
                        <span class="text-muted-foreground">Total new days</span>
                        <span class="text-right tabular-nums font-medium">{{
                            previewData.totals.new
                        }}</span>
                        <span class="text-muted-foreground"
                            >Total updates (merge)</span
                        >
                        <span class="text-right tabular-nums font-medium">{{
                            previewData.totals.updates
                        }}</span>
                        <span class="text-muted-foreground">Unchanged</span>
                        <span class="text-right tabular-nums font-medium">{{
                            previewData.totals.identical
                        }}</span>
                        <span class="text-muted-foreground">Blank rows skipped</span>
                        <span class="text-right tabular-nums font-medium">{{
                            previewData.totals.blank
                        }}</span>
                        <span
                            v-if="previewData.has_conflicts"
                            class="text-destructive col-span-2 font-medium"
                        >
                            Conflicting days: {{ previewData.totals.conflicting }}
                            — choose merge (skip those days) or overwrite.
                        </span>
                    </div>

                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b text-muted-foreground">
                                <th class="py-2 pr-2 font-medium">Dataset</th>
                                <th class="px-1 py-2 text-right font-medium">New</th>
                                <th class="px-1 py-2 text-right font-medium">Upd</th>
                                <th class="px-1 py-2 text-right font-medium">Same</th>
                                <th class="px-1 py-2 text-right font-medium">Skip</th>
                                <th class="py-2 pl-1 text-right font-medium">⚠</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in summaryRows"
                                :key="row.key"
                                class="border-b border-border/60"
                            >
                                <td class="py-2 pr-2">{{ row.label }}</td>
                                <td class="px-1 py-2 text-right tabular-nums">
                                    {{ row.new }}
                                </td>
                                <td class="px-1 py-2 text-right tabular-nums">
                                    {{ row.updates }}
                                </td>
                                <td class="px-1 py-2 text-right tabular-nums">
                                    {{ row.identical }}
                                </td>
                                <td class="px-1 py-2 text-right tabular-nums">
                                    {{ row.blank }}
                                </td>
                                <td class="py-2 pl-1 text-right tabular-nums">
                                    {{ row.conflicting }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DialogFooter
                    class="flex-col gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        variant="outline"
                        :disabled="importLoading"
                        @click="onDialogOpenChange(false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        v-if="previewData?.has_conflicts"
                        variant="secondary"
                        :disabled="importLoading"
                        @click="commitImport('merge')"
                    >
                        Merge only (skip conflicts)
                    </Button>
                    <Button
                        v-if="previewData?.has_conflicts"
                        variant="destructive"
                        :disabled="importLoading"
                        @click="commitImport('overwrite')"
                    >
                        Overwrite conflicts
                    </Button>
                    <Button
                        v-if="previewData && !previewData.has_conflicts"
                        :disabled="importLoading"
                        @click="commitImport('merge')"
                    >
                        Import
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
