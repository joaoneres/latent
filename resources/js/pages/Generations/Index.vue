<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { create, index } from '@/routes/generations';

defineProps<{
    generations: {
        id: number;
        prompt: string;
        status: string;
        status_label: string;
        amount: string;
        image_url: string | null;
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
