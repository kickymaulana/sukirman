<template>
  <div class="p-8 max-w-md mx-auto text-center space-y-4">
    <h1 class="text-2xl font-bold font-sans">Selamat Datang, {{ name }}!</h1>
    <p class="text-gray-600">Inertia v3 + Varlet + Tailwind v4 siap digunakan.</p>

    <var-button type="success" block>
      Tombol Varlet Berhasil Dimuat
    </var-button>
  </div>
</template>

<script setup lang="ts">
// Menerima data dari Controller Laravel
defineProps<{
  name: string;
}>();
</script>
