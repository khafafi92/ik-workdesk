import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const config = window.ikGlobalChatRealtime;
let echoClient = null;
let subscribedRoom = null;
let subscribedComponentId = null;
let activeRoomKey = null;
let lastMessageId = null;
let lastConnectionStatus = null;
let connectionStatusHandler = null;
let connectionStatusSource = null;

function getEchoClient() {
    if (!config?.enabled) {
        return null;
    }

    if (!echoClient) {
        window.Pusher = Pusher;
        echoClient = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host,
            wsPort: Number(config.port),
            wssPort: Number(config.port),
            forceTLS: config.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
        window.Echo = echoClient;
    }

    return echoClient;
}

function activeChatContext() {
    const room = document.querySelector('[data-global-chat-room]');
    const componentRoot = room?.closest('[wire\\:id]');
    const componentId = componentRoot?.getAttribute('wire:id');

    return {
        room,
        companyId: room?.getAttribute('data-company-id'),
        componentId,
        component: componentId && window.Livewire
            ? window.Livewire.find(componentId)
            : null,
    };
}

function bindConnectionStatus(echo) {
    const connection = echo?.connector?.pusher?.connection;

    if (!connection) {
        return;
    }

    if (connectionStatusSource !== connection) {
        connectionStatusSource?.unbind('state_change', connectionStatusHandler);
        connectionStatusSource = connection;
        connectionStatusHandler = ({ current }) => {
            const { component, componentId } = activeChatContext();

            if (!component || lastConnectionStatus?.componentId !== componentId) {
                return;
            }

            const isConnected = current === 'connected';

            if (lastConnectionStatus.connected !== isConnected) {
                lastConnectionStatus.connected = isConnected;
                component.$set('realtimeConnected', isConnected);
            }
        };

        connection.bind('state_change', connectionStatusHandler);
    }

    const { component, componentId } = activeChatContext();

    if (!component) {
        return;
    }

    if (lastConnectionStatus?.componentId !== componentId) {
        lastConnectionStatus = { componentId, connected: null };
    }

    const isConnected = connection.state === 'connected';

    if (lastConnectionStatus.connected !== isConnected) {
        lastConnectionStatus.connected = isConnected;
        component.$set('realtimeConnected', isConnected);
    }
}

function bindSelectedRoom() {
    const { room, companyId, component, componentId } = activeChatContext();
    const echo = room && companyId && component
        ? getEchoClient()
        : null;

    if (!room || !companyId || !component || !echo) {
        if (subscribedRoom !== null && window.Echo) {
            window.Echo.leave(`global-chat.company.${subscribedRoom}`);
            subscribedRoom = null;
            subscribedComponentId = null;
        }

        if (!room && echoClient) {
            echoClient.disconnect();
            echoClient = null;
            window.Echo = undefined;
            activeRoomKey = null;
            lastMessageId = null;
            connectionStatusSource = null;
            connectionStatusHandler = null;
            lastConnectionStatus = null;
        }

        return;
    }

    const roomKey = `${componentId}:${companyId}`;
    const latestMessageId = room.getAttribute('data-last-message-id');

    if (activeRoomKey !== roomKey) {
        activeRoomKey = roomKey;
        lastMessageId = latestMessageId;
        scrollToLatestMessage();
    } else if (lastMessageId !== latestMessageId) {
        lastMessageId = latestMessageId;
        scrollToLatestMessage();
    }

    if (subscribedRoom !== companyId || subscribedComponentId !== componentId) {
        if (subscribedRoom !== null) {
            echo.leave(`global-chat.company.${subscribedRoom}`);
        }

        echo
            .private(`global-chat.company.${companyId}`)
            .listen('.global-chat.message-created', () => component.$refresh());

        subscribedRoom = companyId;
        subscribedComponentId = componentId;
    }

    bindConnectionStatus(echo);
}

function scrollToLatestMessage() {
    const messages = document.querySelector('.ik-global-chat-messages');

    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }
}

function registerGlobalChatListeners() {
    if (!window.Livewire || window.ikGlobalChatListenersRegistered) {
        return;
    }

    window.ikGlobalChatListenersRegistered = true;
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => window.setTimeout(bindSelectedRoom, 0));
    });
    document.addEventListener('livewire:navigated', bindSelectedRoom);
    bindSelectedRoom();
}

if (window.Livewire) {
    registerGlobalChatListeners();
} else {
    document.addEventListener('livewire:init', registerGlobalChatListeners, { once: true });
}

document.addEventListener('DOMContentLoaded', registerGlobalChatListeners, { once: true });
