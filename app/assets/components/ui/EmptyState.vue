<script setup>
import { defineProps, defineEmits } from 'vue'
import Button from './Button.vue'

defineProps({
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: ''
  },
  actionLabel: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['action'])
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <div class="w-16 h-16 mb-4 rounded-full bg-slate-100 flex items-center justify-center">
      <slot name="icon">
        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
      </slot>
    </div>
    <h3 class="text-lg font-medium text-slate-900 mb-1">{{ title }}</h3>
    <p v-if="description" class="text-sm text-slate-500 mb-4 max-w-sm">{{ description }}</p>
    <Button v-if="actionLabel" variant="primary" @click="emit('action')">
      {{ actionLabel }}
    </Button>
  </div>
</template>
