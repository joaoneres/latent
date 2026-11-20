<script setup lang="ts">
import type { ReloadOptions } from '@inertiajs/core';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import { create, index } from '@/routes/generations';

const props = defineProps<{
    generations: {
        id: number;
        prompt: string;
        status: string;
        status_label: string;
        amount: string;
        image_url: string | null;
    }[];
}>();

const openStatuses = ['pending', 'queued', 'processing'] as const;

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

onMounted(() => {
    if (props.generations.some((generation) => isOpen(generation.status))) {
        start();
    }
});

watch(
    () => props.generations,
    (generations) => {
        const settled = generations.every(
            (generation) =>
                generation.status === 'succeeded' ||
                generation.status === 'failed',
        );

        if (settled) {
            stop();
        }
    },
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Gerações',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Gerações" />

    <div class="flex flex-col gap-4 p-4">
        <Link :href="create()" class="text-sm underline">Gerar</Link>

        <ul class="flex flex-col gap-3">
            <li
                v-for="generation in generations"
                :key="generation.id"
                class="rounded-md border p-3"
            >
                <p>{{ generation.prompt }}</p>
                <p>{{ generation.status_label }}</p>
                <p>{{ generation.amount }}</p>
                <img
                    v-if="generation.image_url !== null"
                    :src="generation.image_url"
                    :alt="generation.prompt"
                />
            </li>
        </ul>
    </div>
</template>
