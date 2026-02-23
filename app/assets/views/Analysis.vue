<script setup>
import { ref, onMounted } from 'vue'
import { useJobsStore } from '../stores/jobs.js'
import { useCandidatesStore } from '../stores/candidates.js'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'

const jobsStore = useJobsStore()
const candidatesStore = useCandidatesStore()

const activeTab = ref('rank')
const selectedJob = ref(null)
const selectedCandidates = ref([])
const rankingResults = ref([])
const comparisonData = ref([])
const interviewQuestions = ref([])
const isLoading = ref(false)

const getScoreColor = (score) => {
  if (score <= 40) return 'text-rose-500'
  if (score <= 70) return 'text-amber-500'
  return 'text-emerald-500'
}

const runRanking = async () => {
  if (!selectedJob.value || selectedCandidates.value.length === 0) return
  isLoading.value = true
  try {
    const response = await fetch('http://localhost:8080/api/analysis/rank', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        job_id: selectedJob.value,
        candidate_ids: selectedCandidates.value,
      }),
    })
    rankingResults.value = await response.json()
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

const runComparison = async () => {
  if (selectedCandidates.value.length < 2) return
  isLoading.value = true
  try {
    const response = await fetch('http://localhost:8080/api/analysis/compare', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ candidate_ids: selectedCandidates.value }),
    })
    comparisonData.value = await response.json()
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

const runInterviewQuestions = async () => {
  if (!selectedJob.value || selectedCandidates.value.length === 0) return
  isLoading.value = true
  try {
    const response = await fetch('http://localhost:8080/api/analysis/interview-questions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        candidate_id: selectedCandidates.value[0],
        job_id: selectedJob.value,
      }),
    })
    const data = await response.json()
    interviewQuestions.value = data.questions || []
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

const toggleCandidateSelection = (id) => {
  const index = selectedCandidates.value.indexOf(id)
  if (index === -1) {
    if (activeTab.value === 'compare' && selectedCandidates.value.length >= 3) return
    selectedCandidates.value.push(id)
  } else {
    selectedCandidates.value.splice(index, 1)
  }
}

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
          Rank
        </button>
        <button
          class="py-3 px-1 border-b-2 font-medium text-sm transition-colors"
          :class="activeTab === 'compare' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="activeTab = 'compare'"
        >
          Compare
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

    <!-- Rank Tab -->
    <div v-if="activeTab === 'rank'" class="space-y-6">
      <Card padding="md">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Select Job and Candidates</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Job Position</label>
            <select
              v-model="selectedJob"
              class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null">Select a job</option>
              <option v-for="job in jobsStore.jobs" :key="job.id" :value="job.id">
                {{ job.title }}
              </option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Candidates</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="candidate in candidatesStore.candidates"
              :key="candidate.id"
              class="px-3 py-2 rounded-lg border transition-colors"
              :class="selectedCandidates.includes(candidate.id) ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'"
              @click="toggleCandidateSelection(candidate.id)"
            >
              {{ candidate.name }}
            </button>
          </div>
        </div>

        <Button variant="primary" class="mt-4" :disabled="!selectedJob || selectedCandidates.length === 0 || isLoading" @click="runRanking">
          <Spinner v-if="isLoading" size="sm" class="mr-2" />
          Run Ranking
        </Button>
      </Card>

      <Card v-if="rankingResults.length > 0" padding="md">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Ranking Results</h3>
        <div class="space-y-3">
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

    <!-- Compare Tab -->
    <div v-if="activeTab === 'compare'" class="space-y-6">
      <Card padding="md">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Select 2-3 Candidates to Compare</h3>
        <div class="flex flex-wrap gap-2 mb-4">
          <button
            v-for="candidate in candidatesStore.candidates"
            :key="candidate.id"
            class="px-3 py-2 rounded-lg border transition-colors"
            :class="selectedCandidates.includes(candidate.id) ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'"
            @click="toggleCandidateSelection(candidate.id)"
          >
            {{ candidate.name }}
          </button>
        </div>
        <Button variant="primary" :disabled="selectedCandidates.length < 2 || isLoading" @click="runComparison">
          <Spinner v-if="isLoading" size="sm" class="mr-2" />
          Compare
        </Button>
      </Card>

      <Card v-if="comparisonData.length > 0" padding="md">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Comparison Results</h3>
        <p class="text-slate-500">Comparison data will appear here</p>
      </Card>
    </div>

    <!-- Interview Questions Tab -->
    <div v-if="activeTab === 'interview'" class="space-y-6">
      <Card padding="md">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Generate Interview Questions</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Job Position</label>
            <select
              v-model="selectedJob"
              class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null">Select a job</option>
              <option v-for="job in jobsStore.jobs" :key="job.id" :value="job.id">
                {{ job.title }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Candidate</label>
            <select
              v-model="selectedCandidates"
              class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="[]">Select a candidate</option>
              <option v-for="candidate in candidatesStore.candidates" :key="candidate.id" :value="[candidate.id]">
                {{ candidate.name }}
              </option>
            </select>
          </div>
        </div>
        <Button variant="primary" :disabled="!selectedJob || selectedCandidates.length === 0 || isLoading" @click="runInterviewQuestions">
          <Spinner v-if="isLoading" size="sm" class="mr-2" />
          Generate Questions
        </Button>
      </Card>

      <Card v-if="interviewQuestions.length > 0" padding="md">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Interview Questions</h3>
        <ul class="space-y-3">
          <li v-for="(question, index) in interviewQuestions" :key="index" class="flex gap-3">
            <span class="text-indigo-600 font-medium">{{ index + 1 }}.</span>
            <span class="text-slate-700">{{ question }}</span>
          </li>
        </ul>
      </Card>
    </div>
  </div>
</template>
