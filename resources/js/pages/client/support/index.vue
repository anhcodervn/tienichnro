<script setup lang="ts">
import { supportService } from '@/services/support.service';
import { useSupportStore } from '@/stores/support.store';
import { useUserStore } from '@/stores/user.store';
import type { SupportMessage, SupportMessageCreatedEvent, SupportMessagesReadEvent } from '@/types/support.type';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const supportStore = useSupportStore();
const userStore = useUserStore();
const messageArea = ref<HTMLElement | null>(null);
const messageInput = ref<HTMLTextAreaElement | null>(null);
const messages = ref<SupportMessage[]>([]);
const conversationId = ref<number | null>(null);
const nextCursor = ref<string | null>(null);
const hasMore = ref(false);
const loading = ref(true);
const loadingOlder = ref(false);
const refreshing = ref(false);
const sending = ref(false);
const draft = ref('');
const errorMessage = ref('');
const showNewMessage = ref(false);
const cooldownRemaining = ref(0);
let cooldownTimer: number | null = null;

const sortedMessages = computed(() => [...messages.value].sort((left, right) => Number(left.id) - Number(right.id)));
const canSend = computed(() => draft.value.trim() !== '' && !sending.value && cooldownRemaining.value === 0);
const connectionLabel = computed(() => (supportStore.connected ? 'Đang hoạt động' : 'Đang kết nối...'));

const formatTime = (value: string | null): string => {
    if (!value) return 'Vừa xong';

    const date = new Date(value);
    const today = new Date();

    return date.toDateString() === today.toDateString()
        ? date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString('vi-VN', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          });
};

const readableError = (error: unknown): string => {
    if (!axios.isAxiosError(error)) return 'Không thể kết nối tới hệ thống hỗ trợ.';

    const payload = error.response?.data as { message?: string } | undefined;
    return payload?.message || 'Không thể kết nối tới hệ thống hỗ trợ.';
};

const mergeMessages = (incoming: SupportMessage[]): void => {
    const merged = new Map(messages.value.map((message) => [Number(message.id), message]));

    incoming.forEach((message) => {
        const current = merged.get(Number(message.id));
        merged.set(Number(message.id), current ? { ...current, ...message } : message);
    });

    messages.value = [...merged.values()];
};

const isNearBottom = (): boolean => {
    const area = messageArea.value;
    return !area || area.scrollHeight - area.scrollTop - area.clientHeight < 120;
};

const scrollToBottom = async (behavior: ScrollBehavior = 'smooth'): Promise<void> => {
    await nextTick();
    const area = messageArea.value;
    area?.scrollTo({ top: area.scrollHeight, behavior });
    showNewMessage.value = false;
};

const markRead = async (): Promise<void> => {
    if (!conversationId.value || document.visibilityState === 'hidden') return;

    try {
        const result = await supportService.clientMarkRead();
        const readAt = new Date().toISOString();
        const readIds = new Set(result.message_ids.map(Number));

        messages.value = messages.value.map((message) => (readIds.has(Number(message.id)) ? { ...message, read_at: readAt } : message));
        supportStore.applyStats(result.stats);
    } catch {
        // Read receipts are retried on focus and the next incoming message.
    }
};

const loadThread = async (cursor: string | null = null): Promise<void> => {
    const older = cursor !== null;
    const area = messageArea.value;
    const previousHeight = area?.scrollHeight ?? 0;

    if (older) loadingOlder.value = true;
    else if (messages.value.length === 0) loading.value = true;
    else refreshing.value = true;
    errorMessage.value = '';

    try {
        const result = await supportService.clientThread(cursor);
        conversationId.value = result.conversation?.id ?? conversationId.value;
        mergeMessages(result.messages);
        nextCursor.value = result.meta.next_cursor;
        hasMore.value = result.meta.has_more && nextCursor.value !== null;
        supportStore.applyStats(result.stats);

        await nextTick();
        if (older && area) {
            area.scrollTop += area.scrollHeight - previousHeight;
        } else {
            await scrollToBottom('auto');
            await markRead();
        }
    } catch (error) {
        errorMessage.value = readableError(error);
    } finally {
        loading.value = false;
        loadingOlder.value = false;
        refreshing.value = false;
    }
};

const startCooldown = (seconds = 10): void => {
    cooldownRemaining.value = Math.max(1, Math.ceil(seconds));
    if (cooldownTimer !== null) window.clearInterval(cooldownTimer);

    cooldownTimer = window.setInterval(() => {
        cooldownRemaining.value = Math.max(0, cooldownRemaining.value - 1);
        if (cooldownRemaining.value > 0 || cooldownTimer === null) return;

        window.clearInterval(cooldownTimer);
        cooldownTimer = null;
    }, 1000);
};

const sendMessage = async (): Promise<void> => {
    const content = draft.value.trim();
    if (!content || !canSend.value) return;

    sending.value = true;
    errorMessage.value = '';

    try {
        const result = await supportService.clientSend(content);
        conversationId.value = result.conversation.id;
        mergeMessages([result.message]);
        supportStore.applyStats(result.stats);
        draft.value = '';
        if (messageInput.value) messageInput.value.style.height = 'auto';
        startCooldown();
        await scrollToBottom();
    } catch (error) {
        errorMessage.value = readableError(error);

        if (axios.isAxiosError(error) && error.response?.status === 429) {
            startCooldown(Number(error.response.headers['retry-after'] ?? 10));
        }
    } finally {
        sending.value = false;
        messageInput.value?.focus();
    }
};

const resizeComposer = (): void => {
    const input = messageInput.value;
    if (!input) return;

    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
};

const handleComposerKeydown = (event: KeyboardEvent): void => {
    if (event.key !== 'Enter' || event.shiftKey) return;

    event.preventDefault();
    void sendMessage();
};

const handleScroll = (): void => {
    if (isNearBottom()) showNewMessage.value = false;
};

const handleRealtimeMessage = (event: Event): void => {
    const payload = (event as CustomEvent<SupportMessageCreatedEvent>).detail;
    const incomingConversationId = Number(payload.conversation?.id || payload.message.conversation_id);
    if (conversationId.value && incomingConversationId !== conversationId.value) return;

    const shouldFollow = isNearBottom();
    conversationId.value = incomingConversationId;
    mergeMessages([payload.message]);

    if (payload.message.sender_role === 'admin') void markRead();
    if (shouldFollow) void scrollToBottom();
    else showNewMessage.value = true;
};

const handleReadReceipt = (event: Event): void => {
    const payload = (event as CustomEvent<SupportMessagesReadEvent>).detail;
    if (conversationId.value && Number(payload.conversation_id) !== conversationId.value) return;

    const readIds = new Set(payload.message_ids.map(Number));
    messages.value = messages.value.map((message) => (readIds.has(Number(message.id)) ? { ...message, read_at: payload.read_at } : message));
};

const handleVisibility = (): void => {
    if (document.visibilityState === 'visible') void markRead();
};

onMounted(async () => {
    window.addEventListener('support:message-created', handleRealtimeMessage);
    window.addEventListener('support:messages-read', handleReadReceipt);
    document.addEventListener('visibilitychange', handleVisibility);

    const user = userStore.user ?? (await userStore.bootstrap({ silent: true }));
    if (user) await supportStore.start('client', Number(user.id));
    else errorMessage.value = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';

    await loadThread();
});

onBeforeUnmount(() => {
    supportStore.stop();
    window.removeEventListener('support:message-created', handleRealtimeMessage);
    window.removeEventListener('support:messages-read', handleReadReceipt);
    document.removeEventListener('visibilitychange', handleVisibility);
    if (cooldownTimer !== null) window.clearInterval(cooldownTimer);
});
</script>

<template>
    <main class="flex h-[100dvh] min-h-0 flex-col overflow-hidden bg-slate-100" data-support-chat-app>
        <header class="shrink-0 border-b border-slate-200 bg-white pt-[env(safe-area-inset-top)] shadow-sm">
            <div class="mx-auto flex min-h-16 w-full max-w-3xl items-center gap-3 px-3 sm:px-5">
                <a
                    href="/"
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-2xl text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                    aria-label="Trở lại trang chủ"
                    data-support-back-home
                >
                    <i class="bx bx-arrow-left" aria-hidden="true" />
                </a>

                <span class="relative grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-600 text-xl text-white shadow-sm">
                    <i class="bx bx-headphone-mic" aria-hidden="true" />
                    <span
                        class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white"
                        :class="supportStore.connected ? 'bg-emerald-400' : 'bg-amber-400'"
                        aria-hidden="true"
                    />
                </span>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-extrabold text-slate-950 sm:text-base">Hỗ trợ trực tuyến</h1>
                    <p class="flex items-center gap-1.5 truncate text-[11px] font-medium text-slate-500">
                        <span
                            class="h-1.5 w-1.5 shrink-0 rounded-full"
                            :class="supportStore.connected ? 'bg-emerald-500' : 'animate-pulse bg-amber-400'"
                            aria-hidden="true"
                        />
                        {{ connectionLabel }}
                    </p>
                </div>

                <button
                    type="button"
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-xl text-slate-500 transition hover:bg-slate-100 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 disabled:cursor-wait disabled:opacity-50"
                    :disabled="refreshing"
                    aria-label="Tải lại tin nhắn"
                    data-support-refresh
                    @click="loadThread()"
                >
                    <i class="bx bx-refresh-cw" :class="{ 'animate-spin': refreshing }" aria-hidden="true" />
                </button>
            </div>
        </header>

        <section
            ref="messageArea"
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-[linear-gradient(180deg,#f8fafc_0%,#eefbf5_100%)] px-3 py-4 sm:px-5"
            aria-live="polite"
            :aria-busy="loading"
            data-support-message-area
            @scroll="handleScroll"
        >
            <div class="mx-auto grid w-full max-w-3xl gap-3">
                <div v-if="hasMore" class="flex justify-center pb-1">
                    <button
                        type="button"
                        class="inline-flex min-h-9 items-center gap-2 rounded-full border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-wait disabled:opacity-60"
                        :disabled="loadingOlder"
                        data-support-load-older
                        @click="loadThread(nextCursor)"
                    >
                        <i class="bx bx-history text-base" aria-hidden="true" />
                        {{ loadingOlder ? 'Đang tải...' : 'Tải tin nhắn cũ' }}
                    </button>
                </div>

                <div v-if="loading" class="grid min-h-[60vh] place-items-center text-center" data-support-loading>
                    <div>
                        <i class="bx bx-loader-lines animate-spin text-3xl text-emerald-600" aria-hidden="true" />
                        <p class="pt-2 text-sm font-semibold text-slate-500">Đang tải cuộc trò chuyện...</p>
                    </div>
                </div>

                <div v-else-if="sortedMessages.length === 0" class="grid min-h-[60vh] place-items-center px-6 text-center" data-support-empty>
                    <div>
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-700">
                            <i class="bx bx-message-circle-dots" aria-hidden="true" />
                        </span>
                        <h2 class="pt-4 font-extrabold text-slate-900">Bắt đầu cuộc trò chuyện</h2>
                        <p class="mx-auto max-w-sm pt-1 text-sm leading-6 text-slate-500">
                            Nhập vấn đề cần hỗ trợ. Nhân viên sẽ phản hồi trực tiếp tại đây.
                        </p>
                    </div>
                </div>

                <article
                    v-for="message in sortedMessages"
                    :key="message.id"
                    class="flex"
                    :class="message.sender_role === 'user' ? 'justify-end' : 'justify-start'"
                    :data-support-message-id="message.id"
                >
                    <div class="max-w-[84%] sm:max-w-[72%]">
                        <p
                            class="whitespace-pre-wrap break-words px-3.5 py-2.5 text-sm leading-6 shadow-sm"
                            :class="
                                message.sender_role === 'user'
                                    ? 'rounded-2xl rounded-br-[5px] bg-emerald-600 text-white'
                                    : 'rounded-2xl rounded-bl-[5px] border border-slate-200 bg-white text-slate-800'
                            "
                        >
                            {{ message.message }}
                        </p>
                        <div
                            class="flex items-center gap-2 px-1 pt-1 text-[10px] text-slate-400"
                            :class="message.sender_role === 'user' ? 'justify-end' : 'justify-start'"
                        >
                            <span>{{ message.sender_role === 'user' ? (message.read_at ? 'Đã xem' : 'Đã gửi') : 'Nhân viên hỗ trợ' }}</span>
                            <time :datetime="message.created_at || ''">{{ formatTime(message.created_at) }}</time>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <button
            v-if="showNewMessage"
            type="button"
            class="fixed bottom-24 left-1/2 z-10 inline-flex -translate-x-1/2 items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-xs font-bold text-white shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
            data-support-new-message
            @click="scrollToBottom()"
        >
            <i class="bx bx-arrow-down text-base" aria-hidden="true" />
            Tin nhắn mới
        </button>

        <footer class="shrink-0 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]">
            <div class="mx-auto w-full max-w-3xl px-3 py-2.5 sm:px-5 sm:py-3">
                <p v-if="errorMessage" class="pb-2 text-sm font-semibold text-rose-600" role="alert" data-support-error>
                    {{ errorMessage }}
                </p>
                <form class="flex min-w-0 items-end gap-2" data-support-form @submit.prevent="sendMessage">
                    <label class="sr-only" for="support-message">Nội dung tin nhắn</label>
                    <textarea
                        id="support-message"
                        ref="messageInput"
                        v-model="draft"
                        class="max-h-[120px] min-h-12 min-w-0 flex-1 resize-none rounded-3xl border border-slate-300 bg-slate-50 px-4 py-3 text-base leading-6 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100"
                        rows="1"
                        maxlength="5000"
                        enterkeyhint="send"
                        placeholder="Nhập tin nhắn..."
                        data-support-input
                        @input="resizeComposer"
                        @keydown="handleComposerKeydown"
                    />
                    <button
                        type="submit"
                        class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-emerald-600 text-xl text-white shadow-sm transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="!canSend"
                        :aria-label="cooldownRemaining > 0 ? `Có thể gửi tiếp sau ${cooldownRemaining} giây` : 'Gửi tin nhắn'"
                        data-support-send
                    >
                        <i v-if="sending" class="bx bx-loader-lines animate-spin" aria-hidden="true" />
                        <span v-else-if="cooldownRemaining > 0" class="text-xs font-extrabold tabular-nums">{{ cooldownRemaining }}</span>
                        <i v-else class="bx bx-send" aria-hidden="true" />
                    </button>
                </form>
                <div class="flex items-center justify-between gap-3 px-2 pt-1.5 text-[10px] text-slate-400">
                    <span>Enter để gửi · Shift + Enter để xuống dòng</span>
                    <span class="tabular-nums">{{ draft.length }}/5000</span>
                </div>
            </div>
        </footer>
    </main>
</template>
