<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index as report } from '@/routes/reports';

defineProps<{
    days: {
        date: string;
        charged: string;
        provider_cost: string;
        profit: string;
        matches: boolean | null;
        usage_cost: string | null;
        users: {
            name: string;
            charged: string;
            provider_cost: string;
            profit: string;
        }[];
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Relatório',
                href: report(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Relatório" />

    <div class="flex flex-col gap-4 p-4">
        <section
            v-for="day in days"
            :key="day.date"
            class="rounded-md border p-3"
        >
            <h2>{{ day.date }}</h2>
            <p>{{ day.charged }}</p>
            <p>{{ day.provider_cost }}</p>
            <p>{{ day.profit }}</p>
            <p v-if="day.matches === true">{{ day.usage_cost }} ✓ confere</p>

            <ul class="flex flex-col gap-2">
                <li v-for="user in day.users" :key="user.name">
                    <p>{{ user.name }}</p>
                    <p>{{ user.charged }}</p>
                    <p>{{ user.provider_cost }}</p>
                    <p>{{ user.profit }}</p>
                </li>
            </ul>
        </section>
    </div>
</template>
