<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    nav: { type: Array, default: () => [] },
    notifications: { type: Array, default: () => [] },
    unreadCount: { type: Number, default: 0 },
    markAllReadUrl: { type: String, default: '' },
    notificationsIndexUrl: { type: String, default: '' },
});

const open = ref(null); // 'search' | 'notifications' | null
const searchTerm = ref('');
const unread = ref(props.unreadCount);
const items = ref(props.notifications);

const filteredNav = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) return props.nav;
    return props.nav.filter(item => item.label.toLowerCase().includes(term));
});

function toggle(panel) {
    open.value = open.value === panel ? null : panel;
}

function closeAll() {
    open.value = null;
}

async function markAllRead() {
    if (!props.markAllReadUrl || unread.value === 0) return;
    try {
        await window.axios.post(props.markAllReadUrl);
        items.value = items.value.map(item => ({ ...item, read_at: item.read_at ?? new Date().toISOString() }));
        unread.value = 0;
    } catch {
        window.location.assign(props.markAllReadUrl);
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') closeAll();
}

onMounted(() => {
    document.addEventListener('click', closeAll);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', closeAll);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="dropdown-anchor" @click.stop>
        <button
            type="button" class="icon-button" aria-label="Search" aria-haspopup="true"
            :aria-expanded="open === 'search'" @click="toggle('search')"
        >
            <span class="fa fa-search search-icon"></span>
        </button>
        <div class="dropdown-menu dropdown-right search-panel" data-dropdown :class="{ open: open === 'search' }">
            <input v-model="searchTerm" type="text" placeholder="Search pages…" autocomplete="off">
            <a v-for="item in filteredNav" :key="item.route" :href="item.href">
                <span class="nav-icon fa" :class="`fa-${item.icon}`"></span>{{ item.label }}
            </a>
        </div>
    </div>

    <div class="dropdown-anchor" @click.stop>
        <button
            type="button" class="icon-button notification" aria-label="Notifications" aria-haspopup="true"
            :aria-expanded="open === 'notifications'" @click="toggle('notifications')"
        >
            <span class="fa fa-bell bell-icon"></span><i v-if="unread > 0"></i>
        </button>
        <div class="dropdown-menu dropdown-right notification-panel" data-dropdown :class="{ open: open === 'notifications' }">
            <p class="dropdown-label">Notifications</p>
            <template v-if="items.length">
                <div v-for="item in items" :key="item.id" class="notif-row" :class="{ unread: !item.read_at }">
                    <span class="activity-dot" :class="item.tone"></span>
                    <div>
                        <b>{{ item.title }}</b>
                        <small>{{ item.meta }} · {{ item.created_at }}</small>
                    </div>
                </div>
            </template>
            <p v-else class="dropdown-meta">No notifications yet.</p>
            <div class="dropdown-divider"></div>
            <button v-if="unread > 0" type="button" class="dropdown-item" @click="markAllRead">Mark all read</button>
            <a :href="notificationsIndexUrl">View all notifications</a>
        </div>
    </div>
</template>
