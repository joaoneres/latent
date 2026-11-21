<script setup lang="ts">
import type { ReloadOptions } from '@inertiajs/core';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import GenerationsTable from '@/components/GenerationsTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { create, index } from '@/routes/generations';
import type { GenerationRow } from '@/types/generation';

const props = defineProps<{
    generations: GenerationRow[];
}>();

const openStatuses = ['pending', 'queued', 'processing'] as const;
const updating = ref(false);

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
        if (!updating.value) {
            updating.value = true;
            start();
        }

        return;
    }

    if (updating.value) {
        stop();
        updating.value = false;
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
            <template v-if="updating" #status>
                <Spinner />
                Updating
            </template>
            <template #actions>
                <Button as-child>
                    <Link :href="create()">New generation</Link>
                </Button>
            </template>
        </PageHeader>

        <GenerationsTable :generations="generations" />
    </div>
</template>
