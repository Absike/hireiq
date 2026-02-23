<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useCandidatesStore } from '../stores/candidates.js'
import { useJobsStore } from '../stores/jobs.js'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Badge from '../components/ui/Badge.vue'
import Spinner from '../components/ui/Spinner.vue'
import Modal from '../components/ui/Modal.vue'
import EmptyState from '../components/ui/EmptyState.vue'

const candidatesStore = useCandidatesStore()
const jobsStore = useJobsStore()

const showUploadModal = ref(false)
const showDeleteModal = ref(false)
const showScoreModal = ref(false)
const selectedCandidate = ref(null)
const selectedJobForScore = ref(null)
const isUploading = ref(false)
const isScoring = ref(false)
const dragOver = ref(false)
const uploadFile = ref(null)
const pollInterval = ref(null)

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
  if (score === null) return 'text-slate-400'
  if (score <= 40) return 'text-rose-500'
  if (score <= 70) return 'text-amber-500'
  return 'text-emerald-500'
}

const handleFileSelect = (event) => {
  const target = event.target
  if (target.files && target.files[0]) {
    uploadFile.value = target.files[0]
  }
}

const handleDrop = (event) => {
  event.preventDefault()
  dragOver.value = false
  if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
    uploadFile.value = event.dataTransfer.files[0]
  }
}

const handleDragOver = (event) => {
  event.preventDefault()
  dragOver.value = true
}

const handleDragLeave = () => {
  dragOver.value = false
}

const startPolling = () => {
  if (pollInterval.value) return
  console.log('Starting polling...')
  pollInterval.value = setInterval(async () => {
    console.log('Polling for updates...')
    await candidatesStore.fetchCandidates()
    const hasProcessing = candidatesStore.candidates.some(c => c.status === 'processing')
    console.log('Processing status:', candidatesStore.candidates.map(c => ({ id: c.id, name: c.name, status: c.status, email: c.email })))
    if (!hasProcessing) {
      console.log('All processed, stopping polling')
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

const submitUpload = async () => {
  if (!uploadFile.value) return

  isUploading.value = true
  try {
    const formData = new FormData()
    formData.append('cv', uploadFile.value)
    await candidatesStore.uploadCandidate(formData)
    // Start polling to track processing status
    startPolling()
    showUploadModal.value = false
    uploadFile.value = null
  } catch (e) {
    console.error(e)
  } finally {
    isUploading.value = false
  }
}

const openScoreModal = (candidate) => {
  selectedCandidate.value = candidate
  selectedJobForScore.value = null
  showScoreModal.value = true
}

const submitScore = async () => {
  if (!selectedCandidate.value || !selectedJobForScore.value) return

  isScoring.value = true
  try {
    await candidatesStore.scoreCandidate(selectedCandidate.value.id, selectedJobForScore.value)
    await candidatesStore.fetchCandidate(selectedCandidate.value.id)
    showScoreModal.value = false
  } catch (e) {
    console.error(e)
  } finally {
    isScoring.value = false
  }
}

const confirmDelete = (candidate) => {
  selectedCandidate.value = candidate
  showDeleteModal.value = true
}

const deleteCandidate = async () => {
  if (!selectedCandidate.value) return

  try {
    await candidatesStore.deleteCandidate(selectedCandidate.value.id)
    showDeleteModal.value = false
    selectedCandidate.value = null
  } catch (e) {
    console.error(e)
  }
}

onMounted(async () => {
  try {
    await Promise.all([
      candidatesStore.fetchCandidates(),
      jobsStore.fetchJobs()
    ])
    if (candidatesStore.candidates.some(c => c.status === 'processing')) {
      startPolling()
    }
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
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Candidates</h1>
      <Button variant="primary" @click="showUploadModal = true">
        Upload CV
      </Button>
    </div>

    <div v-if="candidatesStore.loading && candidatesStore.candidates.length === 0" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <Card v-else-if="candidatesStore.candidates.length === 0" padding="none">
      <EmptyState
        title="No candidates yet"
        description="Upload your first candidate CV to get started"
        action-label="Upload CV"
        @action="showUploadModal = true"
      />
    </Card>

    <Card v-else padding="none">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">AI Score</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr
              v-for="candidate in candidatesStore.candidates"
              :key="candidate.id"
              class="hover:bg-slate-50 transition-colors cursor-pointer"
              @click="$router.push(`/candidates/${candidate.id}`)"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                    <span class="text-sm font-medium text-indigo-600">
                      {{ candidate.status === 'processing' ? '⏳' : (candidate.name || '?').charAt(0).toUpperCase() }}
                    </span>
                  </div>
                  <span class="font-medium text-slate-900">
                    {{ candidate.status === 'processing' ? 'Processing...' : (candidate.name || 'Unknown') }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ candidate.email || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <Badge :variant="getStatusVariant(candidate.status)">
                  <span v-if="candidate.status === 'processing'" class="flex items-center gap-1">
                    <Spinner size="sm" />
                    Processing
                  </span>
                  <span v-else>{{ candidate.status }}</span>
                </Badge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getScoreColor(candidate.ai_score)">
                  {{ candidate.ai_score !== null ? candidate.ai_score : '-' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ formatDate(candidate.created_at) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <button
                  class="text-indigo-600 hover:text-indigo-800 mr-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  title="Score Candidate"
                  :disabled="jobsStore.jobs.length === 0 || candidate.status === 'processing'"
                  @click.stop="openScoreModal(candidate)"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                </button>
                <button
                  class="text-rose-500 hover:text-rose-700 transition-colors"
                  title="Delete"
                  @click.stop="confirmDelete(candidate)"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Upload Modal -->
    <Modal v-model="showUploadModal" title="Upload Candidate CV">
      <div class="space-y-4">
        <p class="text-sm text-slate-600 mb-4">
          Name and email will be automatically extracted from the CV using AI.
        </p>
        <div
          class="border-2 border-dashed rounded-lg p-8 text-center transition-colors"
          :class="dragOver ? 'border-indigo-500 bg-indigo-50' : 'border-slate-300'"
          @drop="handleDrop"
          @dragover="handleDragOver"
          @dragleave="handleDragLeave"
        >
          <input
            type="file"
            accept=".pdf"
            class="hidden"
            id="file-upload"
            @change="handleFileSelect"
          >
          <label for="file-upload" class="cursor-pointer">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <p class="mt-2 text-sm text-slate-600">
              <span class="font-medium text-indigo-600">Click to upload</span> or drag and drop
            </p>
            <p class="mt-1 text-xs text-slate-500">PDF only</p>
          </label>
          <div v-if="uploadFile" class="mt-4 p-3 bg-slate-50 rounded-lg">
            <p class="text-sm font-medium text-slate-900">{{ uploadFile.name }}</p>
            <p class="text-xs text-slate-500">{{ (uploadFile.size / 1024).toFixed(1) }} KB</p>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showUploadModal = false">Cancel</Button>
        <Button variant="primary" :disabled="!uploadFile || isUploading" @click="submitUpload">
          <Spinner v-if="isUploading" size="sm" class="mr-2" />
          Upload
        </Button>
      </div>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal v-model="showDeleteModal" title="Delete Candidate">
      <p class="text-slate-600">
        Are you sure you want to delete <strong>{{ selectedCandidate?.name }}</strong>? This action cannot be undone.
      </p>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
        <Button variant="danger" @click="deleteCandidate">Delete</Button>
      </div>
    </Modal>

    <!-- Score Modal -->
    <Modal v-model="showScoreModal" title="Calculate AI Score">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Select Job Position</label>
          <select
            v-model="selectedJobForScore"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          >
            <option :value="null" disabled>Choose a job...</option>
            <option v-for="job in jobsStore.jobs" :key="job.id" :value="job.id">
              {{ job.title }}
            </option>
          </select>
        </div>
        <div v-if="selectedCandidate?.ai_score !== null" class="p-4 bg-slate-50 rounded-lg">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-slate-700">Current Score</span>
            <span :class="getScoreColor(selectedCandidate.ai_score)" class="text-lg font-bold">
              {{ selectedCandidate.ai_score }}
            </span>
          </div>
          <p v-if="selectedCandidate.ai_summary" class="text-sm text-slate-600">{{ selectedCandidate.ai_summary }}</p>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showScoreModal = false">Cancel</Button>
        <Button variant="primary" :disabled="!selectedJobForScore || isScoring" @click="submitScore">
          <Spinner v-if="isScoring" size="sm" class="mr-2" />
          Calculate Score
        </Button>
      </div>
    </Modal>
  </div>
</template>
