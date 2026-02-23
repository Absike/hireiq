<script setup>
import { defineProps } from 'vue'

defineProps({
  variant: {
    type: String,
    default: 'default'
  }
})
</script>

<template>
  <span
    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
    :class="{
      'bg-slate-100 text-slate-700': variant === 'default',
      'bg-emerald-100 text-emerald-700': variant === 'success',
      'bg-amber-100 text-amber-700': variant === 'warning',
      'bg-rose-100 text-rose-700': variant === 'danger',
      'bg-indigo-100 text-indigo-700': variant === 'info',
    }"
  >
    <slot />
  </span>
</template>
