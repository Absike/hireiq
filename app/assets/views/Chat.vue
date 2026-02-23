<script setup>
import { ref, onMounted, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useConversationsStore } from '../stores/conversations.js'
import { useCandidatesStore } from '../stores/candidates.js'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'

const route = useRoute()
const conversationsStore = useConversationsStore()
const candidatesStore = useCandidatesStore()

const conversationId = ref(Number(route.params.id))
const messageInput = ref('')
const messagesContainer = ref(null)
const isSending = ref(false)
const apiPowered = ref(true)

const formatDate = (date) => {
  return new Date(date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

const scrollToBottom = async () => {
  await nextTick()
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

const sendMessage = async () => {
  if (!messageInput.value.trim() || isSending.value) return

  isSending.value = true
  const message = messageInput.value
  messageInput.value = ''

  try {
    await conversationsStore.sendMessage(conversationId.value, message)
    scrollToBottom()
  } catch (e) {
    console.error(e)
    messageInput.value = message
  } finally {
    isSending.value = false
  }
}

onMounted(async () => {
  try {
    await Promise.all([
      conversationsStore.fetchMessages(conversationId.value),
      candidatesStore.fetchCandidates(),
    ])
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
  <div class="flex h-[calc(100vh-8rem)]">
    <!-- Left Info Panel -->
    <div class="w-80 bg-white border-r border-slate-200 p-6 flex flex-col">
      <h2 class="text-lg font-semibold text-slate-900 mb-6">Chat Info</h2>

      <div class="space-y-4">
        <div>
          <label class="text-xs font-medium text-slate-500 uppercase">Candidate</label>
          <p class="text-slate-900">
            {{ candidatesStore.candidates[0]?.name || 'Unknown' }}
          </p>
        </div>

        <div>
          <label class="text-xs font-medium text-slate-500 uppercase">Job Position</label>
          <p class="text-slate-900">Senior Developer</p>
        </div>

        <div>
          <span
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
            :class="apiPowered ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'"
          >
            {{ apiPowered ? '🤖 AI Powered' : '⚙️ Mock Mode' }}
          </span>
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

        <div
          v-for="message in conversationsStore.messages"
          :key="message.id"
          class="flex"
          :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <div
            class="max-w-[70%] rounded-2xl px-4 py-2"
            :class="message.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-900 border border-slate-200'"
          >
            <p class="whitespace-pre-wrap">{{ message.content }}</p>
            <p
              class="text-xs mt-1"
              :class="message.role === 'user' ? 'text-indigo-200' : 'text-slate-400'"
            >
              {{ formatDate(message.created_at) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Input Area -->
      <div class="bg-white border-t border-slate-200 p-4">
        <form @submit.prevent="sendMessage" class="flex gap-3">
          <input
            v-model="messageInput"
            type="text"
            placeholder="Type your message..."
            class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            :disabled="isSending"
          >
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
