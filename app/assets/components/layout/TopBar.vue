<script setup>
import { ref } from 'vue'
</script>

<template>
  <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6">
    <div class="flex items-center gap-4">
      <h2 class="text-lg font-semibold text-slate-900">HireIQ</h2>
    </div>
    <div class="flex items-center gap-4">
      <div class="relative">
        <button class="p-2 text-slate-500 hover:text-slate-700 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </button>
      </div>
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
          <span class="text-sm font-medium text-indigo-600">U</span>
        </div>
      </div>
    </div>
  </header>
</template>
