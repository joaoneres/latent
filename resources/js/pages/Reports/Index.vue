<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
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
                title: 'Report',
                href: report(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Report" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Report"
            description="Daily charges, provider cost, and profit."
        />

        <p v-if="days.length === 0" class="text-sm text-muted-foreground">
            No completed generations yet.
        </p>

        <section
            v-for="day in days"
            :key="day.date"
            class="overflow-x-auto rounded-xl border"
        >
            <div
                class="flex flex-col gap-2 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="font-semibold">{{ day.date }}</h2>
                <p class="text-sm text-muted-foreground">
                    <template v-if="day.matches === true">
                        Usage {{ day.usage_cost }} matches
                    </template>
                    <template v-else-if="day.matches === false">
                        Usage does not match
                    </template>
                    <template v-else>Usage unavailable</template>
                </p>
            </div>

            <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                <thead
                    class="border-b bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">User</th>
                        <th class="px-4 py-3 font-medium">Charged</th>
                        <th class="px-4 py-3 font-medium">Provider cost</th>
                        <th class="px-4 py-3 font-medium">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b font-medium">
                        <td class="px-4 py-3">Day total</td>
                        <td class="px-4 py-3 tabular-nums">{{ day.charged }}</td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ day.provider_cost }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ day.profit }}</td>
                    </tr>
                    <tr
                        v-for="user in day.users"
                        :key="user.name"
                        class="border-b last:border-b-0"
                    >
                        <td class="px-4 py-3">{{ user.name }}</td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ user.charged }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ user.provider_cost }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ user.profit }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
