<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
        :class="className"
        v-bind="$attrs"
    >
        <path fill="currentColor" d="M5.5 3.5h5.25V15.25H19v5.25H5.5V3.5Z" />
    </svg>
</template>
