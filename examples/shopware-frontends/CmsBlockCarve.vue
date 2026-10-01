<script setup lang="ts">
import { computed } from 'vue';
import CmsElementCarve from './CmsElementCarve.vue';

const props = defineProps<{
  content: { slots?: Array<{ slot: string; data?: { html?: string } }> };
}>();
const element = computed(() => props.content.slots?.find((slot) => slot.slot === 'content'));
</script>

<template>
  <CmsElementCarve v-if="element" :content="element" />
</template>
