import { defineStore } from 'pinia'
import { ref } from 'vue'
import { jobsApi } from '../api/client.js'

export const useJobsStore = defineStore('jobs', () => {
  const jobs = ref([])
  const currentJob = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const upsertJob = (job) => {
    const index = jobs.value.findIndex(j => j.id === job.id)
    if (index !== -1) {
      jobs.value[index] = job
    } else {
      jobs.value.unshift(job)
    }
  }

  async function fetchJobs() {
    loading.value = true
    error.value = null
    try {
      const response = await jobsApi.list()
      jobs.value = response.data
    } catch (e) {
      error.value = e.message || 'Failed to fetch jobs'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchJob(id) {
    loading.value = true
    error.value = null
    try {
      const response = await jobsApi.get(id)
      currentJob.value = response.data
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to fetch job'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function createJob(data) {
    loading.value = true
    error.value = null
    try {
      const response = await jobsApi.create(data)
      upsertJob(response.data)
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to create job'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function updateJob(id, data) {
    loading.value = true
    error.value = null
    try {
      const response = await jobsApi.update(id, data)
      upsertJob(response.data)
      if (currentJob.value?.id === id) {
        currentJob.value = response.data
      }
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to update job'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function deleteJob(id) {
    loading.value = true
    error.value = null
    try {
      await jobsApi.delete(id)
      jobs.value = jobs.value.filter(j => j.id !== id)
    } catch (e) {
      error.value = e.message || 'Failed to delete job'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    jobs,
    currentJob,
    loading,
    error,
    fetchJobs,
    fetchJob,
    createJob,
    updateJob,
    deleteJob,
  }
})
