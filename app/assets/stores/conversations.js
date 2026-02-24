import { defineStore } from 'pinia'
import { ref } from 'vue'
import { conversationsApi } from '../api/client.js'

export const useConversationsStore = defineStore('conversations', () => {
  const conversations = ref([])
  const currentConversation = ref(null)
  const messages = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function createConversation(candidateId) {
    loading.value = true
    error.value = null
    try {
      const response = await conversationsApi.create(candidateId)
      currentConversation.value = response.data
      conversations.value.unshift(response.data)
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to create conversation'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchMessages(conversationId) {
    loading.value = true
    error.value = null
    try {
      const response = await conversationsApi.getMessages(conversationId)
      // API returns { conversation_id, messages: [...] }
      messages.value = response.data.messages || []
      return response.data
    } catch (e) {
      error.value = e.message || 'Failed to fetch messages'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function sendMessage(conversationId, message) {
    loading.value = true
    error.value = null
    try {
      const response = await conversationsApi.sendMessage(conversationId, message)
      const data = response.data
      // API returns { user_message: {...}, ai_response: {...} }
      if (data?.user_message) {
        messages.value.push(data.user_message)
      }
      if (data?.ai_response) {
        messages.value.push(data.ai_response)
      }
      return data
    } catch (e) {
      error.value = e.message || 'Failed to send message'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    conversations,
    currentConversation,
    messages,
    loading,
    error,
    createConversation,
    fetchMessages,
    sendMessage,
  }
})
