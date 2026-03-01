<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCandidatesStore } from '../stores/candidates.js'
import { useConversationsStore } from '../stores/conversations.js'
import { useJobsStore } from '../stores/jobs.js'
import { candidatesApi } from '../api/client.js'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import Card from '../components/ui/Card.vue'
import Modal from '../components/ui/Modal.vue'
import Spinner from '../components/ui/Spinner.vue'

const route = useRoute()
const router = useRouter()
const candidatesStore = useCandidatesStore()
const jobsStore = useJobsStore()
const conversationsStore = useConversationsStore()

const candidateId = computed(() => Number(route.params.id))
const pollInterval = ref(null)
const isProcessing = ref(false)
const interviewQuestions = ref([])
const selectedJobId = ref(null)
const selectedJobForScore = ref(null)

const showQuestionsModal = ref(false)
const showShortlistModal = ref(false)
const showRejectModal = ref(false)
const showScoreModal = ref(false)

const statusLoading = ref(false)
const scoreLoading = ref(false)
const toast = ref({ show: false, message: '', type: 'success' })

const candidate = computed(() => candidatesStore.currentCandidate)
const extractedData = computed(() => candidate.value?.ai_extracted_data || {})
const skills = computed(() => extractedData.value.skills || [])
const education = computed(() => extractedData.value.education || [])
const languages = computed(() => extractedData.value.languages || [])
const yearsExperience = computed(() => extractedData.value.years_experience ?? null)
const phone = computed(() => extractedData.value.phone || null)
const achievements = computed(() => extractedData.value.achievements || [])
const engineeringPractices = computed(() => extractedData.value.engineering_practices || [])
const projectComplexity = computed(() => extractedData.value.project_complexity || null)
const careerProgression = computed(() => extractedData.value.career_progression || null)

const availableJobs = computed(() => jobsStore.jobs.filter(job => job.status !== 'closed'))

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 3000)
}

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
  }
  return variants[status] || 'default'
}

const getScoreColor = (score) => {
  if (score === null || score === undefined) return 'bg-slate-300'
  if (score <= 40) return 'bg-rose-500'
  if (score <= 70) return 'bg-amber-500'
  return 'bg-emerald-500'
}

const getScoreTextColor = (score) => {
  if (score === null || score === undefined) return 'text-slate-400'
  if (score <= 40) return 'text-rose-600'
  if (score <= 70) return 'text-amber-600'
  return 'text-emerald-600'
}

const isShortlistedOrRejected = computed(() => {
  return candidate.value?.status === 'shortlisted' || candidate.value?.status === 'rejected'
})

const startPolling = () => {
  if (pollInterval.value || candidate.value?.status !== 'processing') return
  pollInterval.value = setInterval(async () => {
    await candidatesStore.fetchCandidate(candidateId.value)
    if (candidate.value?.status !== 'processing') {
      stopPolling()
    }
  }, 3000)
}

const stopPolling = () => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value)
    pollInterval.value = null
  }
}

const handleShortlist = async () => {
  statusLoading.value = true
  try {
    await candidatesStore.updateCandidateStatus(candidateId.value, 'shortlisted')
    showShortlistModal.value = false
    showToast('Candidate shortlisted')
  } catch (error) {
    console.error(error)
    showToast('Failed to shortlist candidate', 'error')
  } finally {
    statusLoading.value = false
  }
}

const handleReject = async () => {
  statusLoading.value = true
  try {
    await candidatesStore.updateCandidateStatus(candidateId.value, 'rejected')
    showRejectModal.value = false
    showToast('Candidate rejected')
  } catch (error) {
    console.error(error)
    showToast('Failed to reject candidate', 'error')
  } finally {
    statusLoading.value = false
  }
}

const handleRestore = async () => {
  statusLoading.value = true
  try {
    await candidatesStore.updateCandidateStatus(candidateId.value, 'ready')
    showToast('Candidate restored to ready')
  } catch (error) {
    console.error(error)
    showToast('Failed to restore candidate', 'error')
  } finally {
    statusLoading.value = false
  }
}

const openScoreModal = () => {
  selectedJobForScore.value = candidate.value?.job_position?.id || null
  showScoreModal.value = true
}

const submitScore = async () => {
  if (!selectedJobForScore.value) return
  scoreLoading.value = true
  try {
    await candidatesStore.scoreCandidate(candidateId.value, selectedJobForScore.value)
    await candidatesStore.fetchCandidate(candidateId.value)
    showScoreModal.value = false
    showToast('Score updated')
  } catch (error) {
    console.error(error)
    showToast('Failed to calculate score', 'error')
  } finally {
    scoreLoading.value = false
  }
}

const startChat = async () => {
  try {
    const conversation = await conversationsStore.createConversation(candidateId.value)
    router.push(`/chat/${conversation.id}`)
  } catch (error) {
    console.error(error)
    showToast('Failed to start chat', 'error')
  }
}

const generateQuestions = async () => {
  isProcessing.value = true
  showQuestionsModal.value = true
  try {
    const response = await candidatesApi.interviewQuestions(candidateId.value, selectedJobId.value)
    interviewQuestions.value = response.data.questions || []
  } catch (error) {
    console.error(error)
    interviewQuestions.value = []
    showToast('Failed to generate interview questions', 'error')
  } finally {
    isProcessing.value = false
  }
}

onMounted(async () => {
  try {
    await Promise.all([candidatesStore.fetchCandidate(candidateId.value), jobsStore.fetchJobs()])
    selectedJobId.value = candidate.value?.job_position?.id || null
    startPolling()
  } catch (error) {
    console.error(error)
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="toast.show"
      class="fixed top-5 right-5 z-50 px-4 py-2 rounded-xl shadow-lg text-white"
      :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'"
    >
      {{ toast.message }}
    </div>

    <section class="flex items-center gap-3">
      <button class="p-2 rounded-xl border border-slate-300 hover:bg-slate-100" @click="$router.back()">
        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Candidate Review</h1>
        <p class="text-sm text-slate-500">Profile details, AI output, and hiring actions.</p>
      </div>
    </section>

    <div v-if="candidatesStore.loading && !candidate" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <template v-else-if="candidate">
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 space-y-5">
          <Card>
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-6">
              <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-cyan-100 flex items-center justify-center">
                  <span class="text-2xl font-bold text-cyan-700">
                    {{ (candidate.name || '?').charAt(0).toUpperCase() }}
                  </span>
                </div>
                <div>
                  <h2 class="text-2xl font-semibold text-slate-900">{{ candidate.name }}</h2>
                  <p class="text-slate-600">{{ candidate.email || 'No email available' }}</p>
                  <p v-if="phone" class="text-sm text-slate-500 mt-1">{{ phone }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <Badge :variant="getStatusVariant(candidate.status)">
                  <span v-if="candidate.status === 'processing'" class="flex items-center gap-1">
                    <Spinner size="sm" />
                    Processing
                  </span>
                  <span v-else>{{ candidate.status }}</span>
                </Badge>
              </div>
            </div>

            <div class="mb-6">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-slate-700">AI Fit Score</span>
                <span class="text-lg font-semibold" :class="getScoreTextColor(candidate.ai_score)">
                  {{ candidate.ai_score ?? 'N/A' }}
                </span>
              </div>
              <div class="w-full h-3 bg-slate-200 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="getScoreColor(candidate.ai_score)"
                  :style="{ width: candidate.ai_score ? `${candidate.ai_score}%` : '0%' }"
                />
              </div>
              <p class="text-xs text-slate-500 mt-2">
                Linked job: {{ candidate.job_position?.title || 'Not linked yet' }}
              </p>
            </div>

            <div v-if="candidate.ai_summary" class="p-4 rounded-xl bg-slate-100">
              <h3 class="text-sm font-semibold text-slate-700 mb-2">AI Summary</h3>
              <p class="text-slate-700">{{ candidate.ai_summary }}</p>
            </div>
          </Card>

          <Card v-if="Object.keys(extractedData).length > 0">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Extracted Profile Data</h3>

            <div v-if="skills.length > 0" class="mb-4">
              <p class="text-sm font-medium text-slate-700 mb-2">Skills</p>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="skill in skills"
                  :key="skill"
                  class="px-3 py-1 rounded-full text-sm bg-cyan-100 text-cyan-800"
                >
                  {{ skill }}
                </span>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-if="yearsExperience !== null">
                <p class="text-sm font-medium text-slate-700 mb-1">Experience</p>
                <p class="text-slate-600">{{ yearsExperience }} years</p>
              </div>
              <div v-if="languages.length > 0">
                <p class="text-sm font-medium text-slate-700 mb-1">Languages</p>
                <p class="text-slate-600">{{ languages.join(', ') }}</p>
              </div>
            </div>

            <div v-if="education.length > 0" class="mt-4">
              <p class="text-sm font-medium text-slate-700 mb-2">Education</p>
              <ul class="space-y-1">
                <li v-for="entry in education" :key="entry" class="text-slate-600">• {{ entry }}</li>
              </ul>
            </div>

            <div v-if="achievements.length > 0" class="mt-6 border-t border-slate-100 pt-4">
              <p class="text-sm font-medium text-slate-700 mb-2 text-emerald-600">Key Achievements</p>
              <ul class="space-y-2">
                <li v-for="(achievement, idx) in achievements" :key="idx" class="text-sm text-slate-600 flex items-start gap-2">
                  <span class="text-emerald-500">•</span>
                  {{ achievement }}
                </li>
              </ul>
            </div>

            <div v-if="engineeringPractices.length > 0" class="mt-6 border-t border-slate-100 pt-4">
              <p class="text-sm font-medium text-slate-700 mb-2">Engineering Practices</p>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="practice in engineeringPractices"
                  :key="practice"
                  class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-xs border border-slate-200"
                >
                  {{ practice }}
                </span>
              </div>
            </div>

            <div v-if="projectComplexity || careerProgression" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 border-t border-slate-100 pt-4">
              <div v-if="projectComplexity">
                <p class="text-sm font-medium text-slate-700 mb-1 text-indigo-600">Project Complexity</p>
                <p class="text-sm text-slate-600 italic">{{ projectComplexity }}</p>
              </div>
              <div v-if="careerProgression">
                <p class="text-sm font-medium text-slate-700 mb-1 text-cyan-600">Career Progression</p>
                <p class="text-sm text-slate-600 italic">{{ careerProgression }}</p>
              </div>
            </div>
          </Card>
        </div>

        <div class="space-y-5">
          <Card>
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Recruiter Actions</h3>
            <div class="space-y-3">
              <template v-if="!isShortlistedOrRejected">
                <Button variant="success" class="w-full" @click="showShortlistModal = true">Shortlist</Button>
                <Button variant="danger" class="w-full" @click="showRejectModal = true">Reject</Button>
              </template>
              <Button
                v-else
                variant="secondary"
                class="w-full"
                :disabled="statusLoading"
                @click="handleRestore"
              >
                Restore to Ready
              </Button>
              <Button variant="secondary" class="w-full" @click="openScoreModal">Calculate Score</Button>
              <Button variant="secondary" class="w-full" @click="generateQuestions">Interview Questions</Button>
              <Button variant="primary" class="w-full" @click="startChat">Chat with CV</Button>
            </div>
          </Card>

          <Card>
            <p class="text-sm text-slate-500">Created on {{ formatDate(candidate.created_at) }}</p>
          </Card>
        </div>
      </div>
    </template>

    <Modal v-model="showShortlistModal" title="Shortlist Candidate">
      <p class="text-slate-600">
        Shortlist <strong>{{ candidate?.name }}</strong> for the next stage?
      </p>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showShortlistModal = false">Cancel</Button>
        <Button variant="success" :disabled="statusLoading" @click="handleShortlist">
          <Spinner v-if="statusLoading" size="sm" class="mr-2" />
          Shortlist
        </Button>
      </div>
    </Modal>

    <Modal v-model="showRejectModal" title="Reject Candidate">
      <p class="text-slate-600">
        Reject <strong>{{ candidate?.name }}</strong>? You can restore later from this page.
      </p>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showRejectModal = false">Cancel</Button>
        <Button variant="danger" :disabled="statusLoading" @click="handleReject">
          <Spinner v-if="statusLoading" size="sm" class="mr-2" />
          Reject
        </Button>
      </div>
    </Modal>

    <Modal v-model="showScoreModal" title="Calculate Job Match Score">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Job Position</label>
          <select
            v-model="selectedJobForScore"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
          >
            <option :value="null" disabled>Select a job</option>
            <option v-for="job in availableJobs" :key="job.id" :value="job.id">{{ job.title }}</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showScoreModal = false">Cancel</Button>
        <Button variant="primary" :disabled="!selectedJobForScore || scoreLoading" @click="submitScore">
          <Spinner v-if="scoreLoading" size="sm" class="mr-2" />
          Calculate
        </Button>
      </div>
    </Modal>

    <Modal v-model="showQuestionsModal" title="Interview Questions">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Job Position (optional)</label>
          <select
            v-model="selectedJobId"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
          >
            <option :value="null">General questions</option>
            <option v-for="job in jobsStore.jobs" :key="job.id" :value="job.id">{{ job.title }}</option>
          </select>
        </div>
        <div v-if="isProcessing" class="flex justify-center py-8">
          <Spinner size="lg" />
        </div>
        <div v-else-if="interviewQuestions.length > 0" class="space-y-2 max-h-80 overflow-y-auto">
          <div
            v-for="(question, index) in interviewQuestions"
            :key="index"
            class="p-3 rounded-xl bg-slate-100 text-sm text-slate-700"
          >
            {{ index + 1 }}. {{ question.question || question }}
          </div>
        </div>
        <p v-else class="text-slate-500 text-center py-6">Click generate to create tailored questions.</p>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showQuestionsModal = false">Close</Button>
        <Button variant="primary" :disabled="isProcessing" @click="generateQuestions">Generate</Button>
      </div>
    </Modal>
  </div>
</template>
