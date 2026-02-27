<script setup>
import { ref, onMounted, nextTick, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useConversationsStore } from '../stores/conversations.js'
import { candidatesApi } from '../api/client.js'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'

const route = useRoute()
const conversationsStore = useConversationsStore()

const conversationId = ref(Number(route.params.id))
const messageInput = ref('')
const messagesContainer = ref(null)
const isSending = ref(false)
const isTyping = ref(false)
const textareaRef = ref(null)
const candidateProfile = ref(null)

// Suggested questions for empty chat
const suggestedQuestions = [
  "What are this candidate's main technical skills?",
  "How many years of experience do they have?",
  "Is this candidate a good fit for a senior role?",
  "What languages do they speak?",
]

// Get current candidate from conversations store (populated when fetching messages)
const currentCandidate = computed(() => conversationsStore.currentConversation)

const formatDate = (date) => {
  return new Date(date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

const scrollToBottom = async () => {
  await nextTick()
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

const adjustTextareaHeight = () => {
  if (textareaRef.value) {
    textareaRef.value.style.height = 'auto'
    const newHeight = Math.min(textareaRef.value.scrollHeight, 80) // max ~3 rows
    textareaRef.value.style.height = newHeight + 'px'
  }
}

const sendMessage = async () => {
  if (!messageInput.value.trim() || isSending.value) return

  isSending.value = true
  const message = messageInput.value
  messageInput.value = ''

  // Reset textarea height
  if (textareaRef.value) {
    textareaRef.value.style.height = 'auto'
  }

  try {
    isTyping.value = true
    await conversationsStore.sendMessage(conversationId.value, message)
    scrollToBottom()
  } catch (e) {
    console.error(e)
    messageInput.value = message
  } finally {
    isSending.value = false
    isTyping.value = false
  }
}

const askSuggestedQuestion = async (question) => {
  messageInput.value = question
  adjustTextareaHeight()
  await sendMessage()
}

const handleKeydown = (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    sendMessage()
  }
}

onMounted(async () => {
  try {
    await conversationsStore.fetchMessages(conversationId.value)
    const candidateId = conversationsStore.currentConversation?.candidate_id
    if (candidateId) {
      const response = await candidatesApi.get(candidateId)
      candidateProfile.value = response.data
    }
    scrollToBottom()
  } catch (e) {
    console.error(e)
  }
})

watch(() => conversationsStore.messages.length, () => {
  scrollToBottom()
})
</script>

<template>
  <div class="flex h-[calc(100vh-11rem)] md:h-[calc(100vh-8rem)]">
    <!-- Left Info Panel -->
    <div class="hidden lg:flex w-64 bg-white border-r border-slate-200 p-6 flex-col">
      <h2 class="text-lg font-semibold text-slate-900 mb-6">Chat Info</h2>

      <div class="space-y-4">
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase">Candidate</label>
          <p class="text-slate-900 font-medium">
            {{ currentCandidate?.candidate_name || 'Unknown' }}
          </p>
        </div>

        <div>
          <label class="text-xs font-medium text-slate-500 uppercase">Status</label>
          <p class="text-slate-900 capitalize">
            {{ currentCandidate?.candidate_status || 'N/A' }}
          </p>
        </div>

        <div v-if="currentCandidate?.candidate_ai_score !== null && currentCandidate?.candidate_ai_score !== undefined">
          <label class="text-xs font-medium text-slate-500 uppercase">AI Score</label>
          <div class="flex items-center gap-2">
            <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full"
                :class="currentCandidate.candidate_ai_score <= 40 ? 'bg-rose-500' : currentCandidate.candidate_ai_score <= 70 ? 'bg-amber-500' : 'bg-emerald-500'"
                :style="{ width: currentCandidate.candidate_ai_score + '%' }"
              />
            </div>
            <span class="text-sm font-medium text-slate-700">{{ currentCandidate.candidate_ai_score }}</span>
          </div>
        </div>

        <div>
          <label class="text-xs font-medium text-slate-500 uppercase">Job Position</label>
          <p class="text-slate-900">{{ candidateProfile?.job_position?.title || 'Not linked' }}</p>
        </div>
      </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col bg-slate-50">
      <!-- Messages -->
      <div ref="messagesContainer" class="flex-1 overflow-auto p-6 space-y-4">
        <div v-if="conversationsStore.loading && conversationsStore.messages.length === 0" class="flex items-center justify-center h-full">
          <Spinner size="lg" />
        </div>

        <!-- Empty State with Suggestions -->
        <div v-else-if="conversationsStore.messages.length === 0" class="flex flex-col items-center justify-center h-full text-center px-4">
          <div class="w-16 h-16 mb-4 rounded-full bg-cyan-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-cyan-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
          </div>
          <p class="text-slate-600 mb-2">Ask anything about this candidate's CV, skills or experience</p>
          <div class="flex flex-wrap gap-2 justify-center mt-4">
            <button
              v-for="question in suggestedQuestions"
              :key="question"
              @click="askSuggestedQuestion(question)"
              class="px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-all duration-200"
            >
              {{ question }}
            </button>
          </div>
        </div>

        <!-- Messages -->
        <template v-else>
          <div
            v-for="message in conversationsStore.messages"
            :key="message.id"
            class="flex"
            :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
          >
            <div
              class="max-w-[70%] rounded-2xl px-4 py-3 shadow-sm"
              :class="message.role === 'user' ? 'bg-cyan-700 text-white rounded-tr-sm' : 'bg-white border border-slate-200 text-slate-800 rounded-tl-sm'"
            >
              <p class="whitespace-pre-wrap">{{ message.content }}</p>
              <p
                class="text-xs mt-1"
                :class="message.role === 'user' ? 'text-cyan-100' : 'text-slate-400'"
              >
                {{ formatDate(message.created_at) }}
              </p>
            </div>
          </div>

          <!-- Typing Indicator -->
          <div v-if="isTyping" class="flex justify-start">
            <div class="bg-white border border-slate-200 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm">
              <div class="flex gap-1">
                <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Input Area -->
      <div class="bg-white border-t border-slate-200 p-4">
        <form @submit.prevent="sendMessage" class="flex gap-3 items-end">
          <textarea
            ref="textareaRef"
            v-model="messageInput"
            @input="adjustTextareaHeight"
            @keydown="handleKeydown"
            placeholder="Type your message..."
            rows="1"
            class="flex-1 px-4 py-2 border border-slate-300 rounded-lg resize-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
            :disabled="isSending"
          />
          <Button variant="primary" type="submit" :disabled="!messageInput.trim() || isSending">
            <Spinner v-if="isSending" size="sm" />
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
          </Button>
        </form>
      </div>
    </div>
  </div>
</template>
