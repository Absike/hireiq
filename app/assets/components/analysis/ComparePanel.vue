<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCandidatesStore } from '../../stores/candidates.js'
import { analysisApi } from '../../api/client.js'
import Button from '../ui/Button.vue'
import Spinner from '../ui/Spinner.vue'
import Badge from '../ui/Badge.vue'

const router = useRouter()
const candidatesStore = useCandidatesStore()

const selectedCandidateIds = ref([])
const comparisonData = ref(null)
const isLoading = ref(false)
const error = ref(null)

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

// Filter candidates - any status is fine for comparison
const availableCandidates = computed(() => {
  return candidatesStore.candidates
})

const toggleCandidate = (id) => {
  const index = selectedCandidateIds.value.indexOf(id)
  if (index === -1) {
    // Max 3 candidates
    if (selectedCandidateIds.value.length < 3) {
      selectedCandidateIds.value.push(id)
    }
  } else {
    selectedCandidateIds.value.splice(index, 1)
  }
}

const runComparison = async () => {
  if (selectedCandidateIds.value.length < 2) return
  isLoading.value = true
  error.value = null
  comparisonData.value = null

  try {
    const response = await analysisApi.compare(selectedCandidateIds.value)
    comparisonData.value = response.data
  } catch (e) {
    console.error(e)
    error.value = 'Failed to compare candidates'
  } finally {
    isLoading.value = false
  }
}

const viewProfile = (id) => {
  router.push(`/candidates/${id}`)
}

// Helper to get candidate by ID from the candidates list
const getCandidateById = (id) => {
  return candidatesStore.candidates.find(c => c.id === id)
}

// Helper to extract field from extracted data
const getFieldValue = (candidateId, field) => {
  const candidate = getCandidateById(candidateId)
  if (!candidate?.ai_extracted_data) return '—'
  return candidate.ai_extracted_data[field] || '—'
}

// Determine best value for highlighting
const getBestValue = (field, candidates) => {
  if (field === 'ai_score') {
    const scores = candidates.map(c => c.ai_score || 0)
    return Math.max(...scores)
  }
  if (field === 'years_experience') {
    const exps = candidates.map(c => {
      const val = getFieldValue(c.id, 'years_experience')
      return typeof val === 'number' ? val : parseInt(val) || 0
    })
    return Math.max(...exps)
  }
  return null
}

const isBestValue = (candidateId, field, value) => {
  if (!comparisonData.value?.candidates) return false
  const best = getBestValue(field, comparisonData.value.candidates)
  if (best === null) return false

  if (field === 'ai_score') {
    const candidate = getCandidateById(candidateId)
    return candidate?.ai_score === best
  }
  if (field === 'years_experience') {
    const val = typeof value === 'number' ? value : parseInt(value) || 0
    return val === best
  }
  return false
}

const formatSkills = (candidateId) => {
  const skills = getFieldValue(candidateId, 'skills')
  if (skills === '—' || !skills) return '—'
  return Array.isArray(skills) ? skills : [skills]
}
</script>

<template>
  <div class="space-y-6">
    <!-- Step 1: Select Candidates -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">Select 2-3 Candidates to Compare</h3>

      <div v-if="availableCandidates.length === 0" class="text-slate-500 text-sm py-4">
        No candidates available.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <label
          v-for="candidate in availableCandidates"
          :key="candidate.id"
          class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-200"
          :class="selectedCandidateIds.includes(candidate.id)
            ? 'bg-indigo-50 border-indigo-300'
            : 'bg-white border-slate-200 hover:border-slate-300'"
        >
          <input
            type="checkbox"
            :checked="selectedCandidateIds.includes(candidate.id)"
            @change="toggleCandidate(candidate.id)"
            class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"
            :disabled="!selectedCandidateIds.includes(candidate.id) && selectedCandidateIds.length >= 3"
          >
          <div class="flex-1 min-w-0">
            <p class="font-medium text-slate-900 truncate">{{ candidate.name }}</p>
            <div class="flex items-center gap-2 mt-1">
              <Badge :variant="getStatusVariant(candidate.status)" size="sm" />
              <span v-if="candidate.ai_score" class="text-sm text-slate-500">
                Score: {{ candidate.ai_score }}
              </span>
            </div>
          </div>
        </label>
      </div>
    </div>

    <!-- Step 2: Run Comparison -->
    <Button
      variant="primary"
      :disabled="selectedCandidateIds.length < 2 || isLoading"
      @click="runComparison"
    >
      <Spinner v-if="isLoading" size="sm" class="mr-2" />
      Compare
    </Button>

    <!-- Error State -->
    <div v-if="error" class="bg-rose-50 border border-rose-200 rounded-lg p-4 text-rose-700">
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white rounded-xl border border-slate-200 p-12 shadow-sm">
      <div class="flex flex-col items-center justify-center">
        <Spinner size="lg" />
        <p class="mt-4 text-slate-500">Comparing candidates...</p>
      </div>
    </div>

    <!-- Step 3: Comparison Results -->
    <div v-if="comparisonData?.candidates?.length > 0" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50">
            <th class="text-left px-4 py-3 text-sm font-medium text-slate-500 w-40">Attribute</th>
            <th
              v-for="candidate in comparisonData.candidates"
              :key="candidate.id"
              class="text-left px-4 py-3"
            >
              <div class="flex flex-col">
                <span class="font-semibold text-slate-900">{{ candidate.name }}</span>
                <Button variant="ghost" size="sm" class="mt-1" @click="viewProfile(candidate.id)">
                  View Profile
                </Button>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr>
            <td class="px-4 py-3 text-sm font-medium text-slate-500">Status</td>
            <td v-for="candidate in comparisonData.candidates" :key="candidate.id" class="px-4 py-3">
              <Badge :variant="getStatusVariant(candidate.status)" />
            </td>
          </tr>
          <tr>
            <td class="px-4 py-3 text-sm font-medium text-slate-500">AI Score</td>
            <td
              v-for="candidate in comparisonData.candidates"
              :key="candidate.id"
              class="px-4 py-3"
              :class="isBestValue(candidate.id, 'ai_score', candidate.ai_score) ? 'bg-emerald-50' : ''"
            >
              <span class="font-bold" :class="getScoreColor(candidate.ai_score)">
                {{ candidate.ai_score || '—' }}
              </span>
            </td>
          </tr>
          <tr>
            <td class="px-4 py-3 text-sm font-medium text-slate-500">Experience</td>
            <td
              v-for="candidate in comparisonData.candidates"
              :key="candidate.id"
              class="px-4 py-3"
              :class="isBestValue(candidate.id, 'years_experience', getFieldValue(candidate.id, 'years_experience')) ? 'bg-emerald-50' : ''"
            >
              {{ getFieldValue(candidate.id, 'years_experience') }}
            </td>
          </tr>
          <tr>
            <td class="px-4 py-3 text-sm font-medium text-slate-500">Skills</td>
            <td v-for="candidate in comparisonData.candidates" :key="candidate.id" class="px-4 py-3">
              <div v-if="formatSkills(candidate.id) !== '—'" class="flex flex-wrap gap-1">
                <span
                  v-for="skill in formatSkills(candidate.id)"
                  :key="skill"
                  class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-xs"
                >
                  {{ skill }}
                </span>
              </div>
              <span v-else class="text-slate-400">—</span>
            </td>
          </tr>
          <tr>
            <td class="px-4 py-3 text-sm font-medium text-slate-500">Education</td>
            <td v-for="candidate in comparisonData.candidates" :key="candidate.id" class="px-4 py-3">
              <div v-if="getFieldValue(candidate.id, 'education') !== '—'" class="space-y-1">
                <div
                  v-for="edu in getFieldValue(candidate.id, 'education')"
                  :key="edu"
                  class="text-sm text-slate-700"
                >
                  {{ edu }}
                </div>
              </div>
              <span v-else class="text-slate-400">—</span>
            </td>
          </tr>
          <tr>
            <td class="px-4 py-3 text-sm font-medium text-slate-500">Languages</td>
            <td v-for="candidate in comparisonData.candidates" :key="candidate.id" class="px-4 py-3">
              <div v-if="getFieldValue(candidate.id, 'languages') !== '—'" class="flex flex-wrap gap-1">
                <span
                  v-for="lang in getFieldValue(candidate.id, 'languages')"
                  :key="lang"
                  class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-full text-xs"
                >
                  {{ lang }}
                </span>
              </div>
              <span v-else class="text-slate-400">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
