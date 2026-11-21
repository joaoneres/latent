<script setup lang="ts">
import type { ReloadOptions } from '@inertiajs/core';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import GenerationsTable from '@/components/GenerationsTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/generations';
import type { GenerationRow } from '@/types/generation';

const props = defineProps<{
    generations: GenerationRow[];
}>();

const openStatuses = ['pending', 'queued', 'processing'] as const;
const polling = ref(false);

function isOpen(status: string): boolean {
    return openStatuses.some((openStatus) => openStatus === status);
}

function pollRequest(): ReloadOptions & { preserveScroll: true } {
    return {
        only: ['generations'],
        preserveScroll: true,
    };
}

const { start, stop } = usePoll(3000, pollRequest, {
    autoStart: false,
});

function syncPoll(generations: GenerationRow[]): void {
    if (generations.some((generation) => isOpen(generation.status))) {
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

        <GenerationsTable :generations="generations" />
    </div>
</template>
