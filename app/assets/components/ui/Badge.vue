<script setup>
import { defineProps } from 'vue'

defineProps({
  variant: {
    type: String,
    default: 'default'
  },
  size: {
    type: String,
    default: 'md'
  }
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-full font-medium"
    :class="{
      'bg-slate-100 text-slate-700': variant === 'default',
      'bg-emerald-100 text-emerald-700': variant === 'success',
      'bg-amber-100 text-amber-700': variant === 'warning',
      'bg-rose-100 text-rose-700': variant === 'danger',
      'bg-indigo-100 text-indigo-700': variant === 'info',
      'px-2 py-0.5 text-xs': size === 'sm',
      'px-2.5 py-0.5 text-xs': size === 'md',
      'px-3 py-1 text-sm': size === 'lg',
    }"
  >
    <slot />
  </span>
</template>
