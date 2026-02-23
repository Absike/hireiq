<script setup>
import { defineProps } from 'vue'

defineProps({
  padding: {
    type: String,
    default: 'md'
  }
})
</script>

<template>
  <div
    class="bg-white rounded-lg border border-slate-200 shadow-sm"
    :class="{
      'p-0': padding === 'none',
      'p-3': padding === 'sm',
      'p-6': padding === 'md',
      'p-8': padding === 'lg',
    }"
  >
    <slot />
  </div>
</template>
