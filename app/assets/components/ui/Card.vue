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
    class="bg-white/95 rounded-2xl border border-slate-200 shadow-sm backdrop-blur"
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
