<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const pageTitleMap = {
  dashboard: 'Recruiting Dashboard',
  candidates: 'Candidate Pipeline',
  'candidate-detail': 'Candidate Review',
  jobs: 'Job Openings',
  'job-detail': 'Job Intelligence',
  analysis: 'AI Analysis',
  chat: 'Candidate Chat',
}

const pageSubtitleMap = {
  dashboard: 'Track hiring activity and high-priority actions.',
  candidates: 'Filter, score, and move applicants through your funnel.',
  'candidate-detail': 'Deep profile, extracted skills, and decision actions.',
  jobs: 'Maintain role quality and pipeline readiness.',
  'job-detail': 'Review role context and compare candidate fit.',
  analysis: 'Rank, compare, and prepare interviews with AI support.',
  chat: 'Ask focused questions about the candidate CV.',
}

const pageTitle = computed(() => pageTitleMap[route.name] || 'HireIQ')
const pageSubtitle = computed(() => pageSubtitleMap[route.name] || 'AI-powered recruitment workspace.')
const today = computed(() =>
  new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
)
const mobileNav = [
  { label: 'Dashboard', to: '/' },
  { label: 'Candidates', to: '/candidates' },
  { label: 'Jobs', to: '/jobs' },
  { label: 'Analysis', to: '/analysis' },
]

const isActive = (path) => {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>

<template>
  <header class="bg-white/80 backdrop-blur border-b border-slate-200 px-4 md:px-6 py-3">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900">{{ pageTitle }}</h2>
        <p class="text-sm text-slate-500">{{ pageSubtitle }}</p>
      </div>
      <div class="hidden md:flex items-center gap-3">
        <span class="text-xs uppercase tracking-wide text-slate-500">{{ today }}</span>
        <router-link
          to="/candidates"
          class="px-3 py-2 rounded-xl text-sm font-medium bg-cyan-700 text-white hover:bg-cyan-800 transition-colors"
        >
          Upload CV
        </router-link>
        <router-link
          to="/jobs"
          class="px-3 py-2 rounded-xl text-sm font-medium border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors"
        >
          New Job
        </router-link>
      </div>
    </div>
    <div class="md:hidden flex gap-2 mt-3 overflow-x-auto pb-1">
      <router-link
        v-for="item in mobileNav"
        :key="item.to"
        :to="item.to"
        class="px-3 py-1.5 rounded-full text-xs whitespace-nowrap border transition-colors"
        :class="isActive(item.to)
          ? 'bg-cyan-700 text-white border-cyan-700'
          : 'bg-white text-slate-600 border-slate-300'"
      >
        {{ item.label }}
      </router-link>
    </div>
  </header>
</template>
