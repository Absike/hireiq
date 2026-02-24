<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCandidatesStore } from '../stores/candidates.js'
import { useJobsStore } from '../stores/jobs.js'
import { useConversationsStore } from '../stores/conversations.js'
import { candidatesApi } from '../api/client.js'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Badge from '../components/ui/Badge.vue'
import Spinner from '../components/ui/Spinner.vue'
import Modal from '../components/ui/Modal.vue'

const route = useRoute()
const router = useRouter()
const candidatesStore = useCandidatesStore()
const jobsStore = useJobsStore()
const conversationsStore = useConversationsStore()

const candidateId = computed(() => Number(route.params.id))
const pollInterval = ref(null)
const showQuestionsModal = ref(false)
const showChatModal = ref(false)
const interviewQuestions = ref([])
const isProcessing = ref(false)
const chatQuestion = ref('')
const chatAnswer = ref('')
const selectedJobId = ref(null)

// Status update modals
const showShortlistModal = ref(false)
const showRejectModal = ref(false)
const statusLoading = ref(false)
const toast = ref({ show: false, message: '', type: 'success' })

const extractedData = computed(() => {
  if (!candidatesStore.currentCandidate?.ai_extracted_data) return null
  return candidatesStore.currentCandidate.ai_extracted_data
})

const skills = computed(() => extractedData.value?.skills || [])
const yearsExperience = computed(() => extractedData.value?.years_experience || 'N/A')
const education = computed(() => extractedData.value?.education || [])
const languages = computed(() => extractedData.value?.languages || [])

// Computed for button visibility
const isShortlistedOrRejected = computed(() => {
  const status = candidatesStore.currentCandidate?.status
  return status === 'shortlisted' || status === 'rejected'
})

const formatDate = (date) => {
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
  if (score === null) return 'bg-slate-200'
  if (score <= 40) return 'bg-rose-500'
  if (score <= 70) return 'bg-amber-500'
  return 'bg-emerald-500'
}

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 3000)
}

const startPolling = () => {
  if (candidatesStore.currentCandidate?.status === 'processing') {
    pollInterval.value = window.setInterval(async () => {
      await candidatesStore.fetchCandidate(candidateId.value)
      if (candidatesStore.currentCandidate?.status !== 'processing') {
        stopPolling()
      }
    }, 3000)
  }
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
    showToast('Candidate shortlisted!')
  } catch (e) {
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
  } catch (e) {
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
  } catch (e) {
    showToast('Failed to restore candidate', 'error')
  } finally {
    statusLoading.value = false
  }
}

const startChat = async () => {
  try {
    const conversation = await conversationsStore.createConversation(candidateId.value)
    router.push(`/chat/${conversation.id}`)
  } catch (e) {
    console.error(e)
  }
}

const generateQuestions = async () => {
  isProcessing.value = true
  showQuestionsModal.value = true
  try {
    const res = await candidatesApi.interviewQuestions(candidateId.value, selectedJobId.value)
    interviewQuestions.value = res.data.questions
  } catch (e) {
    console.error(e)
    interviewQuestions.value = []
  } finally {
    isProcessing.value = false
  }
}

const askQuestion = async () => {
  if (!chatQuestion.value.trim()) return
  isProcessing.value = true
  try {
    const res = await candidatesApi.chat(candidateId.value, chatQuestion.value)
    chatAnswer.value = res.data.answer
  } catch (e) {
    console.error(e)
    chatAnswer.value = 'Sorry, I could not get an answer.'
  } finally {
    isProcessing.value = false
  }
}

onMounted(async () => {
  try {
    await Promise.all([
      candidatesStore.fetchCandidate(candidateId.value),
      jobsStore.fetchJobs()
    ])
    startPolling()
  } catch (e) {
    console.error(e)
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Toast Notification -->
    <div
      v-if="toast.show"
      class="fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transition-all duration-200"
      :class="toast.type === 'success' ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'"
    >
      {{ toast.message }}
    </div>

    <div class="flex items-center gap-4">
      <button @click="$router.back()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
        <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-2xl font-bold text-slate-900">Candidate Details</h1>
    </div>

    <div v-if="candidatesStore.loading && !candidatesStore.currentCandidate" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <template v-else-if="candidatesStore.currentCandidate">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
          <Card padding="md">
            <div class="flex items-start justify-between mb-6">
              <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center">
                  <span class="text-2xl font-bold text-indigo-600">
                    {{ candidatesStore.currentCandidate.name.charAt(0).toUpperCase() }}
                  </span>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-slate-900">{{ candidatesStore.currentCandidate.name }}</h2>
                  <p class="text-slate-500">{{ candidatesStore.currentCandidate.email || 'No email' }}</p>
                </div>
              </div>
              <Badge :variant="getStatusVariant(candidatesStore.currentCandidate.status)">
                <span v-if="candidatesStore.currentCandidate.status === 'processing'" class="flex items-center gap-1">
                  <Spinner size="sm" />
                  Processing
                </span>
                <span v-else>{{ candidatesStore.currentCandidate.status }}</span>
              </Badge>
            </div>

            <!-- AI Score Bar -->
            <div class="mb-6">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-slate-700">AI Score</span>
                <span class="text-sm font-bold" :class="getScoreColor(candidatesStore.currentCandidate.ai_score).replace('bg-', 'text-')">
                  {{ candidatesStore.currentCandidate.ai_score !== null ? candidatesStore.currentCandidate.ai_score : 'N/A' }}
                </span>
              </div>
              <div class="w-full h-3 bg-slate-200 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="getScoreColor(candidatesStore.currentCandidate.ai_score)"
                  :style="{ width: candidatesStore.currentCandidate.ai_score ? `${candidatesStore.currentCandidate.ai_score}%` : '0%' }"
                />
              </div>
            </div>

            <!-- AI Summary -->
            <div v-if="candidatesStore.currentCandidate.ai_summary" class="p-4 bg-slate-50 rounded-lg">
              <h3 class="text-sm font-semibold text-slate-700 mb-2">AI Summary</h3>
              <p class="text-slate-600">{{ candidatesStore.currentCandidate.ai_summary }}</p>
            </div>
          </Card>

          <!-- Extracted Data -->
          <Card v-if="extractedData" padding="md">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">AI Extracted Data</h3>

            <div v-if="skills.length > 0" class="mb-4">
              <h4 class="text-sm font-medium text-slate-700 mb-2">Skills</h4>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="skill in skills"
                  :key="skill"
                  class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm"
                >
                  {{ skill }}
                </span>
              </div>
            </div>

            <div v-if="yearsExperience !== 'N/A'" class="mb-4">
              <h4 class="text-sm font-medium text-slate-700 mb-1">Years of Experience</h4>
              <p class="text-slate-600">{{ yearsExperience }}</p>
            </div>

            <div v-if="education.length > 0" class="mb-4">
              <h4 class="text-sm font-medium text-slate-700 mb-2">Education</h4>
              <ul class="space-y-1">
                <li v-for="edu in education" :key="edu" class="text-slate-600">{{ edu }}</li>
              </ul>
            </div>

            <div v-if="languages.length > 0">
              <h4 class="text-sm font-medium text-slate-700 mb-2">Languages</h4>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="lang in languages"
                  :key="lang"
                  class="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-sm"
                >
                  {{ lang }}
                </span>
              </div>
            </div>
          </Card>
        </div>

        <!-- Sidebar Actions -->
        <div class="space-y-6">
          <Card padding="md">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Actions</h3>
            <div class="space-y-3">
              <!-- Shortlist/Reject buttons - show when not already shortlisted/rejected -->
              <template v-if="!isShortlistedOrRejected">
                <Button variant="success" class="w-full" @click="showShortlistModal = true">
                  Shortlist Candidate
                </Button>
                <Button variant="danger" class="w-full" @click="showRejectModal = true">
                  Reject Candidate
                </Button>
              </template>
              <!-- Restore button - show when shortlisted or rejected -->
              <template v-else>
                <Button variant="secondary" class="w-full" @click="handleRestore" :disabled="statusLoading">
                  Restore to Ready
                </Button>
              </template>
              <Button variant="secondary" class="w-full" @click="generateQuestions">
                Generate Interview Questions
              </Button>
              <Button variant="primary" class="w-full" @click="startChat">
                Chat with CV
              </Button>
            </div>
          </Card>

          <Card padding="md">
            <p class="text-sm text-slate-500">
              Added on {{ formatDate(candidatesStore.currentCandidate.created_at) }}
            </p>
          </Card>
        </div>
      </div>
    </template>

    <!-- Shortlist Confirmation Modal -->
    <Modal v-model="showShortlistModal" title="Shortlist Candidate">
      <p class="text-slate-600">
        Are you sure you want to shortlist <strong>{{ candidatesStore.currentCandidate?.name }}</strong>?
      </p>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showShortlistModal = false">Cancel</Button>
        <Button variant="success" :disabled="statusLoading" @click="handleShortlist">
          <Spinner v-if="statusLoading" size="sm" class="mr-2" />
          Shortlist
        </Button>
      </div>
    </Modal>

    <!-- Reject Confirmation Modal -->
    <Modal v-model="showRejectModal" title="Reject Candidate">
      <p class="text-slate-600">
        Are you sure you want to reject <strong>{{ candidatesStore.currentCandidate?.name }}</strong>? This cannot be undone.
      </p>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showRejectModal = false">Cancel</Button>
        <Button variant="danger" :disabled="statusLoading" @click="handleReject">
          <Spinner v-if="statusLoading" size="sm" class="mr-2" />
          Reject
        </Button>
      </div>
    </Modal>

    <!-- Interview Questions Modal -->
    <Modal v-model="showQuestionsModal" title="Interview Questions">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Job Position (optional)</label>
          <select
            v-model="selectedJobId"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
          >
            <option :value="null">General questions</option>
            <option v-for="job in jobsStore.jobs" :key="job.id" :value="job.id">
              {{ job.title }}
            </option>
          </select>
        </div>
        <div v-if="isProcessing" class="flex justify-center py-8">
          <Spinner size="lg" />
        </div>
        <div v-else-if="interviewQuestions.length > 0" class="space-y-2 max-h-80 overflow-y-auto">
          <div
            v-for="(question, index) in interviewQuestions"
            :key="index"
            class="p-3 bg-slate-50 rounded-lg text-sm text-slate-700"
          >
            {{ index + 1 }}. {{ question }}
          </div>
        </div>
        <p v-else class="text-slate-500 text-center py-4">Click "Generate" to create questions</p>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showQuestionsModal = false">Close</Button>
        <Button variant="primary" :disabled="isProcessing" @click="generateQuestions">
          Generate
        </Button>
      </div>
    </Modal>
  </div>
</template>
