<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';

const router = useRouter();
const yandexUrl = ref('');
const error = ref('');
const success = ref('');
const isLoading = ref(true);
const isSaving = ref(false);
const isLoggingOut = ref(false);

onMounted(async () => {
    try {
        const response = await api.get('/api/organization');
        yandexUrl.value = response.data.data?.yandex_url ?? '';
    } catch (requestError) {
        error.value = 'Не удалось загрузить настройки организации.';
    } finally {
        isLoading.value = false;
    }
});

async function saveOrganization() {
    error.value = '';
    success.value = '';
    isSaving.value = true;

    try {
        const response = await api.put('/api/organization', {
            yandex_url: yandexUrl.value,
        });

        yandexUrl.value = response.data.data.yandex_url;
        success.value = 'Ссылка сохранена.';
    } catch (requestError) {
        error.value = requestError.response?.data?.errors?.yandex_url?.[0]
            ?? 'Не удалось сохранить ссылку.';
    } finally {
        isSaving.value = false;
    }
}

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
                    <p class="mt-2 text-slate-600">Подключите карточку организации на Яндекс.Картах.</p>
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

            <form class="mt-8 max-w-xl" @submit.prevent="saveOrganization">
                <label class="block text-sm font-medium text-slate-700" for="yandex-url">
                    Ссылка на организацию в Яндекс.Картах
                </label>
                <input
                    id="yandex-url"
                    v-model="yandexUrl"
                    class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-blue-600 disabled:bg-slate-100"
                    type="url"
                    placeholder="https://yandex.ru/maps/org/..."
                    :disabled="isLoading || isSaving"
                    required
                >

                <p v-if="error" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700" role="alert">
                    {{ error }}
                </p>
                <p v-if="success" class="mt-3 rounded-md bg-green-50 p-3 text-sm text-green-700" role="status">
                    {{ success }}
                </p>

                <button
                    class="mt-5 rounded-md bg-blue-600 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                    type="submit"
                    :disabled="isLoading || isSaving"
                >
                    {{ isSaving ? 'Сохраняем…' : 'Сохранить' }}
                </button>
            </form>
        </div>
    </main>
</template>
