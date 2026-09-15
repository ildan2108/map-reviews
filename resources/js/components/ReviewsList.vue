<script setup >
import { onMounted, ref } from 'vue';
import api from '../services/api';

const reviews = ref([]);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const isLoading = ref(false);
const error = ref('');

onMounted(() => {
    loadReviews();
});

async function loadReviews(page = 1) {
    isLoading.value = true;
    error.value = '';

    try {
        const response = await api.get('/api/organization/reviews', {
            params: {
                page,
            },
        });

        reviews.value = response.data.data;
        currentPage.value = response.data.meta.current_page;
        lastPage.value = response.data.meta.last_page;
        total.value = response.data.meta.total;
    } catch (requestError) {
        error.value = 'Не удалось загрузить отзывы.';
    } finally {
        isLoading.value = false;
    }
}

function goToPage(page) {
    if (
        page < 1
        || page > lastPage.value
        || page === currentPage.value
        || isLoading.value
    ) {
        return;
    }

    loadReviews(page);
}

function formatDate(date) {
    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(date));
}
</script>

<template>
    <section class="mt-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Отзывы</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Всего отзывов: {{ total }}
                </p>
            </div>
        </div>

        <div
            v-if="isLoading"
            class="mt-5 rounded-md bg-slate-50 p-6 text-center text-sm text-slate-600"
            role="status"
        >
            Загружаем отзывы…
        </div>

        <div
            v-else-if="error"
            class="mt-5 rounded-md bg-red-50 p-4 text-sm text-red-700"
            role="alert"
        >
            {{ error }}

            <button
                class="ml-2 font-medium underline"
                type="button"
                @click="loadReviews(currentPage)"
            >
                Повторить
            </button>
        </div>

        <div
            v-else-if="reviews.length === 0"
            class="mt-5 rounded-md border border-slate-200 p-6 text-center text-sm text-slate-500"
        >
            Отзывов пока нет.
        </div>

        <div v-else class="mt-5 space-y-4">
            <article
                v-for="review in reviews"
                :key="review.id"
                class="rounded-lg border border-slate-200 p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="font-medium text-slate-900">
                            {{ review.author_name || 'Пользователь' }}
                        </div>

                        <div class="mt-1 flex items-center gap-2 text-sm">
                            <span
                                v-if="review.rating !== null"
                                class="font-medium text-slate-700"
                            >
                                {{ review.rating }}/5
                            </span>

                            <span
                                v-if="review.published_at"
                                class="text-slate-500"
                            >
                                {{ formatDate(review.published_at) }}
                            </span>
                        </div>
                    </div>
                </div>

                <p
                    v-if="review.text"
                    class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700"
                >
                    {{ review.text }}
                </p>

                <div
                    v-if="review.organization_reply"
                    class="mt-4 rounded-md bg-slate-50 p-4"
                >
                    <div class="text-sm font-medium text-slate-800">
                        Ответ организации
                    </div>

                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">
                        {{ review.organization_reply }}
                    </p>

                    <div
                        v-if="review.organization_reply_at"
                        class="mt-2 text-xs text-slate-400"
                    >
                        {{ formatDate(review.organization_reply_at) }}
                    </div>
                </div>
            </article>
        </div>

        <nav
            v-if="lastPage > 1 && !error"
            class="mt-6 flex items-center justify-center gap-2"
            aria-label="Пагинация отзывов"
        >
            <button
                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                type="button"
                :disabled="currentPage === 1 || isLoading"
                @click="goToPage(currentPage - 1)"
            >
                Назад
            </button>

            <span class="px-3 text-sm text-slate-600">
                {{ currentPage }} из {{ lastPage }}
            </span>

            <button
                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                type="button"
                :disabled="currentPage === lastPage || isLoading"
                @click="goToPage(currentPage + 1)"
            >
                Далее
            </button>
        </nav>
    </section>
</template>
