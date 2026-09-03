import { supportService } from '@/services/support.service';
import type {
    SupportConversationUpdatedEvent,
    SupportMessageCreatedEvent,
    SupportMessagesReadEvent,
    SupportSenderRole,
    SupportStats,
} from '@/types/support.type';
import { echo } from '@laravel/echo-vue';
import { defineStore } from 'pinia';

type SupportContext = 'client' | 'admin';

let activeChannelName: string | null = null;
let messageCreatedListener: ((event: SupportMessageCreatedEvent) => void) | null = null;
let messagesReadListener: ((event: SupportMessagesReadEvent) => void) | null = null;
let conversationUpdatedListener: ((event: SupportConversationUpdatedEvent) => void) | null = null;

const emitSupportEvent = <T>(name: string, detail: T): void => {
    window.dispatchEvent(new CustomEvent<T>(name, { detail }));
};

export const useSupportStore = defineStore('support', {
    state: () => ({
        context: null as SupportContext | null,
        userId: null as number | null,
        userUnread: 0,
        adminUnread: 0,
        connected: false,
    }),

    actions: {
        applyStats(stats: SupportStats): void {
            this.userUnread = stats.user_unread;
            this.adminUnread = stats.admin_unread;
        },

        async start(context: SupportContext, userId: number, tenantId?: number): Promise<void> {
            const expectedChannel = context === 'admin' ? `admin.sites.${tenantId}.support` : `users.${userId}.support`;

            if (this.context === context && this.userId === userId && activeChannelName === expectedChannel) {
                return;
            }

            this.stop();
            this.context = context;
            this.userId = userId;
            activeChannelName = expectedChannel;

            messageCreatedListener = (event: SupportMessageCreatedEvent): void => {
                this.applyStats(event.stats);
                emitSupportEvent('support:message-created', event);
            };
            messagesReadListener = (event: SupportMessagesReadEvent): void => {
                this.applyStats(event.stats);
                emitSupportEvent('support:messages-read', event);
            };
            conversationUpdatedListener = (event: SupportConversationUpdatedEvent): void => {
                this.applyStats(event.stats);
                emitSupportEvent('support:conversation-updated', event);
            };

            const channel = echo().private(expectedChannel);
            channel.listen('.support.message.created', messageCreatedListener);
            channel.listen('.support.messages.read', messagesReadListener);
            channel.listen('.support.conversation.updated', conversationUpdatedListener);
            this.connected = true;

            try {
                const stats = context === 'admin' ? await supportService.adminUnread() : await supportService.clientUnread();
                this.applyStats(stats);
            } catch {
                this.connected = false;
            }
        },

        stop(): void {
            if (activeChannelName !== null) {
                const channel = echo().private(activeChannelName);

                if (messageCreatedListener) {
                    channel.stopListening('.support.message.created', messageCreatedListener);
                }
                if (messagesReadListener) {
                    channel.stopListening('.support.messages.read', messagesReadListener);
                }
                if (conversationUpdatedListener) {
                    channel.stopListening('.support.conversation.updated', conversationUpdatedListener);
                }
            }

            activeChannelName = null;
            messageCreatedListener = null;
            messagesReadListener = null;
            conversationUpdatedListener = null;
            this.context = null;
            this.userId = null;
            this.connected = false;
        },

        setUnread(role: SupportSenderRole, count: number): void {
            if (role === 'admin') {
                this.adminUnread = count;
                return;
            }

            this.userUnread = count;
        },
    },
});
