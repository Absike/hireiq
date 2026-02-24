import axios from 'axios'

const api = axios.create({ baseURL: 'http://localhost:8080' })

export const candidatesApi = {
  list: () => api.get('/api/candidates'),
  get: (id) => api.get(`/api/candidates/${id}`),
  upload: (formData) => api.post('/api/candidates', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  delete: (id) => api.delete(`/api/candidates/${id}`),
  score: (id, jobPositionId) => api.post(`/api/candidates/${id}/score`, { job_position_id: jobPositionId }),
  summarize: (id) => api.post(`/api/candidates/${id}/summarize`),
  chat: (id, question) => api.post(`/api/candidates/${id}/chat`, { question }),
  interviewQuestions: (id, jobPositionId) => api.post(`/api/candidates/${id}/interview-questions`, { job_position_id: jobPositionId }),
  updateStatus: (id, status) => api.patch(`/api/candidates/${id}/status`, { status }),
}

export const jobsApi = {
  list: () => api.get('/api/jobs'),
  get: (id) => api.get(`/api/jobs/${id}`),
  create: (data) => api.post('/api/jobs', data),
  update: (id, data) => api.patch(`/api/jobs/${id}`, data),
  delete: (id) => api.delete(`/api/jobs/${id}`),
}

export const conversationsApi = {
  create: (candidateId) =>
    api.post('/api/conversations', { candidate_id: candidateId }),
  getMessages: (id) => api.get(`/api/conversations/${id}/messages`),
  sendMessage: (id, message) =>
    api.post(`/api/conversations/${id}/messages`, { message }),
}

export const analysisApi = {
  rank: (jobId, candidateIds) =>
    api.post('/api/analysis/rank', { job_id: jobId, candidate_ids: candidateIds }),
  interviewQuestions: (candidateId, jobId) =>
    api.post('/api/analysis/interview-questions', { candidate_id: candidateId, job_id: jobId }),
  compare: (candidateIds) =>
    api.post('/api/analysis/compare', { candidate_ids: candidateIds }),
}

export default api
