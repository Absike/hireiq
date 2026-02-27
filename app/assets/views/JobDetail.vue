<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useCandidatesStore } from '../stores/candidates.js'
import { useJobsStore } from '../stores/jobs.js'
import { analysisApi } from '../api/client.js'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'

const route = useRoute()
const jobsStore = useJobsStore()
const candidatesStore = useCandidatesStore()

const jobId = computed(() => Number(route.params.id))
const rankingResults = ref([])
const isRanking = ref(false)
const rankError = ref(null)

const formatDate = (date) => {
  if (!date) return '-'
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
  if (score === null || score === undefined) return 'text-slate-400'
  if (score <= 40) return 'text-rose-500'
  if (score <= 70) return 'text-amber-500'
  return 'text-emerald-500'
}

const eligibleCandidates = computed(() =>
  candidatesStore.candidates.filter(candidate =>
    (candidate.status === 'ready' || candidate.status === 'shortlisted') && candidate.status !== 'processing'
  )
)

const linkedCandidates = computed(() =>
  candidatesStore.candidates.filter(candidate => candidate.job_position?.id === jobId.value)
)

const rankCandidates = async () => {
  if (eligibleCandidates.value.length === 0) {
    rankError.value = 'No ready or shortlisted candidates are available for ranking.'
    return
  }

  isRanking.value = true
  rankError.value = null
  try {
    const response = await analysisApi.rank(jobId.value, eligibleCandidates.value.map(candidate => candidate.id))
    const payload = response?.data ?? {}
    rankingResults.value = Array.isArray(payload) ? payload : (payload.ranked ?? [])
  } catch (error) {
    console.error(error)
    rankingResults.value = []
    rankError.value = error?.response?.data?.error || 'Failed to rank candidates'
  } finally {
    isRanking.value = false
  }
}

onMounted(async () => {
  try {
    await Promise.all([jobsStore.fetchJob(jobId.value), candidatesStore.fetchCandidates()])
  } catch (error) {
    console.error(error)
  }
})
</script>

<template>
  <div class="space-y-6">
    <section class="flex items-center gap-3">
      <button class="p-2 rounded-xl border border-slate-300 hover:bg-slate-100" @click="$router.back()">
        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Job Intelligence</h1>
        <p class="text-sm text-slate-500">Role context plus AI ranking for decision support.</p>
      </div>
    </section>

    <div v-if="jobsStore.loading && !jobsStore.currentJob" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <template v-else-if="jobsStore.currentJob">
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 space-y-5">
          <Card>
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
              <div>
                <h2 class="text-2xl font-semibold text-slate-900">{{ jobsStore.currentJob.title }}</h2>
                <p class="text-sm text-slate-500 mt-1">Created {{ formatDate(jobsStore.currentJob.created_at) }}</p>
              </div>
              <Badge :variant="getStatusVariant(jobsStore.currentJob.status)">
                {{ jobsStore.currentJob.status }}
              </Badge>
            </div>
            <div class="space-y-4">
              <div>
                <h3 class="text-sm font-medium text-slate-700 mb-1">Description</h3>
                <p class="text-slate-600">{{ jobsStore.currentJob.description || 'No description provided.' }}</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-slate-700 mb-1">Requirements</h3>
                <p class="text-slate-600">{{ jobsStore.currentJob.requirements || 'No requirements provided.' }}</p>
              </div>
            </div>
          </Card>

          <Card>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
              <div>
                <h3 class="text-lg font-semibold text-slate-900">AI Candidate Ranking</h3>
                <p class="text-xs text-slate-500 mt-1">Uses ready and shortlisted candidates for a clean ranking set.</p>
              </div>
              <Button variant="primary" size="sm" :disabled="isRanking" @click="rankCandidates">
                <Spinner v-if="isRanking" size="sm" class="mr-2" />
                Run Ranking
              </Button>
            </div>

            <div v-if="rankError" class="mb-3 bg-rose-50 border border-rose-200 rounded-xl p-3 text-rose-700 text-sm">
              {{ rankError }}
            </div>

            <div v-if="!rankError && rankingResults.length === 0" class="text-center py-8 text-slate-500">
              Run ranking to compare candidate fit against this role.
            </div>

            <div v-else-if="rankingResults.length > 0" class="space-y-3">
              <div
                v-for="(result, index) in rankingResults"
                :key="result.candidate_id"
                class="flex items-center gap-4 p-4 rounded-xl border border-slate-200 bg-slate-50"
              >
                <span
                  class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold"
                  :class="index === 0 ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-700'"
                >
                  {{ index + 1 }}
                </span>
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-slate-900 truncate">{{ result.name }}</p>
                  <p class="text-xs text-slate-500 truncate">{{ result.email || 'No email' }}</p>
                  <p v-if="result.summary" class="text-xs text-slate-500 mt-1">{{ result.summary }}</p>
                </div>
                <div class="text-right">
                  <p class="text-2xl font-semibold" :class="getScoreColor(result.score)">{{ result.score ?? '-' }}</p>
                  <p class="text-xs text-slate-400">Score</p>
                </div>
              </div>
            </div>
          </Card>
        </div>

        <div class="space-y-5">
          <Card>
            <h3 class="text-lg font-semibold text-slate-900 mb-3">
              Linked Candidates ({{ linkedCandidates.length }})
            </h3>
            <div v-if="linkedCandidates.length === 0" class="text-sm text-slate-500">
              No candidate has been scored against this job yet.
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="candidate in linkedCandidates"
                :key="candidate.id"
                class="p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer"
                @click="$router.push(`/candidates/${candidate.id}`)"
              >
                <div class="flex items-center justify-between">
                  <p class="font-medium text-slate-900 truncate">{{ candidate.name }}</p>
                  <span :class="getScoreColor(candidate.ai_score)" class="font-semibold text-sm">
                    {{ candidate.ai_score ?? '-' }}
                  </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">{{ candidate.email || 'No email' }}</p>
              </div>
            </div>
          </Card>

          <Card>
            <h3 class="text-sm font-medium text-slate-700 mb-2">Ranking Pool</h3>
            <p class="text-sm text-slate-600">{{ eligibleCandidates.length }} candidates are eligible for ranking.</p>
          </Card>
        </div>
      </div>
    </template>
  </div>
</template>
