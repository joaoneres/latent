<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { create, index } from '@/routes/generations';

defineProps<{
    generations: {
        id: number;
        prompt: string;
        status: string;
    }[];
}>();

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

function statusLabel(status: string): string {
    if (status === 'pending') {
        return 'Enviando / aguardando vaga';
    }

    return status;
}
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
                <p>{{ statusLabel(generation.status) }}</p>
            </li>
        </ul>
    </div>
</template>
