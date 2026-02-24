<script setup>
import { ref, onMounted } from 'vue'
import { useJobsStore } from '../stores/jobs.js'
import { useCandidatesStore } from '../stores/candidates.js'
import RankingPanel from '../components/analysis/RankingPanel.vue'
import ComparePanel from '../components/analysis/ComparePanel.vue'
import InterviewQuestions from '../components/analysis/InterviewQuestions.vue'

const jobsStore = useJobsStore()
const candidatesStore = useCandidatesStore()

const activeTab = ref('rank')

onMounted(async () => {
  try {
    await Promise.all([
      jobsStore.fetchJobs(),
      candidatesStore.fetchCandidates(),
    ])
  } catch (e) {
    console.error(e)
  }
})
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">Analysis</h1>

    <!-- Tabs -->
    <div class="border-b border-slate-200">
      <nav class="flex gap-8">
        <button
          class="py-3 px-1 border-b-2 font-medium text-sm transition-colors"
          :class="activeTab === 'rank' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="activeTab = 'rank'"
        >
          Rank Candidates
        </button>
        <button
          class="py-3 px-1 border-b-2 font-medium text-sm transition-colors"
          :class="activeTab === 'compare' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="activeTab = 'compare'"
        >
          Compare Candidates
        </button>
        <button
          class="py-3 px-1 border-b-2 font-medium text-sm transition-colors"
          :class="activeTab === 'interview' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="activeTab = 'interview'"
        >
          Interview Questions
        </button>
      </nav>
    </div>

    <!-- Tab Content -->
    <RankingPanel v-if="activeTab === 'rank'" />
    <ComparePanel v-if="activeTab === 'compare'" />
    <InterviewQuestions v-if="activeTab === 'interview'" />
  </div>
</template>
