<script setup lang="ts">
import type { ReloadOptions } from '@inertiajs/core';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import GenerationsTable from '@/components/GenerationsTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/generations';
import type { GenerationPagination, GenerationRow } from '@/types/generation';

const props = defineProps<{
    generations: GenerationRow[];
    pagination: GenerationPagination;
}>();

const perPageOptions = [5, 20, 50, 100] as const;
const openStatuses = ['pending', 'queued', 'processing'] as const;
const polling = ref(false);
const currentPage = ref(1);

const selectedPerPage = computed(() =>
    perPageOptions.some((option) => option === props.pagination.per_page)
        ? props.pagination.per_page
        : 20,
);

const rangeLabel = computed(() => {
    if (props.pagination.total < 1) {
        return null;
    }

    const from = (currentPage.value - 1) * props.pagination.per_page + 1;
    const to = Math.min(
        currentPage.value * props.pagination.per_page,
        props.pagination.total,
    );

    return `${from}–${to} of ${props.pagination.total} results`;
});

function isOpen(status: string): boolean {
    return openStatuses.some((openStatus) => openStatus === status);
}

function pollRequest(): ReloadOptions & { preserveScroll: true } {
    return {
        only: ['generations', 'pagination'],
        preserveScroll: true,
    };
}

const { start, stop } = usePoll(3000, pollRequest, {
    autoStart: false,
});

function syncPoll(generations: GenerationRow[]): void {
    const onFirstPage = props.pagination.prev_cursor === null;
    const hasOpen = generations.some((generation) => isOpen(generation.status));

    if (onFirstPage && hasOpen) {
        if (!polling.value) {
            polling.value = true;
            start();
        }

        return;
    }

    if (polling.value) {
        stop();
        polling.value = false;
    }
}

function visit(cursor: string | null, perPage: number): void {
    const query: Record<string, string | number> = {
        per_page: perPage,
    };

    if (cursor !== null && cursor !== '') {
        query.cursor = cursor;
    }

    router.get(index.url(), query, {
        preserveScroll: true,
        preserveState: true,
    });
}

function onPerPage(event: Event): void {
    const value = Number((event.target as HTMLSelectElement).value);

    if (!perPageOptions.some((option) => option === value)) {
        return;
    }

    currentPage.value = 1;
    visit(null, value);
}

function onPrevious(): void {
    if (props.pagination.prev_cursor === null) {
        return;
    }

    currentPage.value = Math.max(1, currentPage.value - 1);
    visit(props.pagination.prev_cursor, props.pagination.per_page);
}

function onNext(): void {
    if (props.pagination.next_cursor === null) {
        return;
    }

    currentPage.value += 1;
    visit(props.pagination.next_cursor, props.pagination.per_page);
}

onMounted(() => {
    syncPoll(props.generations);
});

watch(
    () => props.generations,
    (generations) => {
        syncPoll(generations);
    },
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Generations',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Generations" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Generations"
            description="Runs you have sent, including the ones still in progress."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()">New generation</Link>
                </Button>
            </template>
        </PageHeader>

        <GenerationsTable :generations="generations">
            <template #footer>
                <nav
                    aria-label="Pagination"
                    class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-4"
                    >
                        <label
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            Per page
                            <select
                                :value="selectedPerPage"
                                class="h-8 rounded-md border border-input bg-transparent px-2 text-sm text-foreground"
                                @change="onPerPage"
                            >
                                <option
                                    v-for="option in perPageOptions"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ option }}
                                </option>
                            </select>
                        </label>
                        <p
                            v-if="rangeLabel !== null"
                            class="text-sm text-muted-foreground"
                        >
                            {{ rangeLabel }}
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pagination.prev_cursor === null"
                            @click="onPrevious"
                        >
                            <ChevronLeft class="sm:hidden" />
                            <span class="hidden sm:inline">Previous</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pagination.next_cursor === null"
                            @click="onNext"
                        >
                            <span class="hidden sm:inline">Next</span>
                            <ChevronRight class="sm:hidden" />
                        </Button>
                    </div>
                </nav>
            </template>
        </GenerationsTable>
    </div>
</template>
