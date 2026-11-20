<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/generations';

defineProps<{
    estimate: {
        min: string;
        max: string;
    };
}>();

const form = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_image_1: '',
    reference_image_2: '',
    reference_image_3: '',
    reference_image_4: '',
});

function submit(): void {
    form.transform((data) => ({
        prompt: data.prompt,
        size: data.size,
        quality: data.quality,
        reference_images: [
            data.reference_image_1,
            data.reference_image_2,
            data.reference_image_3,
            data.reference_image_4,
        ].filter((url) => url !== ''),
    })).post(store.url());
}

function referenceImageError(): string | undefined {
    const errors: Record<string, string | undefined> = form.errors;

    return errors['reference_images.0'];
}

let estimateTimer: ReturnType<typeof setTimeout> | undefined;

function scheduleEstimate(): void {
    if (estimateTimer !== undefined) {
        clearTimeout(estimateTimer);
    }

    estimateTimer = setTimeout(() => {
        router.get(
            create.url(),
            {
                prompt: form.prompt,
                size: form.size,
                quality: form.quality,
                reference_images: [
                    form.reference_image_1,
                    form.reference_image_2,
                    form.reference_image_3,
                    form.reference_image_4,
                ],
            },
            {
                only: ['estimate'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 300);
}

watch(
    () => [
        form.prompt,
        form.size,
        form.quality,
        form.reference_image_1,
        form.reference_image_2,
        form.reference_image_3,
        form.reference_image_4,
    ],
    () => {
        scheduleEstimate();
    },
);

onBeforeUnmount(() => {
    if (estimateTimer !== undefined) {
        clearTimeout(estimateTimer);
    }
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Gerar',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Gerar" />

    <form class="flex max-w-xl flex-col gap-6 p-4" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="prompt">Prompt</Label>
            <textarea
                id="prompt"
                v-model="form.prompt"
                required
                maxlength="4000"
                rows="5"
                class="w-full rounded-md border border-input px-3 py-2 text-sm"
            />
            <InputError :message="form.errors.prompt" />
        </div>

        <div class="grid gap-2">
            <Label for="size">Tamanho</Label>
            <select
                id="size"
                v-model="form.size"
                class="h-9 rounded-md border border-input px-3 text-sm"
            >
                <option value="1024x1024">1024x1024</option>
                <option value="1536x1024">1536x1024</option>
                <option value="1024x1536">1024x1536</option>
            </select>
            <InputError :message="form.errors.size" />
        </div>

        <div class="grid gap-2">
            <Label for="quality">Qualidade</Label>
            <select
                id="quality"
                v-model="form.quality"
                class="h-9 rounded-md border border-input px-3 text-sm"
            >
                <option value="low">low</option>
                <option value="medium">medium</option>
                <option value="high">high</option>
            </select>
            <InputError :message="form.errors.quality" />
        </div>

        <div class="grid gap-2">
            <Label for="reference-image-1">Imagens de referência</Label>
            <input
                id="reference-image-1"
                v-model="form.reference_image_1"
                type="url"
                placeholder="https://"
                class="h-9 rounded-md border border-input px-3 text-sm"
            />
            <input
                v-model="form.reference_image_2"
                type="url"
                placeholder="https://"
                class="h-9 rounded-md border border-input px-3 text-sm"
            />
            <input
                v-model="form.reference_image_3"
                type="url"
                placeholder="https://"
                class="h-9 rounded-md border border-input px-3 text-sm"
            />
            <input
                v-model="form.reference_image_4"
                type="url"
                placeholder="https://"
                class="h-9 rounded-md border border-input px-3 text-sm"
            />
            <InputError :message="referenceImageError()" />
        </div>

        <p>Estimado: {{ estimate.min }} – {{ estimate.max }}</p>

        <Button type="submit" :disabled="form.processing">Gerar</Button>
    </form>
</template>
