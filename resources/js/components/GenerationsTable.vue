<script setup lang="ts">
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { GenerationRow } from '@/types/generation';

defineProps<{
    generations: GenerationRow[];
}>();

const preview = ref<GenerationRow | null>(null);

const openStatuses = ['pending', 'queued', 'processing'] as const;

function isOpen(status: string): boolean {
    return openStatuses.some((openStatus) => openStatus === status);
}

function closePreview(open: boolean): void {
    if (!open) {
        preview.value = null;
    }
}

function openPreview(generation: GenerationRow): void {
    preview.value = generation;
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border">
        <div class="overflow-x-auto">
            <table
                class="w-full min-w-[760px] border-collapse text-left text-sm"
            >
                <thead
                    class="border-b bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Preview</th>
                        <th class="px-4 py-3 font-medium">Prompt</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Size</th>
                        <th class="px-4 py-3 font-medium">Quality</th>
                        <th class="px-4 py-3 font-medium">Charge</th>
                        <th class="px-4 py-3 font-medium">Created</th>
                        <th class="px-4 py-3 text-center font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="generations.length === 0">
                        <td
                            colspan="8"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No generations yet.
                        </td>
                    </tr>
                    <tr
                        v-for="generation in generations"
                        :key="generation.id"
                        class="border-b last:border-b-0"
                        :class="
                            isOpen(generation.status)
                                ? 'border-l-2 border-l-primary bg-muted/30'
                                : ''
                        "
                    >
                        <td class="px-4 py-3">
                            <button
                                v-if="generation.image_url !== null"
                                type="button"
                                class="size-16 overflow-hidden rounded-md bg-muted"
                                aria-label="Open image"
                                @click="openPreview(generation)"
                            >
                                <img
                                    :src="generation.image_url"
                                    alt="Generated image"
                                    class="size-full object-cover"
                                />
                            </button>
                            <div
                                v-else
                                class="flex size-16 items-center justify-center rounded-md bg-muted px-1 text-center text-[11px] text-muted-foreground"
                            >
                                No image
                            </div>
                        </td>
                        <td class="max-w-xs px-4 py-3">
                            <p
                                class="line-clamp-2 [overflow-wrap:anywhere]"
                                :title="generation.prompt"
                            >
                                {{ generation.prompt }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    generation.status === 'failed'
                                        ? 'destructive'
                                        : generation.status === 'succeeded'
                                          ? 'default'
                                          : 'secondary'
                                "
                            >
                                <Spinner
                                    v-if="isOpen(generation.status)"
                                    class="size-3"
                                />
                                {{ generation.status_label }}
                            </Badge>
                            <p
                                v-if="
                                    generation.status === 'failed' &&
                                    generation.error_message
                                "
                                class="mt-1 max-w-48 text-xs [overflow-wrap:anywhere] text-muted-foreground"
                            >
                                {{ generation.error_message }}
                            </p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ generation.size }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ generation.quality }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap tabular-nums">
                            {{ generation.amount }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ generation.created_at }}
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openPreview(generation)"
                            >
                                View
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <slot name="footer" />
    </div>

    <Dialog :open="preview !== null" @update:open="closePreview">
        <DialogContent
            v-if="preview"
            class="flex max-h-[calc(100dvh-2rem)] min-w-0 flex-col gap-4 overflow-hidden sm:max-w-4xl"
        >
            <DialogHeader class="shrink-0 pr-8">
                <DialogTitle>Generation</DialogTitle>
                <DialogDescription class="sr-only">
                    {{ preview.status_label }}, {{ preview.size }},
                    {{ preview.quality }}
                </DialogDescription>
            </DialogHeader>

            <div
                class="grid min-h-0 gap-4 overflow-y-auto pr-1 sm:grid-cols-[minmax(0,1.15fr)_minmax(16rem,0.85fr)]"
            >
                <div
                    class="flex min-h-56 items-center justify-center overflow-hidden rounded-lg bg-muted"
                >
                    <img
                        v-if="preview.image_url !== null"
                        :src="preview.image_url"
                        alt="Generated image"
                        class="max-h-[min(58vh,32rem)] w-full object-contain"
                    />
                    <p v-else class="px-6 text-sm text-muted-foreground">
                        No image
                    </p>
                </div>

                <div class="flex min-w-0 flex-col gap-4">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Status</dt>
                            <dd class="mt-1">
                                <Badge
                                    :variant="
                                        preview.status === 'failed'
                                            ? 'destructive'
                                            : preview.status === 'succeeded'
                                              ? 'default'
                                              : 'secondary'
                                    "
                                >
                                    {{ preview.status_label }}
                                </Badge>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Charge</dt>
                            <dd class="mt-1 tabular-nums">
                                {{ preview.amount }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Size</dt>
                            <dd class="mt-1">{{ preview.size }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Quality</dt>
                            <dd class="mt-1">{{ preview.quality }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-muted-foreground">Created</dt>
                            <dd class="mt-1">{{ preview.created_at }}</dd>
                        </div>
                    </dl>

                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Prompt</p>
                        <p
                            class="mt-1 max-h-36 overflow-y-auto rounded-md border bg-muted/40 p-3 text-sm leading-relaxed [overflow-wrap:anywhere]"
                        >
                            {{ preview.prompt }}
                        </p>
                    </div>

                    <p
                        v-if="
                            preview.status === 'failed' && preview.error_message
                        "
                        class="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm [overflow-wrap:anywhere]"
                    >
                        {{ preview.error_message }}
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
