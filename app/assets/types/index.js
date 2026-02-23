// Types
export const Candidate = {
  id: 0,
  name: '',
  email: null,
  status: 'new',
  ai_score: null,
  ai_summary: null,
  ai_extracted_data: null,
  created_at: ''
}

export const JobPosition = {
  id: 0,
  title: '',
  description: '',
  requirements: null,
  status: 'open',
  candidates: 0,
  created_at: ''
}

export const Message = {
  id: 0,
  role: 'user',
  content: '',
  sources: null,
  created_at: ''
}

export const Conversation = {
  id: 0,
  title: '',
  candidate_id: 0,
  created_at: ''
}

export const RankingResult = {
  candidate_id: 0,
  name: '',
  email: '',
  score: 0,
  summary: '',
  status: ''
}
