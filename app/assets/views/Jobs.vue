<script setup>
import { ref, onMounted } from 'vue'
import { useJobsStore } from '../stores/jobs.js'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Badge from '../components/ui/Badge.vue'
import Spinner from '../components/ui/Spinner.vue'
import Modal from '../components/ui/Modal.vue'
import EmptyState from '../components/ui/EmptyState.vue'

const jobsStore = useJobsStore()

const showCreateModal = ref(false)
const showDeleteModal = ref(false)
const selectedJob = ref(null)
const isSubmitting = ref(false)
const newJob = ref({
  title: '',
  description: '',
  requirements: '',
})

const formatDate = (date) => {
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

const createJob = async () => {
  isSubmitting.value = true
  try {
    await jobsStore.createJob(newJob.value)
    showCreateModal.value = false
    newJob.value = { title: '', description: '', requirements: '' }
  } catch (e) {
    console.error(e)
  } finally {
    isSubmitting.value = false
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
  } catch (e) {
    console.error(e)
  }
}

onMounted(async () => {
  try {
    await jobsStore.fetchJobs()
  } catch (e) {
    console.error(e)
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Jobs</h1>
      <Button variant="primary" @click="showCreateModal = true">
        Create Job
      </Button>
    </div>

    <div v-if="jobsStore.loading && jobsStore.jobs.length === 0" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <div v-else-if="jobsStore.jobs.length === 0" class="flex items-center justify-center py-12">
      <EmptyState
        title="No jobs yet"
        description="Create your first job position to start receiving candidates"
        action-label="Create Job"
        @action="showCreateModal = true"
      />
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <Card
        v-for="job in jobsStore.jobs"
        :key="job.id"
        padding="md"
        class="hover:shadow-md transition-shadow cursor-pointer"
        @click="$router.push(`/jobs/${job.id}`)"
      >
        <div class="flex items-start justify-between mb-3">
          <h3 class="text-lg font-semibold text-slate-900">{{ job.title }}</h3>
          <Badge :variant="getStatusVariant(job.status)">{{ job.status }}</Badge>
        </div>
        <p class="text-sm text-slate-500 mb-4 line-clamp-2">
          {{ job.description || 'No description' }}
        </p>
        <div class="flex items-center justify-between">
          <span class="text-sm text-slate-500">
            {{ job.candidates }} candidate{{ job.candidates !== 1 ? 's' : '' }}
          </span>
          <span class="text-sm text-slate-400">{{ formatDate(job.created_at) }}</span>
        </div>
        <div class="flex gap-2 mt-4" @click.stop>
          <Button variant="ghost" size="sm" @click="confirmDelete(job)">
            Delete
          </Button>
        </div>
      </Card>
    </div>

    <!-- Create Job Modal -->
    <Modal v-model="showCreateModal" title="Create New Job">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Title</label>
          <input
            v-model="newJob.title"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="e.g. Senior Developer"
          >
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
          <textarea
            v-model="newJob.description"
            rows="4"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="Job description..."
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Requirements</label>
          <textarea
            v-model="newJob.requirements"
            rows="3"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="Key requirements..."
          />
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showCreateModal = false">Cancel</Button>
        <Button variant="primary" :disabled="!newJob.title || isSubmitting" @click="createJob">
          <Spinner v-if="isSubmitting" size="sm" class="mr-2" />
          Create Job
        </Button>
      </div>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal v-model="showDeleteModal" title="Delete Job">
      <p class="text-slate-600">
        Are you sure you want to delete <strong>{{ selectedJob?.title }}</strong>? This action cannot be undone.
      </p>
      <div class="flex justify-end gap-3 mt-6">
        <Button variant="ghost" @click="showDeleteModal = false">Cancel</Button>
        <Button variant="danger" @click="deleteJob">Delete</Button>
      </div>
    </Modal>
  </div>
</template>
