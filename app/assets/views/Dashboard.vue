<script setup>
import { ref, onMounted, computed } from 'vue'
import { useCandidatesStore } from '../stores/candidates.js'
import { useJobsStore } from '../stores/jobs.js'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Badge from '../components/ui/Badge.vue'
import Spinner from '../components/ui/Spinner.vue'

const candidatesStore = useCandidatesStore()
const jobsStore = useJobsStore()

const loading = ref(true)

const stats = computed(() => ({
  totalCandidates: candidatesStore.candidates.length,
  openJobs: jobsStore.jobs.filter(j => j.status === 'open').length,
  readyForReview: candidatesStore.candidates.filter(c => c.status === 'ready').length,
  shortlisted: candidatesStore.candidates.filter(c => c.status === 'shortlisted').length,
}))

const recentCandidates = computed(() => candidatesStore.candidates.slice(0, 5))
const recentJobs = computed(() => jobsStore.jobs.slice(0, 5))

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
    open: 'success',
    closed: 'danger',
    draft: 'default',
  }
  return variants[status] || 'default'
}

onMounted(async () => {
  try {
    await Promise.all([
      candidatesStore.fetchCandidates(),
      jobsStore.fetchJobs(),
    ])
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
      <div class="flex gap-3">
        <router-link to="/candidates">
          <Button variant="primary">Upload CV</Button>
        </router-link>
        <router-link to="/jobs">
          <Button variant="secondary">Create Job</Button>
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner size="lg" />
    </div>

    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-slate-500">Total Candidates</p>
              <p class="text-2xl font-bold text-slate-900">{{ stats.totalCandidates }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-slate-500">Open Jobs</p>
              <p class="text-2xl font-bold text-slate-900">{{ stats.openJobs }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center">
              <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-slate-500">Ready for Review</p>
              <p class="text-2xl font-bold text-slate-900">{{ stats.readyForReview }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center">
              <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </Card>

        <Card>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-slate-500">Shortlisted</p>
              <p class="text-2xl font-bold text-slate-900">{{ stats.shortlisted }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center">
              <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
          </div>
        </Card>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card padding="md">
          <h3 class="text-lg font-semibold text-slate-900 mb-4">Recent Candidates</h3>
          <div v-if="recentCandidates.length === 0" class="text-center py-8 text-slate-500">
            No candidates yet
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="candidate in recentCandidates"
              :key="candidate.id"
              class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
              @click="$router.push(`/candidates/${candidate.id}`)"
            >
              <div>
                <p class="font-medium text-slate-900">{{ candidate.name }}</p>
                <p class="text-sm text-slate-500">{{ candidate.email || 'No email' }}</p>
              </div>
              <div class="flex items-center gap-3">
                <Badge :variant="getStatusVariant(candidate.status)">
                  {{ candidate.status }}
                </Badge>
                <span class="text-sm text-slate-400">{{ formatDate(candidate.created_at) }}</span>
              </div>
            </div>
          </div>
        </Card>

        <Card padding="md">
          <h3 class="text-lg font-semibold text-slate-900 mb-4">Recent Jobs</h3>
          <div v-if="recentJobs.length === 0" class="text-center py-8 text-slate-500">
            No jobs yet
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="job in recentJobs"
              :key="job.id"
              class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
              @click="$router.push(`/jobs/${job.id}`)"
            >
              <div>
                <p class="font-medium text-slate-900">{{ job.title }}</p>
                <p class="text-sm text-slate-500">{{ job.candidates }} candidates</p>
              </div>
              <div class="flex items-center gap-3">
                <Badge :variant="getStatusVariant(job.status)">
                  {{ job.status }}
                </Badge>
                <span class="text-sm text-slate-400">{{ formatDate(job.created_at) }}</span>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </template>
  </div>
</template>
