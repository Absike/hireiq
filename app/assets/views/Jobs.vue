<script setup>
import { computed, onMounted, ref } from 'vue'
import { useJobsStore } from '../stores/jobs.js'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import Card from '../components/ui/Card.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Modal from '../components/ui/Modal.vue'
import Spinner from '../components/ui/Spinner.vue'

const jobsStore = useJobsStore()

const showCreateModal = ref(false)
const showDeleteModal = ref(false)
const selectedJob = ref(null)
const isSubmitting = ref(false)
const statusUpdatingJobId = ref(null)

const searchQuery = ref('')
const statusFilter = ref('all')
const sortBy = ref('newest')

const newJob = ref({
  title: '',
  description: '',
  requirements: '',
})

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

const filteredJobs = computed(() => {
  const normalizedSearch = searchQuery.value.trim().toLowerCase()

  const filtered = jobsStore.jobs.filter((job) => {
    const matchesSearch = !normalizedSearch ||
      job.title?.toLowerCase().includes(normalizedSearch) ||
      job.description?.toLowerCase().includes(normalizedSearch)
    const matchesStatus = statusFilter.value === 'all' || job.status === statusFilter.value
    return matchesSearch && matchesStatus
  })

  return filtered.sort((a, b) => {
    if (sortBy.value === 'newest') return new Date(b.created_at) - new Date(a.created_at)
    if (sortBy.value === 'oldest') return new Date(a.created_at) - new Date(b.created_at)
    if (sortBy.value === 'title') return (a.title || '').localeCompare(b.title || '')
    if (sortBy.value === 'candidates') return (b.candidates || 0) - (a.candidates || 0)
    return 0
  })
})

const stats = computed(() => ({
  total: jobsStore.jobs.length,
  open: jobsStore.jobs.filter(job => job.status === 'open').length,
  draft: jobsStore.jobs.filter(job => job.status === 'draft').length,
  closed: jobsStore.jobs.filter(job => job.status === 'closed').length,
}))

const createJob = async () => {
  isSubmitting.value = true
  try {
    await jobsStore.createJob(newJob.value)
    showCreateModal.value = false
    newJob.value = { title: '', description: '', requirements: '' }
  } catch (error) {
    console.error(error)
  } finally {
    isSubmitting.value = false
  }
}

const updateJobStatus = async (job, status) => {
  if (job.status === status) return
  statusUpdatingJobId.value = job.id
  try {
    await jobsStore.updateJob(job.id, { status })
  } catch (error) {
    console.error(error)
  } finally {
    statusUpdatingJobId.value = null
  }
}

const confirmDelete = (job) => {
  selectedJob.value = job
  showDeleteModal.value = true
}

const deleteJob = async () => {
  if (!selectedJob.value) return
  try {
    await jobsStore.deleteJob(selectedJob.value.id)
    showDeleteModal.value = false
    selectedJob.value = null
  } catch (error) {
    console.error(error)
  }
}

onMounted(async () => {
  try {
    await jobsStore.fetchJobs()
  } catch (error) {
    console.error(error)
  }
})
</script>

<template>
  <div class="space-y-5">
    <section class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Job Openings</h1>
        <p class="text-sm text-slate-500">Define role quality and keep each job aligned with hiring demand.</p>
      </div>
      <Button variant="primary" @click="showCreateModal = true">Create Job</Button>
    </section>

    <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <Card class="text-center">
        <p class="text-xs uppercase tracking-wide text-slate-500">Total</p>
        <p class="text-2xl font-semibold text-slate-900 mt-1">{{ stats.total }}</p>
      </Card>
      <Card class="text-center">
        <p class="text-xs uppercase tracking-wide text-emerald-700">Open</p>
        <p class="text-2xl font-semibold text-emerald-900 mt-1">{{ stats.open }}</p>
      </Card>
      <Card class="text-center">
        <p class="text-xs uppercase tracking-wide text-slate-600">Draft</p>
        <p class="text-2xl font-semibold text-slate-900 mt-1">{{ stats.draft }}</p>
      </Card>
      <Card class="text-center">
        <p class="text-xs uppercase tracking-wide text-rose-700">Closed</p>
        <p class="text-2xl font-semibold text-rose-900 mt-1">{{ stats.closed }}</p>
      </Card>
    </section>

    <Card>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-medium uppercase tracking-wide text-slate-500 mb-1">Search</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Role title or description"
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
            <option value="open">Open</option>
            <option value="draft">Draft</option>
            <option value="closed">Closed</option>
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
            <option value="candidates">Most candidates</option>
            <option value="title">Title A-Z</option>
          </select>
        </div>
      </div>
    </Card>

    <div v-if="jobsStore.loading && jobsStore.jobs.length === 0" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <div v-else-if="jobsStore.jobs.length === 0" class="flex items-center justify-center py-12">
      <EmptyState
        title="No jobs yet"
        description="Create your first role to start matching candidates."
        action-label="Create Job"
        @action="showCreateModal = true"
      />
    </div>

    <Card v-else-if="filteredJobs.length === 0">
      <p class="text-sm text-slate-500 text-center py-10">No jobs match your current filters.</p>
    </Card>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card
        v-for="job in filteredJobs"
        :key="job.id"
        class="hover:shadow-md transition-shadow cursor-pointer"
        @click="$router.push(`/jobs/${job.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="min-w-0">
            <h3 class="text-lg font-semibold text-slate-900 truncate">{{ job.title }}</h3>
            <p class="text-xs text-slate-500 mt-1">Created {{ formatDate(job.created_at) }}</p>
          </div>
          <Badge :variant="getStatusVariant(job.status)">{{ job.status }}</Badge>
        </div>

        <p class="text-sm text-slate-600 line-clamp-3">{{ job.description || 'No description' }}</p>

        <div class="mt-4 pt-4 border-t border-slate-200 flex items-center justify-between">
          <span class="text-sm text-slate-600">{{ job.candidates || 0 }} candidate{{ (job.candidates || 0) === 1 ? '' : 's' }}</span>
          <div class="inline-flex rounded-lg border border-slate-300 overflow-hidden" @click.stop>
            <button
              class="px-2.5 py-1.5 text-xs"
              :class="job.status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'hover:bg-slate-100 text-slate-600'"
              :disabled="statusUpdatingJobId === job.id"
              @click="updateJobStatus(job, 'open')"
            >
              Open
            </button>
            <button
              class="px-2.5 py-1.5 text-xs border-l border-slate-300"
              :class="job.status === 'draft' ? 'bg-slate-200 text-slate-700' : 'hover:bg-slate-100 text-slate-600'"
              :disabled="statusUpdatingJobId === job.id"
              @click="updateJobStatus(job, 'draft')"
            >
              Draft
            </button>
            <button
              class="px-2.5 py-1.5 text-xs border-l border-slate-300"
              :class="job.status === 'closed' ? 'bg-rose-100 text-rose-700' : 'hover:bg-slate-100 text-slate-600'"
              :disabled="statusUpdatingJobId === job.id"
              @click="updateJobStatus(job, 'closed')"
            >
              Closed
            </button>
          </div>
        </div>

        <div class="mt-3 flex justify-end" @click.stop>
          <button
            class="px-2.5 py-1.5 text-xs rounded-lg border border-rose-300 text-rose-600 hover:bg-rose-50"
            @click="confirmDelete(job)"
          >
            Delete
          </button>
        </div>
      </Card>
    </div>

    <Modal v-model="showCreateModal" title="Create New Job">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Title</label>
          <input
            v-model="newJob.title"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
            placeholder="e.g. Senior Backend Engineer"
          >
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
          <textarea
            v-model="newJob.description"
            rows="4"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
            placeholder="Main responsibilities, team context, expected impact..."
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Requirements</label>
          <textarea
            v-model="newJob.requirements"
            rows="3"
            class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
            placeholder="Skills, years of experience, and must-have capabilities..."
          />
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showCreateModal = false">Cancel</Button>
        <Button variant="primary" :disabled="!newJob.title || !newJob.description || isSubmitting" @click="createJob">
          <Spinner v-if="isSubmitting" size="sm" class="mr-2" />
          Create Job
        </Button>
      </div>
    </Modal>

    <Modal v-model="showDeleteModal" title="Delete Job">
      <p class="text-slate-600">
        Delete <strong>{{ selectedJob?.title }}</strong>? This action cannot be undone.
      </p>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
        <Button variant="danger" @click="deleteJob">Delete</Button>
      </div>
    </Modal>
  </div>
</template>
