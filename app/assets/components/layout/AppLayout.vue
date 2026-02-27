<script setup>
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
</script>

<template>
  <div class="flex min-h-screen bg-[radial-gradient(circle_at_top,#e0f2fe,transparent_45%),linear-gradient(180deg,#f8fafc,#f1f5f9)]">
    <Sidebar class="hidden lg:flex" />
    <div class="flex-1 flex flex-col overflow-hidden">
      <TopBar />
      <main class="flex-1 overflow-auto p-4 md:p-6 lg:p-8">
        <router-view />
      </main>
    </div>
  </div>
</template>
