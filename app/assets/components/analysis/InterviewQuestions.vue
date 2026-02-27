<script setup>
import { ref, computed } from 'vue'
import { useCandidatesStore } from '../../stores/candidates.js'
import { useJobsStore } from '../../stores/jobs.js'
import { analysisApi } from '../../api/client.js'
import Button from '../ui/Button.vue'
import Spinner from '../ui/Spinner.vue'

const candidatesStore = useCandidatesStore()
const jobsStore = useJobsStore()

const selectedCandidateId = ref(null)
const selectedJobId = ref(null)
const questions = ref([])
const isLoading = ref(false)
const error = ref(null)
const copied = ref(false)
const copiedIndex = ref(null)

const hasQuestions = computed(() => questions.value.length > 0)

const generateQuestions = async () => {
  if (!selectedCandidateId.value) return

  isLoading.value = true
  error.value = null
  questions.value = []

  try {
    const response = await analysisApi.interviewQuestions(
      selectedCandidateId.value,
      selectedJobId.value
    )
    questions.value = response.data.questions || []
  } catch (e) {
    console.error(e)
    error.value = 'Failed to generate interview questions'
  } finally {
    isLoading.value = false
  }
}

const copyQuestion = async (question, index) => {
  try {
    await navigator.clipboard.writeText(question)
    copiedIndex.value = index
    setTimeout(() => {
      copiedIndex.value = null
    }, 2000)
  } catch (e) {
    console.error(e)
  }
}

const copyAllQuestions = async () => {
  const allQuestions = questions.value
    .map((q, i) => `${i + 1}. ${q.question || q}`)
    .join('\n\n')

  try {
    await navigator.clipboard.writeText(allQuestions)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (e) {
    console.error(e)
  }
}

</script>

<template>
  <div class="space-y-6">
    <!-- Step 1: Select Candidate and Job -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">Select Candidate and Job</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Candidate</label>
          <select
            v-model="selectedCandidateId"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
          >
            <option :value="null">Select a candidate</option>
            <option v-for="candidate in candidatesStore.candidates" :key="candidate.id" :value="candidate.id">
              {{ candidate.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Job Position (optional)</label>
          <select
            v-model="selectedJobId"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
          >
            <option :value="null">General questions</option>
            <option v-for="job in jobsStore.jobs" :key="job.id" :value="job.id">
              {{ job.title }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Step 2: Generate Questions -->
    <div class="flex items-center gap-4">
      <Button
        variant="primary"
        :disabled="!selectedCandidateId || isLoading"
        @click="generateQuestions"
      >
        <Spinner v-if="isLoading" size="sm" class="mr-2" />
        Generate Questions
      </Button>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-rose-50 border border-rose-200 rounded-lg p-4 text-rose-700">
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white rounded-xl border border-slate-200 p-12 shadow-sm">
      <div class="flex flex-col items-center justify-center">
        <Spinner size="lg" />
        <p class="mt-4 text-slate-500">Generating interview questions...</p>
      </div>
    </div>

    <!-- Step 3: Results -->
    <div v-if="hasQuestions && !isLoading" class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900">Interview Questions</h3>
        <Button variant="secondary" size="sm" @click="copyAllQuestions">
          {{ copied ? '✓ Copied!' : 'Copy All' }}
        </Button>
      </div>

      <div class="space-y-3">
        <div
          v-for="(question, index) in questions"
          :key="index"
          class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm"
        >
          <div class="flex items-start gap-4">
            <div class="w-7 h-7 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-sm font-semibold shrink-0">
              {{ index + 1 }}
            </div>
            <div class="flex-1">
              <p class="text-slate-800 font-medium">{{ question.question || question }}</p>
              <p v-if="question.reason" class="text-sm text-slate-500 mt-2">
                <span class="font-medium">Why:</span> {{ question.reason }}
              </p>
            </div>
            <Button variant="ghost" size="sm" @click="copyQuestion(question.question || question, index)">
              {{ copiedIndex === index ? '✓' : 'Copy' }}
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!hasQuestions && !isLoading && !error" class="bg-white rounded-xl border border-slate-200 p-8 shadow-sm text-center">
      <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="text-slate-500">Select a candidate and click "Generate Questions"</p>
    </div>
  </div>
</template>
