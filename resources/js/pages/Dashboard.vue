<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GenerationsTable from '@/components/GenerationsTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard as dashboardRoute } from '@/routes';
import { create } from '@/routes/generations';
import type { DashboardSnapshot } from '@/types/generation';

const props = defineProps<{
    dashboard: DashboardSnapshot;
}>();

const page = usePage();

const greeting = computed(() => {
    const name = page.props.auth.user?.name?.trim() ?? '';
    const first = name.split(/\s+/)[0] ?? '';

    if (first === '') {
        return 'Hello';
    }

    return `Hello, ${first}`;
});

const stats = computed(() => [
    { label: 'Credit balance', value: props.dashboard.balance },
    { label: 'In progress', value: String(props.dashboard.in_progress) },
    { label: 'Succeeded', value: String(props.dashboard.succeeded) },
    { label: 'Failed', value: String(props.dashboard.failed) },
    { label: 'Total charged', value: props.dashboard.charged },
]);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboardRoute(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="greeting"
            description="A snapshot of your generations and credit."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()">New generation</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="stat in stats" :key="stat.label">
                <CardHeader>
                    <CardDescription>{{ stat.label }}</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">
                        {{ stat.value }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <section class="grid gap-3">
            <h2 class="text-base font-semibold">Recent generations</h2>
            <GenerationsTable :generations="dashboard.recent" />
        </section>
    </div>
</template>
