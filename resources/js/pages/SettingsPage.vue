<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';

const router = useRouter();
const isLoggingOut = ref(false);

async function logout() {
    isLoggingOut.value = true;

    try {
        await api.post('/logout');
        await router.replace('/login');
    } finally {
        isLoggingOut.value = false;
    }
}
</script>

<template>
    <main class="min-h-screen bg-slate-100 p-6">
        <div class="mx-auto max-w-3xl rounded-xl bg-white p-8 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Настройки</h1>
                    <p class="mt-2 text-slate-600">На следующем этапе здесь появится подключение организации.</p>
                </div>

                <button
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 disabled:opacity-60"
                    type="button"
                    :disabled="isLoggingOut"
                    @click="logout"
                >
                    {{ isLoggingOut ? 'Выходим…' : 'Выйти' }}
                </button>
            </div>
        </div>
    </main>
</template>
