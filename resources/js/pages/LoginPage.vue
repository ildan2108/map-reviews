<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';

const route = useRoute();
const router = useRouter();
const credentials = reactive({
    email: '',
    password: '',
});
const error = ref('');
const isLoading = ref(false);

onMounted(async () => {
    try {
        await api.get('/api/user');
        await router.replace('/settings');
    } catch (requestError) {
        if (requestError.response?.status !== 401) {
            error.value = 'Не удалось проверить текущую сессию.';
        }
    }
});

async function login() {
    error.value = '';
    isLoading.value = true;

    try {
        await api.get('/sanctum/csrf-cookie');
        await api.post('/login', credentials);
        await router.push(route.query.redirect ?? '/settings');
    } catch (requestError) {
        error.value = requestError.response?.data?.errors?.email?.[0]
            ?? 'Не удалось выполнить вход. Попробуйте ещё раз.';
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-slate-100 p-6">
        <form class="w-full max-w-sm rounded-xl bg-white p-8 shadow-sm" @submit.prevent="login">
            <h1 class="text-2xl font-semibold text-slate-900">Map Reviews</h1>
            <p class="mt-2 text-sm text-slate-600">Войдите для управления карточками организаций.</p>

            <p v-if="error" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700" role="alert">
                {{ error }}
            </p>

            <label class="mt-6 block text-sm font-medium text-slate-700" for="email">Email</label>
            <input
                id="email"
                v-model="credentials.email"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-blue-600"
                type="email"
                autocomplete="email"
                required
            >

            <label class="mt-4 block text-sm font-medium text-slate-700" for="password">Пароль</label>
            <input
                id="password"
                v-model="credentials.password"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-blue-600"
                type="password"
                autocomplete="current-password"
                required
            >

            <button
                class="mt-6 w-full rounded-md bg-blue-600 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                type="submit"
                :disabled="isLoading"
            >
                {{ isLoading ? 'Входим…' : 'Войти' }}
            </button>
        </form>
    </main>
</template>
