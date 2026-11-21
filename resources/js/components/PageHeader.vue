<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="grid gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p class="max-w-2xl text-sm text-muted-foreground">
                {{ description }}
            </p>
            <div
                v-if="$slots.status"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <slot name="status" />
            </div>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
