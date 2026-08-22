<script setup lang="ts">
import { supportService } from '@/services/support.service';
import { useSupportStore } from '@/stores/support.store';
import { useUserStore } from '@/stores/user.store';
import type {
    SupportConversation,
    SupportConversationUpdatedEvent,
    SupportMessage,
    SupportMessageCreatedEvent,
    SupportMessagesReadEvent,
    SupportUserSearchItem,
} from '@/types/support.type';
import { handleErrorResponse } from '@/utils/response';
import { ArrowDown, ArrowLeft, CheckCheck, LoaderCircle, MessageSquarePlus, RefreshCw, Search, Send, UserRound, X } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const supportStore = useSupportStore();
const conversations = ref<SupportConversation[]>([]);
const selectedConversation = ref<SupportConversation | null>(null);
const messages = ref<SupportMessage[]>([]);
const listSearch = ref('');
const messageText = ref('');
const loadingConversations = ref(true);
const loadingMessages = ref(false);
const loadingOlder = ref(false);
const sending = ref(false);
const nextCursor = ref<string | null>(null);
const hasMore = ref(false);
const showNewMessageButton = ref(false);
const mobileShowingConversation = ref(false);
const messageArea = ref<HTMLElement | null>(null);
const composer = ref<HTMLTextAreaElement | null>(null);
const showNewConversation = ref(false);
const userSearch = ref('');
const userResults = ref<SupportUserSearchItem[]>([]);
const selectedUser = ref<SupportUserSearchItem | null>(null);
const newMessageText = ref('');
const searchingUsers = ref(false);
const startingConversation = ref(false);
let conversationSearchTimer: ReturnType<typeof setTimeout> | null = null;
let userSearchTimer: ReturnType<typeof setTimeout> | null = null;

const formatTime = (value: string | null): string => {
    if (!value) {
        return '--';
    }

    const date = new Date(value);
    const today = new Date();
    return date.toDateString() === today.toDateString()
        ? date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
};

const isNearBottom = (): boolean => {
    const element = messageArea.value;
    return !element || element.scrollHeight - element.scrollTop - element.clientHeight < 120;
};

const scrollToBottom = async (behavior: ScrollBehavior = 'smooth'): Promise<void> => {
    await nextTick();
    const element = messageArea.value;
    element?.scrollTo({ top: element.scrollHeight, behavior });
    showNewMessageButton.value = false;
};

const sortConversations = (): void => {
    conversations.value.sort((left, right) => {
        const leftTime = left.last_message_at ? new Date(left.last_message_at).getTime() : 0;
        const rightTime = right.last_message_at ? new Date(right.last_message_at).getTime() : 0;
        return rightTime - leftTime || right.id - left.id;
    });
};

const upsertConversation = (incoming: SupportConversation): void => {
    const index = conversations.value.findIndex((conversation) => conversation.id === incoming.id);

    if (index >= 0) {
        const current = conversations.value[index];
        conversations.value[index] = {
            ...current,
            ...incoming,
            user: { ...current.user, ...incoming.user, email: incoming.user.email ?? current.user.email },
        };
    } else {
        conversations.value.push(incoming);
    }

    sortConversations();
};

const mergeMessage = (incoming: SupportMessage): void => {
    const index = messages.value.findIndex((message) => message.id === incoming.id);

    if (index >= 0) {
        messages.value[index] = { ...messages.value[index], ...incoming, status: 'sent' };
        return;
    }

    messages.value.push({ ...incoming, status: 'sent' });
};

const loadConversations = async (): Promise<void> => {
    try {
        loadingConversations.value = true;
        const response = await supportService.adminConversations({ search: listSearch.value || undefined, per_page: 50 });
        conversations.value = response.conversations;
        supportStore.applyStats(response.stats);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loadingConversations.value = false;
    }
};

const markSelectedRead = async (): Promise<void> => {
    if (!selectedConversation.value) {
        return;
    }

    try {
        const response = await supportService.adminMarkRead(selectedConversation.value.id);
        supportStore.applyStats(response.stats);
        selectedConversation.value.unread_count = 0;
        const conversation = conversations.value.find((item) => item.id === selectedConversation.value?.id);
        if (conversation) {
            conversation.unread_count = 0;
        }
    } catch (error) {
        handleErrorResponse(error);
    }
};

const openConversation = async (conversationId: number, updateUrl = true): Promise<void> => {
    try {
        loadingMessages.value = true;
        selectedConversation.value = conversations.value.find((conversation) => conversation.id === conversationId) ?? null;
        mobileShowingConversation.value = true;
        messages.value = [];
        nextCursor.value = null;
        hasMore.value = false;
        const response = await supportService.adminThread(conversationId);
        selectedConversation.value = response.conversation;
        if (response.conversation) {
            upsertConversation(response.conversation);
        }
        messages.value = response.messages.map((message) => ({ ...message, status: 'sent' }));
        nextCursor.value = response.meta.next_cursor;
        hasMore.value = response.meta.has_more;
        supportStore.applyStats(response.stats);
        await scrollToBottom('auto');
        await markSelectedRead();
        await nextTick();
        composer.value?.focus();

        if (updateUrl) {
            await router.replace({ query: { ...route.query, conversation: String(conversationId) } });
        }
    } catch (error) {
        selectedConversation.value = null;
        handleErrorResponse(error);
    } finally {
        loadingMessages.value = false;
    }
};

const loadOlder = async (): Promise<void> => {
    const element = messageArea.value;
    const conversationId = selectedConversation.value?.id;

    if (!element || !conversationId || loadingOlder.value || !hasMore.value || !nextCursor.value) {
        return;
    }

    try {
        loadingOlder.value = true;
        const previousHeight = element.scrollHeight;
        const response = await supportService.adminThread(conversationId, nextCursor.value);
        const knownIds = new Set(messages.value.map((message) => message.id));
        messages.value = [...response.messages.filter((message) => !knownIds.has(message.id)), ...messages.value];
        nextCursor.value = response.meta.next_cursor;
        hasMore.value = response.meta.has_more;
        await nextTick();
        element.scrollTop += element.scrollHeight - previousHeight;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loadingOlder.value = false;
    }
};

const handleMessageScroll = (): void => {
    if (messageArea.value && messageArea.value.scrollTop <= 24) {
        void loadOlder();
    }

    if (isNearBottom()) {
        showNewMessageButton.value = false;
    }
};

const sendReply = async (retryMessage?: SupportMessage): Promise<void> => {
    const content = (retryMessage?.message ?? messageText.value).trim();
    const conversationId = selectedConversation.value?.id;

    if (!conversationId || content === '' || sending.value) {
        return;
    }

    const temporaryId = `pending-${Date.now()}`;
    const pendingMessage: SupportMessage = {
        id: temporaryId,
        conversation_id: conversationId,
        sender_id: userStore.user?.id ?? 0,
        sender_role: 'admin',
        message: content,
        read_at: null,
        created_at: new Date().toISOString(),
        status: 'sending',
    };

    if (retryMessage) {
        messages.value = messages.value.filter((message) => message.id !== retryMessage.id);
    } else {
        messageText.value = '';
    }

    messages.value.push(pendingMessage);
    await scrollToBottom();

    try {
        sending.value = true;
        const response = await supportService.adminReply(conversationId, content);
        messages.value = messages.value.filter((message) => message.id !== temporaryId);
        mergeMessage(response.message);
        selectedConversation.value = response.conversation;
        upsertConversation(response.conversation);
        supportStore.applyStats(response.stats);
        await scrollToBottom();
    } catch (error) {
        const failedMessage = messages.value.find((message) => message.id === temporaryId);
        if (failedMessage) {
            failedMessage.status = 'failed';
        }
        handleErrorResponse(error);
    } finally {
        sending.value = false;
        await nextTick();
        composer.value?.focus();
    }
};

const handleComposerKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        void sendReply();
    }
};

const searchUsers = async (): Promise<void> => {
    const keyword = userSearch.value.trim();

    if (keyword.length < 2 && !/^\d+$/.test(keyword)) {
        userResults.value = [];
        return;
    }

    try {
        searchingUsers.value = true;
        userResults.value = await supportService.adminUsers(keyword);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        searchingUsers.value = false;
    }
};

const startConversation = async (): Promise<void> => {
    const content = newMessageText.value.trim();

    if (!selectedUser.value || content === '' || startingConversation.value) {
        return;
    }

    try {
        startingConversation.value = true;
        const response = await supportService.adminStart(selectedUser.value.id, content);
        upsertConversation(response.conversation);
        selectedConversation.value = response.conversation;
        messages.value = [{ ...response.message, status: 'sent' }];
        supportStore.applyStats(response.stats);
        showNewConversation.value = false;
        selectedUser.value = null;
        userSearch.value = '';
        newMessageText.value = '';
        mobileShowingConversation.value = true;
        await router.replace({ query: { ...route.query, conversation: String(response.conversation.id) } });
        await scrollToBottom('auto');
        composer.value?.focus();
        void openConversation(response.conversation.id, false);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        startingConversation.value = false;
    }
};

const handleRealtimeMessage = (event: Event): void => {
    const payload = (event as CustomEvent<SupportMessageCreatedEvent>).detail;
    upsertConversation(payload.conversation);

    if (selectedConversation.value?.id !== payload.conversation.id) {
        return;
    }

    const shouldFollow = isNearBottom();
    selectedConversation.value = { ...selectedConversation.value, ...payload.conversation };
    mergeMessage(payload.message);

    if (payload.message.sender_role === 'user') {
        void markSelectedRead();
    }

    if (shouldFollow) {
        void scrollToBottom();
    } else {
        showNewMessageButton.value = true;
    }
};

const handleConversationUpdated = (event: Event): void => {
    const payload = (event as CustomEvent<SupportConversationUpdatedEvent>).detail;
    upsertConversation(payload.conversation);
};

const handleReadReceipt = (event: Event): void => {
    const payload = (event as CustomEvent<SupportMessagesReadEvent>).detail;

    if (selectedConversation.value?.id !== payload.conversation_id) {
        return;
    }

    const readIds = new Set(payload.message_ids);
    messages.value = messages.value.map((message) => (readIds.has(Number(message.id)) ? { ...message, read_at: payload.read_at } : message));

    if (payload.reader_role === 'admin') {
        selectedConversation.value.unread_count = 0;
        const conversation = conversations.value.find((item) => item.id === payload.conversation_id);
        if (conversation) {
            conversation.unread_count = 0;
        }
    }
};

const backToList = async (): Promise<void> => {
    mobileShowingConversation.value = false;
    await router.replace({ query: { ...route.query, conversation: undefined } });
};

watch(listSearch, () => {
    if (conversationSearchTimer) {
        clearTimeout(conversationSearchTimer);
    }
    conversationSearchTimer = setTimeout(() => void loadConversations(), 300);
});

watch(userSearch, () => {
    if (userSearchTimer) {
        clearTimeout(userSearchTimer);
    }
    userSearchTimer = setTimeout(() => void searchUsers(), 300);
});

onMounted(async () => {
    window.addEventListener('support:message-created', handleRealtimeMessage);
    window.addEventListener('support:messages-read', handleReadReceipt);
    window.addEventListener('support:conversation-updated', handleConversationUpdated);
    await loadConversations();

    const deepLinkedConversation = Number(route.query.conversation);
    if (Number.isInteger(deepLinkedConversation) && deepLinkedConversation > 0) {
        await openConversation(deepLinkedConversation, false);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('support:message-created', handleRealtimeMessage);
    window.removeEventListener('support:messages-read', handleReadReceipt);
    window.removeEventListener('support:conversation-updated', handleConversationUpdated);
    if (conversationSearchTimer) clearTimeout(conversationSearchTimer);
    if (userSearchTimer) clearTimeout(userSearchTimer);
});
</script>

<template>
    <section
        class="grid h-[calc(100dvh-7rem)] min-h-[560px] overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-[0_18px_55px_-34px_rgba(15,23,42,0.3)] lg:grid-cols-[360px_minmax(0,1fr)]"
    >
        <aside class="min-h-0 border-r border-slate-200 bg-slate-50/70" :class="mobileShowingConversation ? 'hidden lg:flex' : 'flex'">
            <div class="flex min-h-0 w-full flex-col">
                <div class="shrink-0 border-b border-slate-200 bg-white p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h1 class="text-base font-black text-slate-950">Tin nhắn hỗ trợ</h1>
                            <p class="mt-0.5 text-xs text-slate-500">{{ supportStore.adminUnread }} tin chưa đọc</p>
                        </div>
                        <button
                            type="button"
                            class="ui-focus inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-blue-600 px-3 text-xs font-semibold text-white transition hover:bg-blue-700"
                            @click="showNewConversation = true"
                        >
                            <MessageSquarePlus class="h-4 w-4" /> Tin mới
                        </button>
                    </div>
                    <label
                        class="mt-3 flex min-h-11 items-center gap-2 rounded-[10px] border border-slate-200 bg-slate-50 px-3 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100"
                    >
                        <Search class="h-4 w-4 text-slate-400" />
                        <span class="sr-only">Tìm cuộc trò chuyện</span>
                        <input
                            v-model="listSearch"
                            type="search"
                            placeholder="Tên, username, email hoặc ID"
                            class="w-full border-0 bg-transparent p-0 text-sm outline-none focus:ring-0"
                        />
                    </label>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-2">
                    <div v-if="loadingConversations" class="flex items-center justify-center gap-2 py-12 text-sm text-slate-500">
                        <LoaderCircle class="h-5 w-5 animate-spin" /> Đang tải...
                    </div>
                    <div v-else-if="conversations.length === 0" class="px-5 py-12 text-center text-sm text-slate-500">
                        Chưa có cuộc trò chuyện phù hợp.
                    </div>
                    <template v-else>
                        <button
                            v-for="item in conversations"
                            :key="item.id"
                            type="button"
                            class="ui-focus mb-1 flex min-h-[76px] w-full items-start gap-3 rounded-[12px] p-3 text-left transition duration-200"
                            :class="
                                selectedConversation?.id === item.id
                                    ? 'bg-blue-600 text-white shadow-sm'
                                    : item.unread_count > 0
                                      ? 'bg-blue-50 text-slate-900'
                                      : 'hover:bg-white'
                            "
                            @click="openConversation(item.id)"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/90 text-sm font-black text-blue-700 ring-1 ring-slate-200"
                            >
                                {{ item.user.name.trim().charAt(0).toUpperCase() || 'U' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-bold">{{ item.user.name }}</p>
                                    <span
                                        class="shrink-0 text-[10px]"
                                        :class="selectedConversation?.id === item.id ? 'text-blue-100' : 'text-slate-400'"
                                        >{{ formatTime(item.last_message_at) }}</span
                                    >
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <p
                                        class="line-clamp-1 flex-1 text-xs"
                                        :class="
                                            selectedConversation?.id === item.id
                                                ? 'text-blue-100'
                                                : item.unread_count > 0
                                                  ? 'font-semibold text-slate-700'
                                                  : 'text-slate-500'
                                        "
                                    >
                                        {{ item.last_message?.message || 'Chưa có tin nhắn' }}
                                    </p>
                                    <span
                                        v-if="item.unread_count > 0"
                                        class="inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-black text-white"
                                        >{{ item.unread_count > 99 ? '99+' : item.unread_count }}</span
                                    >
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </aside>

        <div class="min-h-0" :class="mobileShowingConversation ? 'flex' : 'hidden lg:flex'">
            <div v-if="!selectedConversation" class="flex w-full flex-col items-center justify-center bg-slate-50/40 px-6 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 text-blue-700">
                    <MessageSquarePlus class="h-8 w-8" />
                </div>
                <h2 class="mt-4 text-lg font-black text-slate-950">Chọn một cuộc trò chuyện</h2>
                <p class="mt-2 max-w-sm text-sm leading-6 text-slate-500">Đọc và trả lời người dùng mà không cần rời khỏi màn hình này.</p>
            </div>

            <div v-else class="flex min-h-0 w-full flex-col">
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 py-3 sm:px-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="ui-focus inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] text-slate-600 hover:bg-slate-100 lg:hidden"
                            aria-label="Quay lại danh sách"
                            @click="backToList"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </button>
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-700">
                            <UserRound class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="truncate text-sm font-bold text-slate-950">{{ selectedConversation.user.name }}</h2>
                            <p class="truncate text-xs text-slate-500">
                                @{{ selectedConversation.user.username }} · ID {{ selectedConversation.user.id }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-blue-700">
                        <span class="h-2 w-2 rounded-full" :class="supportStore.connected ? 'bg-blue-500' : 'bg-slate-400'" />
                        <span class="hidden sm:inline">{{ supportStore.connected ? 'Realtime' : 'Đang kết nối' }}</span>
                        <button
                            type="button"
                            class="ui-focus ml-2 inline-flex h-11 w-11 items-center justify-center rounded-[10px] text-slate-500 hover:bg-slate-100"
                            aria-label="Tải lại cuộc trò chuyện"
                            @click="openConversation(selectedConversation.id, false)"
                        >
                            <RefreshCw class="h-4 w-4" :class="loadingMessages ? 'animate-spin' : ''" />
                        </button>
                    </div>
                </header>

                <div class="relative min-h-0 flex-1 bg-slate-50/60">
                    <div ref="messageArea" class="h-full overflow-y-auto px-3 py-4 sm:px-5" aria-live="polite" @scroll.passive="handleMessageScroll">
                        <div v-if="loadingOlder" class="flex items-center justify-center gap-2 py-2 text-xs text-slate-500">
                            <LoaderCircle class="h-4 w-4 animate-spin" /> Đang tải tin cũ...
                        </div>
                        <button
                            v-else-if="hasMore"
                            type="button"
                            class="ui-focus mx-auto mb-3 flex min-h-11 items-center rounded-[10px] px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50"
                            @click="loadOlder"
                        >
                            Xem tin nhắn cũ hơn
                        </button>

                        <div v-if="loadingMessages" class="flex h-full items-center justify-center gap-2 text-sm text-slate-500">
                            <LoaderCircle class="h-5 w-5 animate-spin text-blue-600" /> Đang tải cuộc trò chuyện...
                        </div>
                        <div v-else class="space-y-3">
                            <article
                                v-for="message in messages"
                                :key="message.id"
                                class="flex"
                                :class="message.sender_role === 'admin' ? 'justify-end' : 'justify-start'"
                            >
                                <div class="max-w-[88%] sm:max-w-[72%]">
                                    <div
                                        class="rounded-[14px] px-4 py-2.5 text-sm leading-6 shadow-sm"
                                        :class="
                                            message.sender_role === 'admin'
                                                ? 'rounded-br-sm bg-blue-600 text-white'
                                                : 'rounded-bl-sm border border-slate-200 bg-white text-slate-800'
                                        "
                                    >
                                        <p class="whitespace-pre-wrap break-words">{{ message.message }}</p>
                                    </div>
                                    <div
                                        class="mt-1 flex items-center gap-2 px-1 text-[11px] text-slate-400"
                                        :class="message.sender_role === 'admin' ? 'justify-end' : 'justify-start'"
                                    >
                                        <span>{{ formatTime(message.created_at) }}</span>
                                        <span v-if="message.status === 'sending'" class="inline-flex items-center gap-1"
                                            ><LoaderCircle class="h-3 w-3 animate-spin" /> Đang gửi</span
                                        >
                                        <button
                                            v-else-if="message.status === 'failed'"
                                            type="button"
                                            class="inline-flex min-h-11 items-center font-semibold text-red-600"
                                            @click="sendReply(message)"
                                        >
                                            Gửi thất bại · Thử lại
                                        </button>
                                        <span
                                            v-else-if="message.sender_role === 'admin' && message.read_at"
                                            class="inline-flex items-center gap-1 text-blue-600"
                                            ><CheckCheck class="h-3.5 w-3.5" /> Đã xem</span
                                        >
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <button
                        v-if="showNewMessageButton"
                        type="button"
                        class="ui-focus absolute bottom-3 left-1/2 inline-flex min-h-11 -translate-x-1/2 items-center gap-2 rounded-full bg-slate-950 px-4 text-xs font-semibold text-white shadow-lg transition hover:bg-blue-700"
                        @click="scrollToBottom()"
                    >
                        <ArrowDown class="h-4 w-4" /> Tin nhắn mới
                    </button>
                </div>

                <form class="shrink-0 border-t border-slate-200 bg-white p-3" @submit.prevent="sendReply()">
                    <label for="admin-support-message" class="sr-only">Nội dung phản hồi</label>
                    <div
                        class="flex items-end gap-2 rounded-[14px] border border-slate-200 bg-slate-50 p-2 transition focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100"
                    >
                        <textarea
                            id="admin-support-message"
                            ref="composer"
                            v-model="messageText"
                            rows="1"
                            maxlength="5000"
                            placeholder="Nhập phản hồi..."
                            class="max-h-32 min-h-11 flex-1 resize-none border-0 bg-transparent px-2 py-2.5 text-base outline-none focus:ring-0 sm:text-sm"
                            @keydown="handleComposerKeydown"
                        />
                        <button
                            type="submit"
                            class="ui-focus inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] bg-blue-600 text-white transition hover:bg-blue-700 disabled:opacity-50"
                            :disabled="sending || messageText.trim() === ''"
                            aria-label="Gửi phản hồi"
                        >
                            <Send class="h-5 w-5" />
                        </button>
                    </div>
                    <p class="mt-1.5 px-1 text-[11px] text-slate-400">Enter để gửi · Shift + Enter để xuống dòng</p>
                </form>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showNewConversation"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
                @click.self="showNewConversation = false"
            >
                <form class="w-full max-w-lg rounded-[16px] bg-white shadow-2xl" @submit.prevent="startConversation">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                        <div>
                            <h2 class="text-base font-black text-slate-950">Tin nhắn mới</h2>
                            <p class="mt-1 text-xs text-slate-500">Chọn người dùng để bắt đầu hoặc tiếp tục cuộc trò chuyện.</p>
                        </div>
                        <button
                            type="button"
                            class="ui-focus inline-flex h-11 w-11 items-center justify-center rounded-[10px] text-slate-500 hover:bg-slate-100"
                            aria-label="Đóng"
                            @click="showNewConversation = false"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="space-y-4 p-5">
                        <label class="block">
                            <span class="text-xs font-semibold text-slate-700">Tìm người dùng</span>
                            <div
                                class="mt-2 flex min-h-11 items-center gap-2 rounded-[10px] border border-slate-200 px-3 focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100"
                            >
                                <Search class="h-4 w-4 text-slate-400" />
                                <input
                                    v-model="userSearch"
                                    type="search"
                                    placeholder="Tên, username, email hoặc ID"
                                    class="w-full border-0 p-0 text-sm outline-none focus:ring-0"
                                />
                                <LoaderCircle v-if="searchingUsers" class="h-4 w-4 animate-spin text-blue-600" />
                            </div>
                        </label>

                        <div
                            v-if="!selectedUser && userResults.length"
                            class="max-h-48 space-y-1 overflow-y-auto rounded-[10px] border border-slate-200 p-1"
                        >
                            <button
                                v-for="user in userResults"
                                :key="user.id"
                                type="button"
                                class="flex min-h-11 w-full items-center gap-3 rounded-[8px] px-3 py-2 text-left hover:bg-blue-50"
                                @click="selectedUser = user"
                            >
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ user.name }}</p>
                                    <p class="truncate text-xs text-slate-500">@{{ user.username }} · {{ user.email }}</p>
                                </div>
                                <span v-if="user.conversation_id" class="text-[10px] font-semibold text-blue-700">Đã có chat</span>
                            </button>
                        </div>

                        <div v-if="selectedUser" class="flex items-center justify-between gap-3 rounded-[10px] border border-blue-200 bg-blue-50 p-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-blue-950">{{ selectedUser.name }}</p>
                                <p class="truncate text-xs text-blue-700">@{{ selectedUser.username }} · ID {{ selectedUser.id }}</p>
                            </div>
                            <button type="button" class="min-h-11 text-xs font-semibold text-blue-700" @click="selectedUser = null">Đổi user</button>
                        </div>

                        <label class="block">
                            <span class="text-xs font-semibold text-slate-700">Nội dung</span>
                            <textarea
                                v-model="newMessageText"
                                rows="4"
                                maxlength="5000"
                                class="mt-2 w-full resize-none rounded-[10px] border border-slate-200 px-3 py-3 text-base outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 sm:text-sm"
                                placeholder="Nhập nội dung cần gửi..."
                            />
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                        <button
                            type="button"
                            class="min-h-11 rounded-[10px] px-4 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                            @click="showNewConversation = false"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="!selectedUser || !newMessageText.trim() || startingConversation"
                        >
                            <LoaderCircle v-if="startingConversation" class="h-4 w-4 animate-spin" /><Send v-else class="h-4 w-4" /> Gửi tin nhắn
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </section>
</template>
