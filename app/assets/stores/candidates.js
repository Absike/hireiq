import { defineStore } from 'pinia'
import { ref } from 'vue'
import { candidatesApi } from '../api/client.js'

export const useCandidatesStore = defineStore('candidates', () => {
  const candidates = ref([])
  const currentCandidate = ref(null)
  const loading = ref(false)
  const error = ref(null)

  async function fetchCandidates() {
    loading.value = true
    error.value = null
    try {
      const response = await candidatesApi.list()
      // Replace all candidates with fresh data
      candidates.value = response.data
    } catch (e) {
      error.value = e.message || 'Failed to fetch candidates'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchCandidate(id) {
    loading.value = true
    error.value = null
    try {
      const response = await candidatesApi.get(id)
      currentCandidate.value = response.data
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to fetch candidate'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function uploadCandidate(formData) {
    loading.value = true
    error.value = null
    try {
      const response = await candidatesApi.upload(formData)
      candidates.value.unshift(response.data)
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to upload candidate'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function deleteCandidate(id) {
    loading.value = true
    error.value = null
    try {
      await candidatesApi.delete(id)
      candidates.value = candidates.value.filter(c => c.id !== id)
    } catch (e) {
      error.value = e.message || 'Failed to delete candidate'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function scoreCandidate(id, jobPositionId) {
    try {
      const response = await candidatesApi.score(id, jobPositionId)
      const index = candidates.value.findIndex(c => c.id === id)
      if (index !== -1) {
        candidates.value[index].ai_score = response.data.score
        candidates.value[index].ai_summary = response.data.summary
      }
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to score candidate'
      throw e
    }
  }

  async function summarizeCandidate(id) {
    try {
      const response = await candidatesApi.summarize(id)
      const index = candidates.value.findIndex(c => c.id === id)
      if (index !== -1) {
        candidates.value[index].ai_summary = response.data.summary
      }
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to summarize candidate'
      throw e
    }
  }

  return {
    candidates,
    currentCandidate,
    loading,
    error,
    fetchCandidates,
    fetchCandidate,
    uploadCandidate,
    deleteCandidate,
    scoreCandidate,
    summarizeCandidate,
  }
})
