<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const page = usePage();
</script>

<template>
    <Head title="Latent" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="flex items-center justify-between px-6 py-4">
            <span class="text-sm font-semibold tracking-tight">Latent</span>
            <nav class="flex items-center gap-2">
                <Button v-if="page.props.auth.user" as-child>
                    <Link :href="dashboard()">Dashboard</Link>
                </Button>
                <Button v-else as-child>
                    <Link :href="login()">Log in</Link>
                </Button>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-xl flex-1 flex-col justify-center gap-4 px-6 py-16"
        >
            <h1 class="text-4xl font-semibold tracking-tight">Latent</h1>
            <p class="text-base text-muted-foreground">
                Image generation with a price quote before you run it.
            </p>
        </main>
    </div>
</template>
