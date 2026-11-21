<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { download, index as report } from '@/routes/reports';

type ReportUser = {
    name: string;
    charged: string;
    provider_cost: string;
    profit: string;
};

type ReportDay = {
    date: string;
    charged: string;
    provider_cost: string;
    profit: string;
    matches: boolean | null;
    usage_cost: string | null;
    users: ReportUser[];
};

type ReportLine = {
    id: string;
    completed_at: string;
    user: string;
    email: string;
    status: string;
    prompt: string;
    size: string;
    quality: string;
    charged: string;
    provider_cost: string;
    profit: string;
    error_code: string;
    error_message: string;
    pixelforge_id: string;
};

type ReportSummary = {
    count: number;
    charged: string;
    provider_cost: string;
    profit: string;
};

type ReportFilters = {
    from: string;
    to: string;
    user_id: string;
    status: string;
};

type ReportUserOption = {
    id: string;
    name: string;
    email: string;
};

const props = defineProps<{
    summary: ReportSummary;
    days: ReportDay[];
    generations: ReportLine[];
    filters: ReportFilters;
    users: ReportUserOption[];
}>();

const form = reactive({
    from: props.filters.from,
    to: props.filters.to,
    user_id: props.filters.user_id,
    status: props.filters.status,
});

watch(
    () => props.filters,
    (filters) => {
        form.from = filters.from;
        form.to = filters.to;
        form.user_id = filters.user_id;
        form.status = filters.status;
    },
);

const filtering = computed(
    () =>
        props.filters.from !== '' ||
        props.filters.to !== '' ||
        props.filters.user_id !== '' ||
        props.filters.status !== '',
);

const downloadHref = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.from !== '') {
        params.set('from', props.filters.from);
    }

    if (props.filters.to !== '') {
        params.set('to', props.filters.to);
    }

    if (props.filters.user_id !== '') {
        params.set('user_id', props.filters.user_id);
    }

    if (props.filters.status !== '') {
        params.set('status', props.filters.status);
    }

    const query = params.toString();

    return query === '' ? download.url() : `${download.url()}?${query}`;
});

function apply(): void {
    const query: Record<string, string> = {};

    if (form.from !== '') {
        query.from = form.from;
    }

    if (form.to !== '') {
        query.to = form.to;
    }

    if (form.user_id !== '') {
        query.user_id = form.user_id;
    }

    if (form.status !== '') {
        query.status = form.status;
    }

    router.get(report.url(), query, {
        preserveScroll: true,
        preserveState: true,
    });
}

const stats = computed(() => [
    { label: 'Generations', value: String(props.summary.count) },
    { label: 'Charged', value: props.summary.charged },
    { label: 'Provider cost', value: props.summary.provider_cost },
    { label: 'Profit', value: props.summary.profit },
]);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Report',
                href: report(),
            },
        ],
    },
});

function usageLabel(day: ReportDay): string {
    if (day.matches === true && day.usage_cost !== null) {
        return `Usage ${day.usage_cost} matches`;
    }

    if (day.matches === false) {
        return 'Usage does not match';
    }

    return 'Usage unavailable';
}
</script>

<template>
    <Head title="Report" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Report"
            description="Every completed generation, with daily totals and a CSV download."
        >
            <template #actions>
                <Button as-child>
                    <a :href="downloadHref">Download</a>
                </Button>
            </template>
        </PageHeader>

        <form class="grid gap-4 rounded-lg border p-4" @submit.prevent="apply">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="grid gap-2">
                    <Label for="report-from">From</Label>
                    <Input
                        id="report-from"
                        v-model="form.from"
                        type="date"
                        name="from"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="report-to">To</Label>
                    <Input
                        id="report-to"
                        v-model="form.to"
                        type="date"
                        name="to"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="report-user">User</Label>
                    <select
                        id="report-user"
                        v-model="form.user_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm text-foreground"
                    >
                        <option value="">All users</option>
                        <option
                            v-for="user in users"
                            :key="user.id"
                            :value="String(user.id)"
                        >
                            {{ user.name }} · {{ user.email }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="report-status">Status</Label>
                    <select
                        id="report-status"
                        v-model="form.status"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm text-foreground"
                    >
                        <option value="">Succeeded and failed</option>
                        <option value="succeeded">Succeeded</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>
            <p class="text-sm text-muted-foreground">
                Dates are the UTC day the generation finished.
            </p>
            <div class="flex gap-2">
                <Button type="submit">Apply</Button>
                <Button v-if="filtering" variant="outline" as-child>
                    <Link :href="report()">Clear</Link>
                </Button>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
            <h2 class="text-sm font-medium">Generations</h2>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full min-w-[64rem] text-sm">
                    <thead
                        class="border-b bg-muted/40 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                Completed (UTC)
                            </th>
                            <th class="px-3 py-2 font-medium">User</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2 font-medium">Prompt</th>
                            <th class="px-3 py-2 font-medium">Size</th>
                            <th class="px-3 py-2 font-medium">Quality</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Charged
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Provider cost
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Profit
                            </th>
                            <th class="px-3 py-2 font-medium">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="generations.length === 0">
                            <td
                                class="px-3 py-8 text-center text-muted-foreground"
                                colspan="10"
                            >
                                No completed generations.
                            </td>
                        </tr>
                        <tr
                            v-for="line in generations"
                            :key="line.id"
                            class="border-b last:border-b-0"
                        >
                            <td
                                class="px-3 py-2 whitespace-nowrap tabular-nums"
                            >
                                {{ line.completed_at }}
                            </td>
                            <td class="px-3 py-2">
                                <div>{{ line.user }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ line.email }}
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap capitalize">
                                {{ line.status }}
                            </td>
                            <td class="max-w-xs px-3 py-2">
                                <p
                                    class="line-clamp-2 break-words"
                                    :title="line.prompt"
                                >
                                    {{ line.prompt }}
                                </p>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ line.size }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ line.quality }}
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ line.charged }}
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ line.provider_cost }}
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ line.profit }}
                            </td>
                            <td class="max-w-xs px-3 py-2">
                                <p
                                    class="line-clamp-2 break-words"
                                    :title="line.error_message"
                                >
                                    {{ line.error_message }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4">
            <h2 class="text-sm font-medium">Daily totals</h2>

            <p v-if="days.length === 0" class="text-sm text-muted-foreground">
                No completed generations.
            </p>

            <article
                v-for="day in days"
                :key="day.date"
                class="overflow-hidden rounded-lg border"
            >
                <header
                    class="flex flex-wrap items-baseline justify-between gap-2 border-b px-4 py-3"
                >
                    <h3 class="font-medium tabular-nums">{{ day.date }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{ usageLabel(day) }}
                    </p>
                </header>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-sm">
                        <thead
                            class="border-b bg-muted/40 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-2 font-medium">User</th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Charged
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Provider cost
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Profit
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in day.users"
                                :key="user.name"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-4 py-2">{{ user.name }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ user.charged }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ user.provider_cost }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ user.profit }}
                                </td>
                            </tr>
                            <tr class="border-t bg-muted/20 font-medium">
                                <td class="px-4 py-2">Day total</td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ day.charged }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ day.provider_cost }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ day.profit }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</template>
