<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useJobsStore } from '../../stores/jobs.js'
import { useCandidatesStore } from '../../stores/candidates.js'
import { analysisApi } from '../../api/client.js'
import Button from '../ui/Button.vue'
import Spinner from '../ui/Spinner.vue'
import Badge from '../ui/Badge.vue'

const router = useRouter()
const jobsStore = useJobsStore()
const candidatesStore = useCandidatesStore()

const selectedJobId = ref(null)
const selectedCandidateIds = ref([])
const rankingResults = ref([])
const isLoading = ref(false)
const error = ref(null)

// Filter candidates with status 'ready' or 'shortlisted'
const eligibleCandidates = computed(() => {
  return candidatesStore.candidates.filter(c =>
    c.status === 'ready' || c.status === 'shortlisted'
  )
})

const getStatusVariant = (status) => {
  const variants = {
    new: 'default',
    processing: 'warning',
    ready: 'success',
    shortlisted: 'info',
    rejected: 'danger',
  }
  return variants[status] || 'default'
}

const getScoreColor = (score) => {
  if (score === null) return 'bg-slate-200'
  if (score <= 40) return 'bg-rose-500'
  if (score <= 70) return 'bg-amber-500'
  return 'bg-emerald-500'
}

const getScoreTextColor = (score) => {
  if (score === null) return 'text-slate-400'
  if (score <= 40) return 'text-rose-500'
  if (score <= 70) return 'text-amber-500'
  return 'text-emerald-500'
}

const getRankBadgeClass = (index) => {
  if (index === 0) return 'bg-yellow-500 text-white' // Gold
  if (index === 1) return 'bg-slate-400 text-white'   // Silver
  if (index === 2) return 'bg-amber-600 text-white'   // Bronze
  return 'bg-slate-200 text-slate-600'
}

const toggleAll = () => {
  if (selectedCandidateIds.value.length === eligibleCandidates.value.length) {
    selectedCandidateIds.value = []
  } else {
    selectedCandidateIds.value = eligibleCandidates.value.map(c => c.id)
  }
}

const toggleCandidate = (id) => {
  const index = selectedCandidateIds.value.indexOf(id)
  if (index === -1) {
    selectedCandidateIds.value.push(id)
  } else {
    selectedCandidateIds.value.splice(index, 1)
  }
}

const runRanking = async () => {
  if (!selectedJobId.value || selectedCandidateIds.value.length === 0) return
  isLoading.value = true
  error.value = null
  try {
    const response = await analysisApi.rank(selectedJobId.value, selectedCandidateIds.value)
    const payload = response?.data ?? {}
    rankingResults.value = Array.isArray(payload) ? payload : (payload.ranked ?? [])
  } catch (e) {
    console.error(e)
    rankingResults.value = []
    error.value = e?.response?.data?.error || 'Failed to rank candidates'
  } finally {
    isLoading.value = false
  }
}

const viewProfile = (id) => {
  router.push(`/candidates/${id}`)
}

const shortlistCandidate = async (id) => {
  try {
    await candidatesStore.updateCandidateStatus(id, 'shortlisted')
    // Update in results
    const result = rankingResults.value.find(r => r.candidate_id === id)
    if (result) {
      result.status = 'shortlisted'
    }
  } catch (e) {
    console.error(e)
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Step 1: Select Job -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">Select Job</h3>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Job Position</label>
        <select
          v-model="selectedJobId"
          class="w-full md:w-80 px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500"
        >
          <option :value="null">Select a job</option>
          <option v-for="job in jobsStore.jobs" :key="job.id" :value="job.id">
            {{ job.title }}
          </option>
        </select>
      </div>
    </div>

    <!-- Step 2: Select Candidates -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-slate-900">Select Candidates</h3>
        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
          <input
            type="checkbox"
            :checked="selectedCandidateIds.length === eligibleCandidates.length && eligibleCandidates.length > 0"
            @change="toggleAll"
            class="w-4 h-4 text-cyan-700 rounded border-slate-300 focus:ring-cyan-500"
          >
          Select All
        </label>
      </div>

      <div v-if="eligibleCandidates.length === 0" class="text-slate-500 text-sm py-4">
        No candidates with status "ready" or "shortlisted" available.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <label
          v-for="candidate in eligibleCandidates"
          :key="candidate.id"
          class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200"
          :class="selectedCandidateIds.includes(candidate.id)
            ? 'bg-cyan-50 border-cyan-300'
            : 'bg-white border-slate-200 hover:border-slate-300'"
        >
          <input
            type="checkbox"
            :checked="selectedCandidateIds.includes(candidate.id)"
            @change="toggleCandidate(candidate.id)"
            class="w-4 h-4 text-cyan-700 rounded border-slate-300 focus:ring-cyan-500"
          >
          <div class="flex-1 min-w-0">
            <p class="font-medium text-slate-900 truncate">{{ candidate.name }}</p>
            <div class="flex items-center gap-2 mt-1">
              <Badge :variant="getStatusVariant(candidate.status)" size="sm">{{ candidate.status }}</Badge>
              <span v-if="candidate.ai_score" class="text-sm text-slate-500">
                Score: {{ candidate.ai_score }}
              </span>
            </div>
          </div>
        </label>
      </div>
    </div>

    <!-- Step 3: Run Ranking -->
    <div class="flex items-center gap-4">
      <Button
        variant="primary"
        :disabled="!selectedJobId || selectedCandidateIds.length === 0 || isLoading"
        @click="runRanking"
      >
        <Spinner v-if="isLoading" size="sm" class="mr-2" />
        Rank Candidates
      </Button>
      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
        AI Powered
      </span>
    </div>

    <div v-if="error" class="bg-rose-50 border border-rose-200 rounded-lg p-4 text-rose-700 text-sm">
      {{ error }}
    </div>

    <!-- Step 4: Results -->
    <div v-if="rankingResults.length > 0" class="space-y-4">
      <h3 class="text-lg font-semibold text-slate-900">Ranking Results</h3>

      <div
        v-for="(result, index) in rankingResults"
        :key="result.candidate_id"
        class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <!-- Rank Badge -->
          <div
            class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold shrink-0"
            :class="getRankBadgeClass(index)"
          >
            #{{ index + 1 }}
          </div>

          <!-- Candidate Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-2">
              <div>
                <h4 class="text-xl font-bold text-slate-900">{{ result.name }}</h4>
                <p class="text-sm text-slate-500">{{ result.email }}</p>
              </div>
              <div class="text-right">
                <p class="text-3xl font-bold" :class="getScoreTextColor(result.score)">{{ result.score }}</p>
                <p class="text-xs text-slate-400">AI Score</p>
              </div>
            </div>

            <!-- Score Bar -->
            <div class="mb-4">
              <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="getScoreColor(result.score)"
                  :style="{ width: result.score + '%' }"
                />
              </div>
            </div>

            <!-- Summary -->
            <div v-if="result.summary" class="mb-4 p-3 bg-slate-50 rounded-lg">
              <p class="text-sm text-slate-600 italic">"{{ result.summary }}"</p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
              <Button variant="secondary" size="sm" @click="viewProfile(result.candidate_id)">
                View Profile
              </Button>
              <Button
                v-if="result.status !== 'shortlisted'"
                variant="success"
                size="sm"
                @click="shortlistCandidate(result.candidate_id)"
              >
                Shortlist
              </Button>
              <Badge v-else variant="info" size="sm">
                Shortlisted
              </Badge>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white rounded-xl border border-slate-200 p-12 shadow-sm">
      <div class="flex flex-col items-center justify-center">
        <Spinner size="lg" />
        <p class="mt-4 text-slate-500">Analyzing candidates...</p>
      </div>
    </div>
  </div>
</template>
