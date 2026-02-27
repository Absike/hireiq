<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()

const navItems = [
  { name: 'Dashboard', path: '/', icon: 'chart' },
  { name: 'Candidates', path: '/candidates', icon: 'users' },
  { name: 'Jobs', path: '/jobs', icon: 'briefcase' },
  { name: 'Analysis', path: '/analysis', icon: 'cpu' },
]

const isActive = (path) => {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>

<template>
  <aside class="w-72 bg-slate-950/95 text-slate-100 backdrop-blur border-r border-slate-800 flex flex-col">
    <div class="p-6 border-b border-slate-800">
      <p class="text-xs uppercase tracking-[0.2em] text-cyan-300/80">Recruiting OS</p>
      <h1 class="text-2xl font-semibold text-white mt-1">HireIQ</h1>
      <p class="text-xs text-slate-400 mt-2">AI screening, ranking, and interview prep.</p>
    </div>

    <nav class="flex-1 p-4 space-y-1.5">
      <router-link
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200"
        :class="isActive(item.path)
          ? 'bg-cyan-500/20 text-cyan-200 border border-cyan-400/30'
          : 'text-slate-400 hover:bg-slate-900 hover:text-white border border-transparent'"
      >
        <svg v-if="item.icon === 'chart'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <svg v-else-if="item.icon === 'users'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <svg v-else-if="item.icon === 'briefcase'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        <svg v-else-if="item.icon === 'cpu'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
        </svg>
        <span class="font-medium">{{ item.name }}</span>
      </router-link>
    </nav>
  </aside>
</template>
