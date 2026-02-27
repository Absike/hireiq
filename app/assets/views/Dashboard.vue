<script setup>
import { computed, onMounted, ref } from 'vue'
import { useCandidatesStore } from '../stores/candidates.js'
import { useJobsStore } from '../stores/jobs.js'
import Card from '../components/ui/Card.vue'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'

const candidatesStore = useCandidatesStore()
const jobsStore = useJobsStore()
const loading = ref(true)

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const getStatusVariant = (status) => {
  const variants = {
    new: 'default',
    processing: 'warning',
    ready: 'success',
    shortlisted: 'info',
    rejected: 'danger',
    open: 'success',
    closed: 'danger',
    draft: 'default',
  }
  return variants[status] || 'default'
}

const scoredCandidates = computed(() => candidatesStore.candidates.filter(c => Number.isFinite(c.ai_score)))
const averageScore = computed(() => {
  if (scoredCandidates.value.length === 0) return null
  const sum = scoredCandidates.value.reduce((total, item) => total + item.ai_score, 0)
  return Math.round(sum / scoredCandidates.value.length)
})

const stats = computed(() => ({
  totalCandidates: candidatesStore.candidates.length,
  openJobs: jobsStore.jobs.filter(job => job.status === 'open').length,
  shortlisted: candidatesStore.candidates.filter(candidate => candidate.status === 'shortlisted').length,
  avgScore: averageScore.value,
}))

const actionQueue = computed(() => ({
  waitingExtraction: candidatesStore.candidates.filter(candidate => candidate.status === 'processing'),
  needsScoring: candidatesStore.candidates.filter(candidate =>
    (candidate.status === 'ready' || candidate.status === 'shortlisted') && candidate.ai_score === null
  ),
  highFit: candidatesStore.candidates
    .filter(candidate => Number.isFinite(candidate.ai_score) && candidate.ai_score >= 75 && candidate.status !== 'rejected')
    .sort((a, b) => b.ai_score - a.ai_score)
    .slice(0, 4),
}))

const recentCandidates = computed(() =>
  [...candidatesStore.candidates]
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    .slice(0, 6)
)

const activeJobs = computed(() =>
  [...jobsStore.jobs]
    .sort((a, b) => (b.candidates || 0) - (a.candidates || 0))
    .slice(0, 5)
)

onMounted(async () => {
  try {
    await Promise.all([candidatesStore.fetchCandidates(), jobsStore.fetchJobs()])
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <section class="bg-slate-900 text-white rounded-3xl p-6 md:p-8 shadow-lg">
      <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-cyan-200 uppercase tracking-[0.15em] text-xs">Recruiter Control Center</p>
          <h1 class="text-3xl font-semibold mt-2">Hiring Priorities</h1>
          <p class="text-slate-300 mt-1">Focus first on candidates ready for scoring and final review.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <router-link to="/candidates">
            <Button variant="primary">Upload Candidate</Button>
          </router-link>
          <router-link to="/jobs">
            <Button variant="secondary">Create Job</Button>
          </router-link>
        </div>
      </div>
    </section>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <template v-else>
      <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <Card>
          <p class="text-sm text-slate-500">Candidates</p>
          <p class="text-3xl font-bold text-slate-900 mt-1">{{ stats.totalCandidates }}</p>
        </Card>
        <Card>
          <p class="text-sm text-slate-500">Open Jobs</p>
          <p class="text-3xl font-bold text-slate-900 mt-1">{{ stats.openJobs }}</p>
        </Card>
        <Card>
          <p class="text-sm text-slate-500">Shortlisted</p>
          <p class="text-3xl font-bold text-slate-900 mt-1">{{ stats.shortlisted }}</p>
        </Card>
        <Card>
          <p class="text-sm text-slate-500">Average AI Score</p>
          <p class="text-3xl font-bold text-slate-900 mt-1">{{ stats.avgScore ?? 'N/A' }}</p>
        </Card>
      </section>

      <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <Card class="xl:col-span-2">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Action Queue</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4">
              <p class="text-xs uppercase tracking-wide text-amber-700">CV Extraction</p>
              <p class="text-2xl font-semibold text-amber-900 mt-1">{{ actionQueue.waitingExtraction.length }}</p>
              <p class="text-xs text-amber-700 mt-2">Candidates still processing.</p>
            </div>
            <div class="rounded-xl bg-cyan-50 border border-cyan-200 p-4">
              <p class="text-xs uppercase tracking-wide text-cyan-700">Needs Scoring</p>
              <p class="text-2xl font-semibold text-cyan-900 mt-1">{{ actionQueue.needsScoring.length }}</p>
              <p class="text-xs text-cyan-700 mt-2">Ready applicants missing job-fit score.</p>
            </div>
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4">
              <p class="text-xs uppercase tracking-wide text-emerald-700">High Fit</p>
              <p class="text-2xl font-semibold text-emerald-900 mt-1">{{ actionQueue.highFit.length }}</p>
              <p class="text-xs text-emerald-700 mt-2">Scored 75+ and ready for final decision.</p>
            </div>
          </div>
        </Card>

        <Card>
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Top Matches</h2>
          <div v-if="actionQueue.highFit.length === 0" class="text-sm text-slate-500 py-6 text-center">
            No high-fit candidates yet.
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="candidate in actionQueue.highFit"
              :key="candidate.id"
              class="p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer"
              @click="$router.push(`/candidates/${candidate.id}`)"
            >
              <div class="flex items-center justify-between">
                <p class="font-medium text-slate-900">{{ candidate.name }}</p>
                <span class="text-emerald-600 font-semibold">{{ candidate.ai_score }}</span>
              </div>
              <p class="text-xs text-slate-500 mt-1">{{ candidate.job_position?.title || 'No linked job yet' }}</p>
            </div>
          </div>
        </Card>
      </section>

      <section class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <Card>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Recent Candidates</h2>
            <router-link class="text-sm text-cyan-700 hover:text-cyan-800" to="/candidates">View all</router-link>
          </div>
          <div v-if="recentCandidates.length === 0" class="text-sm text-slate-500 text-center py-8">
            No candidates uploaded yet.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="candidate in recentCandidates"
              :key="candidate.id"
              class="flex items-center justify-between rounded-xl border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer"
              @click="$router.push(`/candidates/${candidate.id}`)"
            >
              <div class="min-w-0">
                <p class="font-medium text-slate-900 truncate">{{ candidate.name }}</p>
                <p class="text-xs text-slate-500 truncate">{{ candidate.email || 'No email' }}</p>
              </div>
              <div class="text-right">
                <Badge :variant="getStatusVariant(candidate.status)">{{ candidate.status }}</Badge>
                <p class="text-xs text-slate-400 mt-1">{{ formatDate(candidate.created_at) }}</p>
              </div>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Most Active Jobs</h2>
            <router-link class="text-sm text-cyan-700 hover:text-cyan-800" to="/jobs">View all</router-link>
          </div>
          <div v-if="activeJobs.length === 0" class="text-sm text-slate-500 text-center py-8">
            No jobs created yet.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="job in activeJobs"
              :key="job.id"
              class="flex items-center justify-between rounded-xl border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer"
              @click="$router.push(`/jobs/${job.id}`)"
            >
              <div class="min-w-0">
                <p class="font-medium text-slate-900 truncate">{{ job.title }}</p>
                <p class="text-xs text-slate-500">{{ job.candidates || 0 }} candidate{{ (job.candidates || 0) === 1 ? '' : 's' }}</p>
              </div>
              <div class="text-right">
                <Badge :variant="getStatusVariant(job.status)">{{ job.status }}</Badge>
                <p class="text-xs text-slate-400 mt-1">{{ formatDate(job.created_at) }}</p>
              </div>
            </div>
          </div>
        </Card>
      </section>
    </template>
  </div>
</template>
