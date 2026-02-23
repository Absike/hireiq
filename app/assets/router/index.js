import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/Dashboard.vue'),
  },
  {
    path: '/candidates',
    name: 'candidates',
    component: () => import('../views/Candidates.vue'),
  },
  {
    path: '/candidates/:id',
    name: 'candidate-detail',
    component: () => import('../views/CandidateDetail.vue'),
  },
  {
    path: '/jobs',
    name: 'jobs',
    component: () => import('../views/Jobs.vue'),
  },
  {
    path: '/jobs/:id',
    name: 'job-detail',
    component: () => import('../views/JobDetail.vue'),
  },
  {
    path: '/analysis',
    name: 'analysis',
    component: () => import('../views/Analysis.vue'),
  },
  {
    path: '/chat/:id',
    name: 'chat',
    component: () => import('../views/Chat.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
