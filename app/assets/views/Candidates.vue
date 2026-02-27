<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useCandidatesStore } from '../stores/candidates.js'
import { useJobsStore } from '../stores/jobs.js'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import Card from '../components/ui/Card.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Modal from '../components/ui/Modal.vue'
import Spinner from '../components/ui/Spinner.vue'

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

const searchQuery = ref('')
const statusFilter = ref('all')
const sortBy = ref('newest')

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

const pipelineStats = computed(() => ({
  total: candidatesStore.candidates.length,
  processing: candidatesStore.candidates.filter(candidate => candidate.status === 'processing').length,
  ready: candidatesStore.candidates.filter(candidate => candidate.status === 'ready').length,
  shortlisted: candidatesStore.candidates.filter(candidate => candidate.status === 'shortlisted').length,
}))

const availableJobs = computed(() => jobsStore.jobs.filter(job => job.status !== 'closed'))

const filteredCandidates = computed(() => {
  const normalizedSearch = searchQuery.value.trim().toLowerCase()

  const filtered = candidatesStore.candidates.filter((candidate) => {
    const matchesSearch = !normalizedSearch ||
      candidate.name?.toLowerCase().includes(normalizedSearch) ||
      candidate.email?.toLowerCase().includes(normalizedSearch)

    const matchesStatus = statusFilter.value === 'all' || candidate.status === statusFilter.value

    return matchesSearch && matchesStatus
  })

  return filtered.sort((a, b) => {
    if (sortBy.value === 'newest') return new Date(b.created_at) - new Date(a.created_at)
    if (sortBy.value === 'oldest') return new Date(a.created_at) - new Date(b.created_at)
    if (sortBy.value === 'name') return (a.name || '').localeCompare(b.name || '')
    if (sortBy.value === 'score_desc') return (b.ai_score ?? -1) - (a.ai_score ?? -1)
    if (sortBy.value === 'score_asc') return (a.ai_score ?? 101) - (b.ai_score ?? 101)
    return 0
  })
})

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
  pollInterval.value = setInterval(async () => {
    await candidatesStore.fetchCandidates()
    const hasProcessing = candidatesStore.candidates.some(candidate => candidate.status === 'processing')
    if (!hasProcessing) {
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
    showUploadModal.value = false
    uploadFile.value = null
    startPolling()
  } catch (error) {
    console.error(error)
  } finally {
    isUploading.value = false
  }
}

const openScoreModal = (candidate) => {
  selectedCandidate.value = candidate
  selectedJobForScore.value = candidate.job_position?.id || null
  showScoreModal.value = true
}

const submitScore = async () => {
  if (!selectedCandidate.value || !selectedJobForScore.value) return

  isScoring.value = true
  try {
    await candidatesStore.scoreCandidate(selectedCandidate.value.id, selectedJobForScore.value)
    await candidatesStore.fetchCandidate(selectedCandidate.value.id)
    showScoreModal.value = false
  } catch (error) {
    console.error(error)
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
  } catch (error) {
    console.error(error)
  }
}

const quickStatusLabel = (status) => {
  if (status === 'ready') return 'Shortlist'
  if (status === 'shortlisted' || status === 'rejected') return 'Restore'
  if (status === 'new') return 'Mark Ready'
  return null
}

const updateQuickStatus = async (candidate) => {
  let nextStatus = null
  if (candidate.status === 'ready') nextStatus = 'shortlisted'
  if (candidate.status === 'shortlisted' || candidate.status === 'rejected') nextStatus = 'ready'
  if (candidate.status === 'new') nextStatus = 'ready'
  if (!nextStatus) return

  try {
    await candidatesStore.updateCandidateStatus(candidate.id, nextStatus)
  } catch (error) {
    console.error(error)
  }
}

onMounted(async () => {
  try {
    await Promise.all([candidatesStore.fetchCandidates(), jobsStore.fetchJobs()])
    if (candidatesStore.candidates.some(candidate => candidate.status === 'processing')) {
      startPolling()
    }
  } catch (error) {
    console.error(error)
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="space-y-5">
    <section class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Candidate Pipeline</h1>
        <p class="text-sm text-slate-500">Track extraction, scoring, and hiring decisions from one table.</p>
      </div>
      <Button variant="primary" @click="showUploadModal = true">Upload CV</Button>
    </section>

    <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <Card class="text-center">
        <p class="text-xs uppercase tracking-wide text-slate-500">Total</p>
        <p class="text-2xl font-semibold text-slate-900 mt-1">{{ pipelineStats.total }}</p>
      </Card>
      <Card class="text-center">
        <p class="text-xs uppercase tracking-wide text-amber-700">Processing</p>
        <p class="text-2xl font-semibold text-amber-900 mt-1">{{ pipelineStats.processing }}</p>
      </Card>
      <Card class="text-center">
        <p class="text-xs uppercase tracking-wide text-cyan-700">Ready</p>
        <p class="text-2xl font-semibold text-cyan-900 mt-1">{{ pipelineStats.ready }}</p>
      </Card>
      <Card class="text-center">
        <p class="text-xs uppercase tracking-wide text-emerald-700">Shortlisted</p>
        <p class="text-2xl font-semibold text-emerald-900 mt-1">{{ pipelineStats.shortlisted }}</p>
      </Card>
    </section>

    <Card>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-medium uppercase tracking-wide text-slate-500 mb-1">Search</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Candidate name or email"
            class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
          >
        </div>
        <div>
          <label class="block text-xs font-medium uppercase tracking-wide text-slate-500 mb-1">Status</label>
          <select
            v-model="statusFilter"
            class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
          >
            <option value="all">All statuses</option>
            <option value="new">New</option>
            <option value="processing">Processing</option>
            <option value="ready">Ready</option>
            <option value="shortlisted">Shortlisted</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium uppercase tracking-wide text-slate-500 mb-1">Sort</label>
          <select
            v-model="sortBy"
            class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
          >
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="score_desc">Highest score</option>
            <option value="score_asc">Lowest score</option>
            <option value="name">Name A-Z</option>
          </select>
        </div>
      </div>
    </Card>

    <div v-if="candidatesStore.loading && candidatesStore.candidates.length === 0" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <Card v-else-if="candidatesStore.candidates.length === 0" padding="none">
      <EmptyState
        title="No candidates yet"
        description="Upload your first candidate CV to start screening."
        action-label="Upload CV"
        @action="showUploadModal = true"
      />
    </Card>

    <Card v-else-if="filteredCandidates.length === 0">
      <p class="text-sm text-slate-500 text-center py-10">No candidates match your current filters.</p>
    </Card>

    <Card v-else padding="none">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="px-5 py-3 text-left text-xs uppercase tracking-wide text-slate-500">Candidate</th>
              <th class="px-5 py-3 text-left text-xs uppercase tracking-wide text-slate-500">Status</th>
              <th class="px-5 py-3 text-left text-xs uppercase tracking-wide text-slate-500">Linked Job</th>
              <th class="px-5 py-3 text-left text-xs uppercase tracking-wide text-slate-500">AI Score</th>
              <th class="px-5 py-3 text-left text-xs uppercase tracking-wide text-slate-500">Added</th>
              <th class="px-5 py-3 text-right text-xs uppercase tracking-wide text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr
              v-for="candidate in filteredCandidates"
              :key="candidate.id"
              class="hover:bg-slate-50 transition-colors cursor-pointer"
              @click="$router.push(`/candidates/${candidate.id}`)"
            >
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-cyan-100 flex items-center justify-center text-cyan-700 font-semibold">
                    {{ candidate.status === 'processing' ? '…' : (candidate.name || '?').charAt(0).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <p class="font-medium text-slate-900 truncate">{{ candidate.name || 'Unknown' }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ candidate.email || 'No email' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-4">
                <Badge :variant="getStatusVariant(candidate.status)">
                  <span v-if="candidate.status === 'processing'" class="flex items-center gap-1">
                    <Spinner size="sm" />
                    Processing
                  </span>
                  <span v-else>{{ candidate.status }}</span>
                </Badge>
              </td>
              <td class="px-5 py-4 text-sm text-slate-600">
                {{ candidate.job_position?.title || '-' }}
              </td>
              <td class="px-5 py-4">
                <span :class="getScoreColor(candidate.ai_score)" class="font-semibold">
                  {{ candidate.ai_score ?? '-' }}
                </span>
              </td>
              <td class="px-5 py-4 text-sm text-slate-500">{{ formatDate(candidate.created_at) }}</td>
              <td class="px-5 py-4 text-right">
                <div class="inline-flex items-center gap-2" @click.stop>
                  <button
                    class="px-2.5 py-1.5 rounded-lg text-xs border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40"
                    :disabled="availableJobs.length === 0 || candidate.status === 'processing'"
                    @click="openScoreModal(candidate)"
                  >
                    Score
                  </button>
                  <button
                    v-if="quickStatusLabel(candidate.status)"
                    class="px-2.5 py-1.5 rounded-lg text-xs border border-cyan-300 text-cyan-700 hover:bg-cyan-50 disabled:opacity-40"
                    :disabled="candidate.status === 'processing'"
                    @click="updateQuickStatus(candidate)"
                  >
                    {{ quickStatusLabel(candidate.status) }}
                  </button>
                  <button
                    class="px-2.5 py-1.5 rounded-lg text-xs border border-rose-300 text-rose-600 hover:bg-rose-50"
                    @click="confirmDelete(candidate)"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <Modal v-model="showUploadModal" title="Upload Candidate CV">
      <div class="space-y-4">
        <p class="text-sm text-slate-600">We automatically extract profile data from PDF CV files.</p>
        <div
          class="border-2 border-dashed rounded-xl p-8 text-center transition-colors"
          :class="dragOver ? 'border-cyan-500 bg-cyan-50' : 'border-slate-300'"
          @drop="handleDrop"
          @dragover="handleDragOver"
          @dragleave="handleDragLeave"
        >
          <input
            id="file-upload"
            type="file"
            accept=".pdf"
            class="hidden"
            @change="handleFileSelect"
          >
          <label for="file-upload" class="cursor-pointer block">
            <p class="text-sm text-slate-600">
              <span class="font-medium text-cyan-700">Click to upload</span> or drag and drop
            </p>
            <p class="text-xs text-slate-500 mt-1">PDF only</p>
          </label>
          <div v-if="uploadFile" class="mt-4 p-3 bg-slate-100 rounded-lg text-left">
            <p class="text-sm font-medium text-slate-900 truncate">{{ uploadFile.name }}</p>
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

    <Modal v-model="showDeleteModal" title="Delete Candidate">
      <p class="text-slate-600">
        Delete <strong>{{ selectedCandidate?.name }}</strong>? This action cannot be undone.
      </p>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
        <Button variant="danger" @click="deleteCandidate">Delete</Button>
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
            <option v-for="job in availableJobs" :key="job.id" :value="job.id">
              {{ job.title }}
            </option>
          </select>
        </div>
        <div v-if="selectedCandidate?.ai_score !== null" class="p-4 bg-slate-100 rounded-xl">
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-600">Current score</span>
            <span :class="getScoreColor(selectedCandidate.ai_score)" class="text-lg font-semibold">
              {{ selectedCandidate.ai_score }}
            </span>
          </div>
          <p v-if="selectedCandidate?.job_position?.title" class="text-xs text-slate-500 mt-2">
            Last scored for: {{ selectedCandidate.job_position.title }}
          </p>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showScoreModal = false">Cancel</Button>
        <Button variant="primary" :disabled="!selectedJobForScore || isScoring" @click="submitScore">
          <Spinner v-if="isScoring" size="sm" class="mr-2" />
          Calculate
        </Button>
      </div>
    </Modal>
  </div>
</template>
