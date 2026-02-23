<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useJobsStore } from '../stores/jobs.js'
import { useCandidatesStore } from '../stores/candidates.js'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Badge from '../components/ui/Badge.vue'
import Spinner from '../components/ui/Spinner.vue'

const route = useRoute()
const jobsStore = useJobsStore()
const candidatesStore = useCandidatesStore()

const jobId = computed(() => Number(route.params.id))
const rankingResults = ref([])
const isRanking = ref(false)

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const getStatusVariant = (status) => {
  const variants = {
    open: 'success',
    closed: 'danger',
    draft: 'default',
  }
  return variants[status] || 'default'
}

const getScoreColor = (score) => {
  if (score <= 40) return 'text-rose-500'
  if (score <= 70) return 'text-amber-500'
  return 'text-emerald-500'
}

const rankCandidates = async () => {
  isRanking.value = true
  try {
    const response = await fetch('http://localhost:8080/api/analysis/rank', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ job_id: jobId.value, candidate_ids: candidatesStore.candidates.map(c => c.id) }),
    })
    rankingResults.value = await response.json()
  } catch (e) {
    console.error(e)
  } finally {
    isRanking.value = false
  }
}

onMounted(async () => {
  try {
    await Promise.all([
      jobsStore.fetchJob(jobId.value),
      candidatesStore.fetchCandidates(),
    ])
  } catch (e) {
    console.error(e)
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button @click="$router.back()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-2xl font-bold text-slate-900">Job Details</h1>
    </div>

    <div v-if="jobsStore.loading && !jobsStore.currentJob" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <template v-else-if="jobsStore.currentJob">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Job Info -->
        <div class="lg:col-span-2 space-y-6">
          <Card padding="md">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h2 class="text-xl font-bold text-slate-900">{{ jobsStore.currentJob.title }}</h2>
                <p class="text-slate-500">Created {{ formatDate(jobsStore.currentJob.created_at) }}</p>
              </div>
              <Badge :variant="getStatusVariant(jobsStore.currentJob.status)">
                {{ jobsStore.currentJob.status }}
              </Badge>
            </div>

            <div class="space-y-4">
              <div>
                <h3 class="text-sm font-medium text-slate-700 mb-1">Description</h3>
                <p class="text-slate-600">{{ jobsStore.currentJob.description || 'No description provided' }}</p>
              </div>

              <div v-if="jobsStore.currentJob.requirements">
                <h3 class="text-sm font-medium text-slate-700 mb-1">Requirements</h3>
                <p class="text-slate-600">{{ jobsStore.currentJob.requirements }}</p>
              </div>
            </div>
          </Card>

          <!-- Ranking Section -->
          <Card padding="md">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold text-slate-900">Candidate Ranking</h3>
              <Button variant="primary" size="sm" :disabled="isRanking" @click="rankCandidates">
                <Spinner v-if="isRanking" size="sm" class="mr-2" />
                Rank Candidates
              </Button>
            </div>

            <div v-if="rankingResults.length === 0" class="text-center py-8 text-slate-500">
              Click "Rank Candidates" to see AI-powered rankings
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="(result, index) in rankingResults"
                :key="result.candidate_id"
                class="flex items-center gap-4 p-4 bg-slate-50 rounded-lg"
              >
                <span class="text-lg font-bold text-slate-400">#{{ index + 1 }}</span>
                <div class="flex-1">
                  <p class="font-medium text-slate-900">{{ result.name }}</p>
                  <p class="text-sm text-slate-500">{{ result.email }}</p>
                </div>
                <div class="text-right">
                  <p class="text-2xl font-bold" :class="getScoreColor(result.score)">{{ result.score }}</p>
                  <p class="text-xs text-slate-400">score</p>
                </div>
              </div>
            </div>
          </Card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <Card padding="md">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">
              Candidates ({{ candidatesStore.candidates.length }})
            </h3>
            <div v-if="candidatesStore.candidates.length === 0" class="text-center py-4 text-slate-500">
              No candidates yet
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="candidate in candidatesStore.candidates"
                :key="candidate.id"
                class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg cursor-pointer"
                @click="$router.push(`/candidates/${candidate.id}`)"
              >
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                  <span class="text-sm font-medium text-indigo-600">
                    {{ candidate.name.charAt(0).toUpperCase() }}
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-slate-900 truncate">{{ candidate.name }}</p>
                  <p class="text-xs text-slate-500">{{ candidate.email || '-' }}</p>
                </div>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </template>
  </div>
</template>
