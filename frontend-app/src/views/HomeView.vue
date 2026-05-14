<template>
  <div class="home">
    <h1>Welcome to SaaS App</h1>
    <p>This is the home page</p>
    <RouterLink to="/dashboard">Go to Dashboard</RouterLink>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
</script>

<style scoped>
.home {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  text-align: center;
}

a {
  margin-top: 1rem;
  padding: 0.75rem 1.5rem;
  background: #007bff;
  color: white;
  text-decoration: none;
  border-radius: 4px;
}

a:hover {
  background: #0056b3;
}
</style>
