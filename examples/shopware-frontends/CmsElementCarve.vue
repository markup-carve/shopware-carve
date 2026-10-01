<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  content: { data?: { html?: string; carveSource?: string; carveIncludeDependencies?: Array<{ path: string; resolved: boolean }> } };
}>();
const html = computed(() => props.content.data?.html ?? '');
</script>

<template>
  <div class="cms-element-carve carve-content" v-html="html" />
</template>
