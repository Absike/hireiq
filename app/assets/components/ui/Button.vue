<script setup>
import { defineProps, defineEmits } from 'vue'

defineProps({
  variant: {
    type: String,
    default: 'primary'
  },
  size: {
    type: String,
    default: 'md'
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click'])
</script>

<template>
  <button
    class="inline-flex items-center justify-center font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
    :class="{
      'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500': variant === 'primary',
      'bg-slate-200 text-slate-800 hover:bg-slate-300 focus:ring-slate-400': variant === 'secondary',
      'bg-emerald-500 text-white hover:bg-emerald-600 focus:ring-emerald-400': variant === 'success',
      'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-400': variant === 'warning',
      'bg-rose-500 text-white hover:bg-rose-600 focus:ring-rose-400': variant === 'danger',
      'bg-transparent text-slate-600 hover:bg-slate-100 focus:ring-slate-400': variant === 'ghost',
      'px-3 py-1.5 text-sm': size === 'sm',
      'px-4 py-2 text-sm': size === 'md',
      'px-6 py-3 text-base': size === 'lg',
    }"
    :disabled="disabled"
    @click="emit('click', $event)"
  >
    <slot />
  </button>
</template>
