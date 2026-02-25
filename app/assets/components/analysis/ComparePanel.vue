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

// 10 comparison criteria with display helpers
const comparisonCriteria = [
  { key: 'professional_summary', label: 'Professional Summary', subtitle: 'Role focus & seniority' },
  { key: 'technical_skills', label: 'Technical Skills', subtitle: 'Languages, frameworks, tools' },
  { key: 'work_experience', label: 'Work Experience', subtitle: 'Years + similar projects' },
  { key: 'achievements', label: 'Achievements', subtitle: 'Metrics, impact, improvements' },
  { key: 'project_complexity', label: 'Project Complexity', subtitle: 'Built vs assisted' },
  { key: 'engineering_practices', label: 'Engineering Practices', subtitle: 'Tests, CI/CD, architecture' },
  { key: 'education', label: 'Education', subtitle: 'Degrees & certifications' },
  { key: 'side_projects', label: 'Side Projects', subtitle: 'Portfolio, GitHub' },
  { key: 'career_progression', label: 'Career Progression', subtitle: 'Growth & stability' },
  { key: 'languages', label: 'Languages', subtitle: 'Spoken languages' },
]

const getStatusVariant = (status) => {
  const variants = {
    new: 'default',
    processing: 'warning',
    ready: 'success',
    shortlisted: 'info',
    rejected: 'danger',
    employed: 'success',
    freelance: 'info',
    seeking: 'warning',
  }
  return variants[status] || 'default'
}

const getScoreColor = (score) => {
  if (score === null || score === undefined) return 'text-slate-400'
  if (score <= 40) return 'text-rose-500'
  if (score <= 70) return 'text-amber-500'
  return 'text-emerald-500'
}

const availableCandidates = computed(() => {
  return candidatesStore.candidates
})

const toggleCandidate = (id) => {
  const index = selectedCandidateIds.value.indexOf(id)
  if (index === -1) {
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

// Get candidate by ID from comparison data
const getCandidateById = (id) => {
  return comparisonData.value?.candidates?.find(c => c.id === id)
}

// Get extracted data with fallback
const getExtractedData = (candidateId) => {
  const candidate = getCandidateById(candidateId)
  return candidate?.extracted || {}
}

// Get field value with safe fallback
const getFieldValue = (candidateId, field, defaultValue = '—') => {
  const data = getExtractedData(candidateId)
  const value = data[field]
  if (value === null || value === undefined || value === '') return defaultValue
  return value
}

// Format technical skills - can be either array or object
const formatTechnicalSkills = (candidateId) => {
  const data = getExtractedData(candidateId)
  const skills = data.technical_skills

  if (!skills) {
    // Fallback to plain skills array
    const plainSkills = data.skills
    if (!plainSkills || !Array.isArray(plainSkills)) return '—'
    return plainSkills.slice(0, 10).join(', ')
  }

  // It's an object with categories
  const parts = []
  if (skills.languages?.length) parts.push(...skills.languages.slice(0, 3))
  if (skills.frameworks?.length) parts.push(...skills.frameworks.slice(0, 3))
  if (skills.tools?.length) parts.push(...skills.tools.slice(0, 2))
  if (skills.platforms?.length) parts.push(...skills.platforms.slice(0, 2))

  return parts.length > 0 ? parts.slice(0, 8).join(', ') : '—'
}

// Format years of experience
const formatYearsExperience = (candidateId) => {
  const data = getExtractedData(candidateId)
  const years = data.years_experience

  if (years === null || years === undefined) return '—'
  return `${years} year${years !== 1 ? 's' : ''}`
}

// Format work experience as summary
const formatWorkExperience = (candidateId) => {
  const data = getExtractedData(candidateId)
  const exp = data.work_experience

  if (!exp || !Array.isArray(exp) || exp.length === 0) {
    // Fallback to years
    return formatYearsExperience(candidateId)
  }

  // Get first 2 jobs
  const jobs = exp.slice(0, 2).map(job => {
    const title = job.title || 'Unknown'
    const years = job.years || ''
    return years ? `${title} (${years}y)` : title
  })

  return jobs.join(' | ')
}

// Format achievements
const formatAchievements = (candidateId) => {
  const data = getExtractedData(candidateId)
  const achievements = data.achievements

  if (!achievements || !Array.isArray(achievements) || achievements.length === 0) return '—'
  return achievements.slice(0, 3)
}

// Format education - ensure it's actual degrees, not contact info
const formatEducation = (candidateId) => {
  const data = getExtractedData(candidateId)
  const education = data.education

  if (!education || !Array.isArray(education) || education.length === 0) return '—'

  // Filter out anything that looks like contact info
  const validEducation = education.filter(edu => {
    const str = String(edu)
    return !str.includes('@') && !str.match(/^\+?\d{8,}/) && str.length > 5
  })

  return validEducation.length > 0 ? validEducation : '—'
}

// Format languages
const formatLanguages = (candidateId) => {
  const data = getExtractedData(candidateId)
  const languages = data.languages

  if (!languages || !Array.isArray(languages) || languages.length === 0) return '—'
  return languages.slice(0, 5)
}

// Format side projects
const formatSideProjects = (candidateId) => {
  const data = getExtractedData(candidateId)
  const projects = data.side_projects

  if (!projects || !Array.isArray(projects) || projects.length === 0) return '—'
  return projects.slice(0, 3)
}

// Determine best score for highlighting
const getBestScore = () => {
  if (!comparisonData.value?.candidates) return null
  const scores = comparisonData.value.candidates
    .map(c => c.ai_score || 0)
    .filter(s => s > 0)
  return scores.length > 0 ? Math.max(...scores) : null
}

const isBestScore = (candidateId) => {
  const best = getBestScore()
  if (best === null) return false
  const candidate = getCandidateById(candidateId)
  return candidate?.ai_score === best
}

// Helper for rendering array content
const renderArrayContent = (value) => {
  if (value === '—') return '—'
  if (Array.isArray(value)) return value
  return [value]
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
    <div v-if="comparisonData?.candidates?.length > 0" class="space-y-4">
      <!-- Header with candidate names -->
      <div class="bg-slate-800 text-white rounded-t-xl p-4">
        <div class="flex">
          <div class="w-48 flex-shrink-0"></div>
          <div
            v-for="candidate in comparisonData.candidates"
            :key="candidate.id"
            class="flex-1 text-center"
          >
            <div class="font-semibold text-lg">{{ candidate.name }}</div>
            <Button variant="ghost" size="sm" class="text-indigo-300 hover:text-white mt-1" @click="viewProfile(candidate.id)">
              View Profile
            </Button>
          </div>
        </div>
      </div>

      <!-- Basic Info Row -->
      <div class="bg-white rounded-b-xl border-x border-b border-slate-200 overflow-hidden">
        <div class="flex">
          <div class="w-48 flex-shrink-0 bg-slate-50 p-4 border-r border-slate-200">
            <div class="font-medium text-slate-700">Status</div>
            <div class="text-xs text-slate-500">Employment status</div>
          </div>
          <div
            v-for="candidate in comparisonData.candidates"
            :key="candidate.id"
            class="flex-1 p-4 border-r border-slate-200 last:border-r-0"
          >
            <Badge :variant="getStatusVariant(candidate.status)" />
          </div>
        </div>

        <div class="flex border-t border-slate-200">
          <div class="w-48 flex-shrink-0 bg-slate-50 p-4 border-r border-slate-200">
            <div class="font-medium text-slate-700">AI Score</div>
            <div class="text-xs text-slate-500">Overall fit score</div>
          </div>
          <div
            v-for="candidate in comparisonData.candidates"
            :key="candidate.id"
            class="flex-1 p-4 border-r border-slate-200 last:border-r-0"
            :class="isBestScore(candidate.id) ? 'bg-emerald-50' : ''"
          >
            <span class="font-bold text-lg" :class="getScoreColor(candidate.ai_score)">
              {{ candidate.ai_score || '—' }}
            </span>
          </div>
        </div>

        <div class="flex border-t border-slate-200">
          <div class="w-48 flex-shrink-0 bg-slate-50 p-4 border-r border-slate-200">
            <div class="font-medium text-slate-700">Experience</div>
            <div class="text-xs text-slate-500">Total years</div>
          </div>
          <div
            v-for="candidate in comparisonData.candidates"
            :key="candidate.id"
            class="flex-1 p-4 border-r border-slate-200 last:border-r-0"
          >
            {{ formatYearsExperience(candidate.id) }}
          </div>
        </div>
      </div>

      <!-- 10 Comparison Criteria -->
      <div
        v-for="(criteria, index) in comparisonCriteria"
        :key="criteria.key"
        class="bg-white rounded-xl border border-slate-200 overflow-hidden"
      >
        <div class="flex">
          <div class="w-48 flex-shrink-0 bg-slate-50 p-4 border-r border-slate-200">
            <div class="font-medium text-slate-700">{{ criteria.label }}</div>
            <div class="text-xs text-slate-500">{{ criteria.subtitle }}</div>
          </div>
          <div
            v-for="candidate in comparisonData.candidates"
            :key="candidate.id"
            class="flex-1 p-4 border-r border-slate-200 last:border-r-0"
          >
            <!-- Professional Summary -->
            <template v-if="criteria.key === 'professional_summary'">
              <p v-if="getFieldValue(candidate.id, 'professional_summary') !== '—'" class="text-sm text-slate-700">
                {{ getFieldValue(candidate.id, 'professional_summary') }}
              </p>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Technical Skills -->
            <template v-else-if="criteria.key === 'technical_skills'">
              <div v-if="formatTechnicalSkills(candidate.id) !== '—'" class="flex flex-wrap gap-1">
                <span
                  v-for="skill in formatTechnicalSkills(candidate.id).split(', ')"
                  :key="skill"
                  class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-xs"
                >
                  {{ skill.trim() }}
                </span>
              </div>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Work Experience -->
            <template v-else-if="criteria.key === 'work_experience'">
              <div v-if="getFieldValue(candidate.id, 'work_experience', []).length > 0">
                <div
                  v-for="(job, idx) in renderArrayContent(getFieldValue(candidate.id, 'work_experience')).slice(0, 3)"
                  :key="idx"
                  class="text-sm text-slate-700 mb-1"
                >
                  {{ typeof job === 'object' ? (job.title || job) : job }}
                </div>
              </div>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Achievements -->
            <template v-else-if="criteria.key === 'achievements'">
              <div v-if="formatAchievements(candidate.id) !== '—'" class="space-y-1">
                <div
                  v-for="(achievement, idx) in formatAchievements(candidate.id)"
                  :key="idx"
                  class="text-sm text-slate-700 flex items-start gap-2"
                >
                  <span class="text-emerald-500">•</span>
                  {{ achievement }}
                </div>
              </div>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Project Complexity -->
            <template v-else-if="criteria.key === 'project_complexity'">
              <p v-if="getFieldValue(candidate.id, 'project_complexity') !== '—'" class="text-sm text-slate-700">
                {{ getFieldValue(candidate.id, 'project_complexity') }}
              </p>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Engineering Practices -->
            <template v-else-if="criteria.key === 'engineering_practices'">
              <div v-if="getFieldValue(candidate.id, 'engineering_practices', []).length > 0" class="flex flex-wrap gap-1">
                <span
                  v-for="practice in renderArrayContent(getFieldValue(candidate.id, 'engineering_practices')).slice(0, 6)"
                  :key="practice"
                  class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-xs"
                >
                  {{ practice }}
                </span>
              </div>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Education -->
            <template v-else-if="criteria.key === 'education'">
              <div v-if="formatEducation(candidate.id) !== '—'" class="space-y-1">
                <div
                  v-for="(edu, idx) in formatEducation(candidate.id)"
                  :key="idx"
                  class="text-sm text-slate-700"
                >
                  {{ edu }}
                </div>
              </div>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Side Projects -->
            <template v-else-if="criteria.key === 'side_projects'">
              <div v-if="formatSideProjects(candidate.id) !== '—'" class="space-y-1">
                <div
                  v-for="(project, idx) in formatSideProjects(candidate.id)"
                  :key="idx"
                  class="text-sm text-slate-700 flex items-start gap-2"
                >
                  <span class="text-indigo-500">◆</span>
                  {{ project }}
                </div>
              </div>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Career Progression -->
            <template v-else-if="criteria.key === 'career_progression'">
              <p v-if="getFieldValue(candidate.id, 'career_progression') !== '—'" class="text-sm text-slate-700">
                {{ getFieldValue(candidate.id, 'career_progression') }}
              </p>
              <span v-else class="text-slate-400">—</span>
            </template>

            <!-- Languages -->
            <template v-else-if="criteria.key === 'languages'">
              <div v-if="formatLanguages(candidate.id) !== '—'" class="flex flex-wrap gap-1">
                <span
                  v-for="lang in formatLanguages(candidate.id)"
                  :key="lang"
                  class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs"
                >
                  {{ lang }}
                </span>
              </div>
              <span v-else class="text-slate-400">—</span>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
