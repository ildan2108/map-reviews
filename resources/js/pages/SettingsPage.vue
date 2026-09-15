<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import ReviewsList from '../components/ReviewsList.vue';
import api from '../services/api';

const router = useRouter();
const organization = ref(null);
const yandexUrl = ref('');
const error = ref('');
const success = ref('');
const isLoading = ref(true);
const isSaving = ref(false);
const isLoggingOut = ref(false);
let pollingTimer = null;

onMounted(async () => {
    await loadOrganization();
});

onBeforeUnmount(() => {
    stopPolling();
});

async function loadOrganization() {
    try {
        const response = await api.get('/api/organization');
        organization.value = response.data.data;
        yandexUrl.value = response.data.data?.yandex_url ?? '';
        syncPolling();
    } catch (requestError) {
        error.value = 'Не удалось загрузить настройки организации.';
    } finally {
        isLoading.value = false;
    }
}

async function refreshOrganization() {
    try {
        const response = await api.get('/api/organization');
        organization.value = response.data.data;
        syncPolling();
    } catch (requestError) {
        stopPolling();
    }
}

function syncPolling() {
    if (['pending', 'processing'].includes(organization.value?.parsing_status)) {
        if (pollingTimer === null) {
            pollingTimer = window.setInterval(refreshOrganization, 3000);
        }
        return;
    }

    stopPolling();
}

function stopPolling() {
    if (pollingTimer !== null) {
        window.clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

async function saveOrganization() {
    error.value = '';
    success.value = '';
    isSaving.value = true;

    try {
        const response = await api.put('/api/organization', {
            yandex_url: yandexUrl.value,
        });

        organization.value = response.data.data;
        yandexUrl.value = response.data.data.yandex_url;
        success.value = 'Ссылка сохранена. Данные организации загружаются.';
        syncPolling();
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

                <div v-if="organization?.parsing_status === 'pending' || organization?.parsing_status === 'processing'" class="mt-4 rounded-md bg-blue-50 p-3 text-sm text-blue-700" role="status">
                    Данные обновляются…
                </div>
                <div v-else-if="organization?.parsing_status === 'completed'" class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-700" role="status">
                    Данные загружены.
                </div>
                <div v-else-if="organization?.parsing_status === 'failed'" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700" role="alert">
                    {{ organization.parsing_error || 'Не удалось загрузить данные организации.' }}
                </div>

                <div v-if="organization?.name" class="mt-5 rounded-md border border-slate-200 p-4">
                    <div class="font-medium text-slate-900">{{ organization.name }}</div>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm text-slate-600">
                        <span v-if="organization.average_rating !== null">Рейтинг: {{ organization.average_rating }}</span>
                        <span v-if="organization.ratings_count !== null">Оценок: {{ organization.ratings_count }}</span>
                        <span v-if="organization.reviews_count !== null">Отзывов: {{ organization.reviews_count }}</span>
                    </div>
                </div>

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

            <ReviewsList
                v-if="organization?.parsing_status === 'completed'"
                :key="organization.parsed_at"
            />
        </div>
    </main>
</template>
