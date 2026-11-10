@extends('layouts.hotel')

@section('title', 'Discussions')

@section('content')
<div class="h-[calc(100vh-10rem)] bg-white rounded-2xl shadow-sm overflow-hidden border border-secondary/15">
    @if(!empty($dbNotReady))
        <div class="p-6">
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Le module discussion n'est pas encore initialise. Lance les migrations puis recharge la page.
            </div>
        </div>
    @else
        <div class="h-full grid grid-cols-1 lg:grid-cols-[360px_1fr] overflow-hidden">
            <aside class="h-full min-h-0 border-r border-secondary/20 bg-accent/15 relative flex flex-col">
                <div class="px-4 py-4 border-b border-secondary/20">
                    <h1 class="font-heading text-lg font-semibold text-primary">Discussions</h1>
                    <p class="text-xs text-primary/45 mt-1">Communication interne de l'etablissement</p>
                </div>

                <div id="conversation-list"
                     data-selected-conversation-id="{{ $selectedConversation?->id }}"
                     data-list-endpoint="{{ route('discussions.conversations.list') }}"
                     class="flex-1 min-h-0 overflow-y-auto">
                    @forelse($conversations as $conversation)
                        @php
                            $last = $conversation->messages->first();
                            $other = $conversation->participants->firstWhere('id', '!=', auth()->id());
                            $title = $conversation->title ?: ($other?->name ?? 'Discussion interne');
                            $active = $selectedConversation && $selectedConversation->id === $conversation->id;
                            $unread = (int) ($unreadCounts[$conversation->id] ?? 0);
                        @endphp
                        <div data-conversation-item="{{ $conversation->id }}"
                             class="px-4 py-3 border-b border-secondary/10 transition-colors {{ $active ? 'bg-white' : 'hover:bg-white/70' }}">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 text-xs font-semibold">
                                    {{ mb_strtoupper(mb_substr($title, 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-1 flex items-start justify-between gap-2">
                                    <a href="{{ route('discussions.index', ['conversation' => $conversation->id]) }}" class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-primary truncate flex items-center gap-1">
                                            {{ $title }}
                                            <span data-unread-badge="{{ $conversation->id }}"
                                                  class="inline-flex h-5 min-w-5 px-1 rounded-full items-center justify-center text-[10px] font-semibold bg-primary text-secondary {{ $unread > 0 ? '' : 'hidden' }}">
                                                {{ $unread > 0 ? $unread : '' }}
                                            </span>
                                        </p>
                                        <p class="text-xs text-primary/50 truncate mt-0.5">
                                            {{ $last?->body ?? 'Aucun message pour le moment' }}
                                        </p>
                                    </a>

                                    <div class="relative flex flex-col items-end gap-1 ml-2" data-menu-root>
                                        <button type="button"
                                                data-menu-toggle
                                                class="inline-flex h-5 w-5 items-center justify-center rounded-full text-primary/50 hover:text-primary hover:bg-accent/20 transition-colors">
                                            <i data-lucide="ellipsis-vertical" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <span class="text-[11px] text-primary/40 flex-shrink-0">{{ $last?->created_at?->format('H:i') }}</span>

                                        <div data-menu-panel class="hidden absolute right-0 top-6 z-20 w-44 rounded-lg border border-secondary/20 bg-white shadow-lg py-1">
                                            <button type="button"
                                                    data-action="archive"
                                                    data-conversation-id="{{ $conversation->id }}"
                                                    data-action-url="{{ route('discussions.conversations.archive', $conversation) }}"
                                                    class="w-full text-left px-3 py-2 text-xs text-primary hover:bg-accent/20">
                                                Archiver
                                            </button>
                                            <button type="button"
                                                    data-action="delete"
                                                    data-conversation-id="{{ $conversation->id }}"
                                                    data-action-url="{{ route('discussions.conversations.destroy', $conversation) }}"
                                                    class="w-full text-left px-3 py-2 text-xs text-red-700 hover:bg-red-50">
                                                Supprimer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div id="conversation-empty" class="p-6 text-center text-primary/40 text-sm">
                            Aucune discussion. Demarre une nouvelle conversation.
                        </div>
                    @endforelse
                </div>

                <button type="button"
                        onclick="openNewDiscussionModal()"
                        class="absolute bottom-5 right-5 h-12 w-12 rounded-full bg-primary text-white shadow-lg hover:bg-surface-dark transition-colors flex items-center justify-center">
                    <i data-lucide="message-square-plus" class="w-5 h-5"></i>
                </button>
            </aside>

            <section id="chat-window" class="h-full min-h-0 flex flex-col bg-white overflow-hidden">
                @if($selectedConversation)
                    @php
                        $other = $selectedConversation->participants->firstWhere('id', '!=', auth()->id());
                        $title = $selectedConversation->title ?: ($other?->name ?? 'Discussion interne');
                    @endphp
                    <header class="px-5 py-4 border-b border-secondary/20 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center text-xs font-semibold">
                            {{ mb_strtoupper(mb_substr($title, 0, 2)) }}
                        </div>
                        <div class="flex-1 flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-primary">{{ $title }}</h2>
                                @if($selectedConversation->is_group)
                                    <p class="text-xs text-primary/50 mt-0.5">
                                        {{ $selectedConversation->participants->count() }} membres
                                        @if($selectedConversation->participants->where('id', auth()->id())->first()?->pivot?->is_admin)
                                            <span class="ml-1 text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded uppercase font-semibold">Admin</span>
                                        @endif
                                    </p>
                                @elseif($other)
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="relative flex h-2.5 w-2.5">
                                          @if($other->isOnline())
                                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                                          @else
                                              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gray-300"></span>
                                          @endif
                                        </span>
                                        <p class="text-[11px] font-medium text-primary/50">
                                            {{ $other->isOnline() ? 'En ligne' : 'Hors ligne' }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                            
                            @if($selectedConversation->is_group && $selectedConversation->participants->where('id', auth()->id())->first()?->pivot?->is_admin)
                                <button type="button" class="text-xs px-3 py-1.5 bg-secondary/20 hover:bg-secondary/40 text-primary rounded-lg transition-colors font-medium flex items-center gap-1">
                                    <i data-lucide="settings" class="w-3.5 h-3.5"></i>
                                    Gérer le groupe
                                </button>
                            @endif
                        </div>
                    </header>

                    <div id="chat-messages"
                         data-current-user-id="{{ auth()->id() }}"
                         data-is-group="{{ $selectedConversation->is_group ? '1' : '0' }}"
                         data-poll-url="{{ route('discussions.conversations.poll', $selectedConversation) }}"
                         class="flex-1 min-h-0 overflow-y-auto p-5 space-y-3 bg-gradient-to-b from-white to-accent/10">
                        @forelse($messages as $message)
                            @php $mine = $message->user_id === auth()->id(); @endphp
                            <div class="flex items-end gap-1.5 {{ $mine ? 'justify-end' : 'justify-start' }} group" data-message-id="{{ $message->id }}">
                                @if($mine)
                                    <button type="button"
                                            data-action="open-delete-message-modal"
                                            data-message-id="{{ $message->id }}"
                                            data-message-body="{{ $message->body }}"
                                            data-can-delete-for-all="1"
                                            data-delete-url="{{ route('discussions.messages.destroy', $message) }}"
                                            title="Supprimer ce message"
                                            aria-label="Supprimer ce message"
                                            class="opacity-70 md:opacity-0 md:group-hover:opacity-100 focus-visible:opacity-100 h-7 w-7 rounded-full flex items-center justify-center text-primary/40 hover:text-red-600 hover:bg-red-50 transition-all flex-shrink-0">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                @endif
                                <div class="max-w-[72%] rounded-2xl px-4 py-3 shadow-sm {{ $mine ? 'bg-primary text-white' : 'bg-white border border-secondary/20 text-primary' }}">
                                    @if(!$mine)
                                        <p class="text-[11px] font-semibold text-primary/70 mb-1">{{ $message->user->name }}</p>
                                    @endif
                                    <p class="text-sm whitespace-pre-wrap leading-relaxed">{{ $message->body }}</p>
                                    <p class="text-[10px] mt-1.5 {{ $mine ? 'text-white/70' : 'text-primary/40' }} text-right">
                                        {{ $message->created_at->locale('fr')->isoFormat('HH:mm') }}
                                    </p>
                                </div>
                                @if(!$mine)
                                    <button type="button"
                                            data-action="open-delete-message-modal"
                                            data-message-id="{{ $message->id }}"
                                            data-message-body="{{ $message->body }}"
                                            data-can-delete-for-all="0"
                                            data-delete-url="{{ route('discussions.messages.destroy', $message) }}"
                                            title="Supprimer ce message"
                                            aria-label="Supprimer ce message"
                                            class="opacity-70 md:opacity-0 md:group-hover:opacity-100 focus-visible:opacity-100 h-7 w-7 rounded-full flex items-center justify-center text-primary/40 hover:text-red-600 hover:bg-red-50 transition-all flex-shrink-0">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                @endif
                            </div>
                        @empty
                            <div data-empty-messages class="text-center text-sm text-primary/35 py-12">Aucun message dans cette conversation.</div>
                        @endforelse
                    </div>

                    <footer class="flex-shrink-0 border-t border-secondary/20 p-4 bg-white">
                        @if($errors->any())
                            <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form id="message-form" method="POST" action="{{ route('discussions.store') }}" class="flex items-end gap-3">
                            @csrf
                            <input type="hidden" name="conversation_id" value="{{ $selectedConversation->id }}">
                            <textarea id="message-body" name="body" rows="2" maxlength="1000" required placeholder="Ecris un message..."
                                      class="flex-1 resize-none px-4 py-2.5 text-sm border border-secondary/30 rounded-2xl outline-none focus:border-secondary bg-accent/10">{{ old('body') }}</textarea>
                            <button id="send-button" type="submit" class="h-11 w-11 rounded-full bg-primary text-white hover:bg-surface-dark transition-colors flex items-center justify-center">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </footer>
                @else
                    <div class="h-full flex items-center justify-center text-center px-6">
                        <div>
                            <i data-lucide="messages-square" class="w-12 h-12 text-primary/30 mx-auto mb-3"></i>
                            <p class="text-primary/60 font-medium">Selectionne une conversation</p>
                            <p class="text-primary/40 text-sm mt-1">Ou demarre une nouvelle discussion avec le bouton en bas a gauche.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>

        <x-modal id="new-discussion-modal" title="Nouvelle discussion" formAction="{{ route('discussions.conversations.store') }}" closeAction="closeNewDiscussionModal()">
            <input type="hidden" name="is_group" :value="isGroup ? '1' : '0'">

            <div x-data="{ isGroup: false }">
                <div class="flex items-center gap-2 bg-accent/10 p-2.5 rounded-lg border border-secondary/20 cursor-pointer mb-4" @click="isGroup = !isGroup">
                    <input type="checkbox" id="is_group_toggle" x-model="isGroup" class="rounded border-secondary/30 text-primary focus:ring-primary w-4 h-4 pointer-events-none">
                    <label for="is_group_toggle" class="text-sm font-medium text-primary pointer-events-none">Créer un groupe de discussion</label>
                </div>

                <div x-show="isGroup" x-transition class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-primary/60 uppercase tracking-widest mb-1.5">Nom du groupe *</label>
                        <input type="text" name="title" :required="isGroup" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary" placeholder="Ex: Équipe Réception">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-primary/60 uppercase tracking-widest mb-1.5">Membres du groupe *</label>
                        <div class="max-h-48 overflow-y-auto border border-secondary/30 rounded-lg p-2 space-y-1 bg-white">
                            @foreach($availableUsers as $participant)
                                <label class="flex items-center gap-3 p-2 hover:bg-accent/10 rounded-md cursor-pointer border border-transparent hover:border-secondary/20 transition-colors">
                                    <input type="checkbox" name="participant_ids[]" value="{{ $participant->id }}" class="rounded border-secondary/30 text-primary focus:ring-primary w-4 h-4">
                                    <div>
                                        <span class="text-sm font-medium text-primary block">{{ $participant->name }}</span>
                                        <span class="text-[10px] text-primary/50 uppercase">{{ str_replace('_', ' ', $participant->role ?? 'staff') }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div x-show="!isGroup" x-transition>
                    <label class="block text-xs font-semibold text-primary/60 uppercase tracking-widest mb-1.5">Démarrer avec *</label>
                    <select name="participant_id" :required="!isGroup" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        <option value="">Choisir un collaborateur</option>
                        @foreach($availableUsers as $participant)
                            <option value="{{ $participant->id }}">{{ $participant->name }} - {{ str_replace('_', ' ', $participant->role ?? 'staff') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <x-slot:footer>
                <button type="submit" class="w-full py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-surface-dark transition-colors">
                    Démarrer la discussion
                </button>
            </x-slot:footer>
        </x-modal>

        <x-modal id="delete-message-modal" title="Supprimer le message" closeAction="closeDeleteMessageModal()">
            <p class="text-sm text-primary/70 mb-2">
                Choisis le mode de suppression de ce message.
            </p>
            <div id="delete-message-preview" class="hidden text-xs text-primary/60 bg-accent/15 border border-secondary/20 rounded-lg px-3 py-2 mb-4 break-words line-clamp-3"></div>
            <div class="grid grid-cols-1 gap-2">
                <button type="button" id="delete-message-for-me-btn" class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm font-medium text-primary hover:bg-accent/20 transition-colors">
                    Uniquement pour moi
                </button>
                <button type="button" id="delete-message-for-all-btn" class="w-full rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 transition-colors">
                    Pour moi et mon interlocuteur
                </button>
            </div>
        </x-modal>
    @endif
</div>

<script>
const discussionState = {
    csrf: '{{ csrf_token() }}',
    indexUrl: '{{ route('discussions.index') }}',
    selectedConversationId: Number(document.getElementById('conversation-list')?.dataset.selectedConversationId || 0),
    deleteMessageUrl: '',
    deleteMessageId: null,
    deletingConversationIds: new Set(),
    deletingMessagePending: false,
};

window.openNewDiscussionModal = function() {
    const modal = document.getElementById('new-discussion-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeNewDiscussionModal = function() {
    const modal = document.getElementById('new-discussion-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openDeleteMessageModal = function(messageId, messageBody, url, canDeleteForAll) {
    discussionState.deleteMessageId = Number(messageId || 0);
    discussionState.deleteMessageUrl = url || '';

    const previewEl = document.getElementById('delete-message-preview');
    if (previewEl) {
        const trimmed = (messageBody || '').trim();
        if (trimmed) {
            previewEl.textContent = trimmed;
            previewEl.classList.remove('hidden');
        } else {
            previewEl.textContent = '';
            previewEl.classList.add('hidden');
        }
    }

    const forAllBtn = document.getElementById('delete-message-for-all-btn');
    if (forAllBtn) {
        const isGroup = document.getElementById('chat-messages')?.dataset.isGroup === '1';
        forAllBtn.textContent = isGroup ? 'Pour moi et les participants' : 'Pour moi et mon interlocuteur';
        forAllBtn.classList.toggle('hidden', !canDeleteForAll);
    }

    const modal = document.getElementById('delete-message-modal');
    modal?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeDeleteMessageModal = function() {
    discussionState.deleteMessageId = null;
    discussionState.deleteMessageUrl = '';
    discussionState.deletingMessagePending = false;
    const modal = document.getElementById('delete-message-modal');
    modal?.classList.add('hidden');
    document.body.style.overflow = '';
};

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const msgModal = document.getElementById('delete-message-modal');
        if (msgModal && !msgModal.classList.contains('hidden')) {
            closeDeleteMessageModal();
        }
    }
});

function escapeHtml(value) {
    return (value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function updateUnreadUI(unreadCounts, totalUnread) {
    document.querySelectorAll('[data-unread-badge]').forEach((badge) => {
        const conversationId = badge.getAttribute('data-unread-badge');
        const count = Number((unreadCounts && unreadCounts[conversationId]) || 0);

        if (count > 0) {
            badge.textContent = `${count}`;
            badge.classList.remove('hidden');
            badge.style.display = 'inline-flex';
        } else {
            badge.textContent = '';
            badge.classList.add('hidden');
            badge.style.display = 'none';
        }
    });

    const sidebarDot = document.getElementById('sidebar-discussions-dot');
    if (sidebarDot) {
        sidebarDot.classList.toggle('hidden', Number(totalUnread || 0) <= 0);
    }
}

function renderConversationItem(conversation, isActive) {
    const activeClass = isActive ? 'bg-white' : 'hover:bg-white/70';
    const unread = Number(conversation.unread || 0);
    const unreadHtml = unread > 0
        ? `<span data-unread-badge="${conversation.id}" class="inline-flex h-5 min-w-5 px-1 rounded-full items-center justify-center text-[10px] font-semibold bg-primary text-secondary">${unread}</span>`
        : `<span data-unread-badge="${conversation.id}" class="hidden inline-flex h-5 min-w-5 px-1 rounded-full items-center justify-center text-[10px] font-semibold bg-primary text-secondary"></span>`;

    return `
        <div data-conversation-item="${conversation.id}" class="px-4 py-3 border-b border-secondary/10 transition-colors ${activeClass}">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 text-xs font-semibold">
                    ${escapeHtml(conversation.initials || 'DI')}
                </div>
                <div class="min-w-0 flex-1 flex items-start justify-between gap-2">
                    <a href="${escapeHtml(conversation.url || '#')}" class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-primary truncate flex items-center gap-1">
                            ${escapeHtml(conversation.title || 'Discussion')}
                            ${unreadHtml}
                        </p>
                        <p class="text-xs text-primary/50 truncate mt-0.5">${escapeHtml(conversation.last_body || '')}</p>
                    </a>
                    <div class="relative flex flex-col items-end gap-1 ml-2" data-menu-root>
                        <button type="button" data-menu-toggle class="inline-flex h-5 w-5 items-center justify-center rounded-full text-primary/50 hover:text-primary hover:bg-accent/20 transition-colors">
                            <i data-lucide="ellipsis-vertical" class="w-3.5 h-3.5"></i>
                        </button>
                        <span class="text-[11px] text-primary/40 flex-shrink-0">${escapeHtml(conversation.last_time || '')}</span>
                        <div data-menu-panel class="hidden absolute right-0 top-6 z-20 w-44 rounded-lg border border-secondary/20 bg-white shadow-lg py-1">
                            <button type="button" data-action="archive" data-conversation-id="${conversation.id}" data-action-url="${escapeHtml(conversation.archive_url || '')}" class="w-full text-left px-3 py-2 text-xs text-primary hover:bg-accent/20">Archiver</button>
                            <button type="button" data-action="delete" data-conversation-id="${conversation.id}" data-action-url="${escapeHtml(conversation.delete_url || '')}" class="w-full text-left px-3 py-2 text-xs text-red-700 hover:bg-red-50">Supprimer</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderConversationList(conversations) {
    const list = document.getElementById('conversation-list');
    if (!list) return;

    if (!Array.isArray(conversations) || conversations.length === 0) {
        list.innerHTML = '<div id="conversation-empty" class="p-6 text-center text-primary/40 text-sm">Aucune discussion. Demarre une nouvelle conversation.</div>';
        return;
    }

    list.innerHTML = conversations
        .map((conversation) => renderConversationItem(
            conversation,
            Number(conversation.id) === Number(discussionState.selectedConversationId || 0),
        ))
        .join('');

    if (typeof window.refreshLucideIcons === 'function') {
        window.refreshLucideIcons();
    }
}

function isAnyConversationMenuOpen() {
    return !!document.querySelector('[data-menu-panel]:not(.hidden)');
}

async function syncConversationList() {
    const list = document.getElementById('conversation-list');
    const endpoint = list?.dataset.listEndpoint;
    if (!endpoint) return;

    try {
        const response = await fetch(endpoint, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) return;

        const payload = await response.json();
        if (!payload || !payload.ok) return;

        if (!isAnyConversationMenuOpen()) {
            renderConversationList(payload.conversations || []);
        }
        updateUnreadUI(payload.unread_counts || {}, payload.total_unread || 0);

        if (discussionState.selectedConversationId > 0) {
            const stillVisible = (payload.conversations || []).some((c) => Number(c.id) === Number(discussionState.selectedConversationId));
            if (!stillVisible) {
                showSelectConversationState();
            }
        }
    } catch (error) {
        console.error('Conversations polling failed', error);
    }
}

function showSelectConversationState() {
    const chatWindow = document.getElementById('chat-window');
    if (!chatWindow) return;

    discussionState.selectedConversationId = 0;
    const listEl = document.getElementById('conversation-list');
    if (listEl) {
        listEl.dataset.selectedConversationId = '';
    }

    if (window.location.search.includes('conversation=')) {
        window.history.replaceState({}, '', discussionState.indexUrl);
    }

    chatWindow.innerHTML = `
        <div class="h-full flex items-center justify-center text-center px-6">
            <div>
                <i data-lucide="messages-square" class="w-12 h-12 text-primary/30 mx-auto mb-3"></i>
                <p class="text-primary/60 font-medium">Selectionne une conversation</p>
                <p class="text-primary/40 text-sm mt-1">Ou demarre une nouvelle discussion avec le bouton en bas a gauche.</p>
            </div>
        </div>
    `;

    if (window.__discussionPollTimer) {
        clearInterval(window.__discussionPollTimer);
        window.__discussionPollTimer = null;
    }

    if (typeof window.refreshLucideIcons === 'function') {
        window.refreshLucideIcons();
    }
}

function removeMessageElement(messageId) {
    const container = document.getElementById('chat-messages');
    if (!container) return;

    const el = container.querySelector(`[data-message-id="${messageId}"]`);
    if (el) {
        el.remove();
    }

    if (!container.querySelector('[data-message-id]') && !container.querySelector('[data-empty-messages]')) {
        const empty = document.createElement('div');
        empty.setAttribute('data-empty-messages', '');
        empty.className = 'text-center text-sm text-primary/35 py-12';
        empty.textContent = 'Aucun message dans cette conversation.';
        container.appendChild(empty);
    }
}

document.addEventListener('click', async function(event) {
    const toggle = event.target.closest('[data-menu-toggle]');
    const root = event.target.closest('[data-menu-root]');

    document.querySelectorAll('[data-menu-panel]').forEach((panel) => {
        if (!root || panel.parentElement !== root) {
            panel.classList.add('hidden');
        }
    });

    if (toggle && root) {
        event.preventDefault();
        event.stopPropagation();
        const panel = root.querySelector('[data-menu-panel]');
        panel?.classList.toggle('hidden');
        return;
    }

    const archiveButton = event.target.closest('[data-action="archive"]');
    if (archiveButton) {
        event.preventDefault();
        document.querySelectorAll('[data-menu-panel]').forEach((panel) => panel.classList.add('hidden'));
        const url = archiveButton.getAttribute('data-action-url');
        if (!url) return;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': discussionState.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (payload?.unread_counts !== undefined) {
                updateUnreadUI(payload.unread_counts || {}, payload.total_unread || 0);
            }
            await syncConversationList();
        } catch (error) {
            console.error('Archive conversation failed', error);
        }
        return;
    }

    const deleteConversationButton = event.target.closest('[data-action="delete"]');
    if (deleteConversationButton) {
        event.preventDefault();
        document.querySelectorAll('[data-menu-panel]').forEach((panel) => panel.classList.add('hidden'));

        const conversationId = Number(deleteConversationButton.getAttribute('data-conversation-id') || 0);
        const url = deleteConversationButton.getAttribute('data-action-url');
        if (!url || discussionState.deletingConversationIds.has(conversationId)) return;

        discussionState.deletingConversationIds.add(conversationId);
        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': discussionState.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) return;
            const payload = await response.json();

            if (Number(payload?.removed_conversation_id || 0) === Number(discussionState.selectedConversationId || 0)) {
                showSelectConversationState();
            }

            if (payload?.unread_counts !== undefined) {
                updateUnreadUI(payload.unread_counts || {}, payload.total_unread || 0);
            }

            await syncConversationList();
        } catch (error) {
            console.error('Delete conversation failed', error);
        } finally {
            discussionState.deletingConversationIds.delete(conversationId);
        }
        return;
    }

    const openDeleteMsgButton = event.target.closest('[data-action="open-delete-message-modal"]');
    if (openDeleteMsgButton) {
        event.preventDefault();
        openDeleteMessageModal(
            openDeleteMsgButton.getAttribute('data-message-id'),
            openDeleteMsgButton.getAttribute('data-message-body'),
            openDeleteMsgButton.getAttribute('data-delete-url'),
            openDeleteMsgButton.getAttribute('data-can-delete-for-all') === '1',
        );
    }
});

async function performMessageDeletion(mode) {
    if (!discussionState.deleteMessageUrl || discussionState.deletingMessagePending) return;
    discussionState.deletingMessagePending = true;

    const forMeBtn = document.getElementById('delete-message-for-me-btn');
    const forAllBtn = document.getElementById('delete-message-for-all-btn');
    forMeBtn?.setAttribute('disabled', 'disabled');
    forAllBtn?.setAttribute('disabled', 'disabled');

    try {
        const response = await fetch(discussionState.deleteMessageUrl, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': discussionState.csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mode }),
        });
        if (!response.ok) return;
        const payload = await response.json();
        const removedId = Number(payload?.removed_message_id || discussionState.deleteMessageId || 0);

        closeDeleteMessageModal();

        if (removedId > 0) {
            removeMessageElement(removedId);
        }

        if (payload?.unread_counts !== undefined) {
            updateUnreadUI(payload.unread_counts || {}, payload.total_unread || 0);
        }
        await syncConversationList();
    } catch (error) {
        console.error('Delete message failed', error);
    } finally {
        discussionState.deletingMessagePending = false;
        forMeBtn?.removeAttribute('disabled');
        forAllBtn?.removeAttribute('disabled');
    }
}

document.getElementById('delete-message-for-me-btn')?.addEventListener('click', () => performMessageDeletion('me'));
document.getElementById('delete-message-for-all-btn')?.addEventListener('click', () => performMessageDeletion('all'));

const chatContainer = document.getElementById('chat-messages');
if (chatContainer) {
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function appendMessage(message, currentUserId) {
    const container = document.getElementById('chat-messages');
    if (!container) return;
    if (container.querySelector(`[data-message-id="${message.id}"]`)) {
        return;
    }

    container.querySelector('[data-empty-messages]')?.remove();

    const mine = Number(message.user_id) === Number(currentUserId);
    const canDeleteForAll = mine || Boolean(message.can_delete_for_all);
    const wrapper = document.createElement('div');
    wrapper.className = `flex items-end gap-1.5 ${mine ? 'justify-end' : 'justify-start'} group`;
    wrapper.setAttribute('data-message-id', message.id);

    const deleteButtonHtml = `
        <button type="button"
                data-action="open-delete-message-modal"
                data-message-id="${message.id}"
                data-message-body="${escapeHtml(message.body || '')}"
                data-can-delete-for-all="${canDeleteForAll ? '1' : '0'}"
                data-delete-url="${escapeHtml(message.delete_url || '')}"
                title="Supprimer ce message"
                aria-label="Supprimer ce message"
                class="opacity-70 md:opacity-0 md:group-hover:opacity-100 focus-visible:opacity-100 h-7 w-7 rounded-full flex items-center justify-center text-primary/40 hover:text-red-600 hover:bg-red-50 transition-all flex-shrink-0">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
        </button>
    `;

    wrapper.innerHTML = `
        ${mine ? deleteButtonHtml : ''}
        <div class="max-w-[72%] rounded-2xl px-4 py-3 shadow-sm ${mine ? 'bg-primary text-white' : 'bg-white border border-secondary/20 text-primary'}">
            ${mine ? '' : `<p class="text-[11px] font-semibold text-primary/70 mb-1">${escapeHtml(message.user_name)}</p>`}
            <p class="text-sm whitespace-pre-wrap leading-relaxed">${escapeHtml(message.body)}</p>
            <p class="text-[10px] mt-1.5 ${mine ? 'text-white/70' : 'text-primary/40'} text-right">${escapeHtml(message.time || '')}</p>
        </div>
        ${mine ? '' : deleteButtonHtml}
    `;

    container.appendChild(wrapper);

    if (typeof window.refreshLucideIcons === 'function') {
        window.refreshLucideIcons();
    }
}

if (chatContainer) {
    let lastMessageId = 0;
    const currentUserId = Number(chatContainer.dataset.currentUserId || 0);
    const pollUrl = chatContainer.dataset.pollUrl;
    const messageForm = document.getElementById('message-form');
    const messageBody = document.getElementById('message-body');
    const sendButton = document.getElementById('send-button');

    const existing = [...chatContainer.querySelectorAll('[data-message-id]')]
        .map((el) => Number(el.getAttribute('data-message-id')))
        .filter((id) => Number.isFinite(id) && id > 0);
    if (existing.length) {
        lastMessageId = Math.max(...existing);
    }

    const scrollToBottom = () => {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    };

    const pollMessages = async () => {
        if (!pollUrl) return;
        try {
            const response = await fetch(`${pollUrl}?after_id=${lastMessageId}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (!payload.ok) return;

            if (payload.conversation_hidden) {
                showSelectConversationState();
                updateUnreadUI(payload.unread_counts || {}, payload.total_unread || 0);
                await syncConversationList();
                return;
            }

            if (Array.isArray(payload.removed_message_ids) && payload.removed_message_ids.length) {
                payload.removed_message_ids.forEach((id) => removeMessageElement(Number(id)));
            }

            if (Array.isArray(payload.messages) && payload.messages.length) {
                payload.messages.forEach((message) => {
                    appendMessage(message, currentUserId);
                    lastMessageId = Math.max(lastMessageId, Number(message.id));
                });
                scrollToBottom();
                if (window.playNotificationSound) window.playNotificationSound();
            }

            updateUnreadUI(payload.unread_counts || {}, payload.total_unread || 0);
            await syncConversationList();
        } catch (error) {
            console.error('Polling discussion failed', error);
        }
    };

    if (messageForm && messageBody) {
        messageForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const body = messageBody.value.trim();
            if (!body) return;

            sendButton?.setAttribute('disabled', 'disabled');

            try {
                const response = await fetch(messageForm.action, {
                    method: 'POST',
                    body: new FormData(messageForm),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Message send failed');
                }

                const payload = await response.json();
                if (payload.ok && payload.message) {
                    appendMessage(payload.message, currentUserId);
                    lastMessageId = Math.max(lastMessageId, Number(payload.message.id));
                    messageBody.value = '';
                    scrollToBottom();
                    updateUnreadUI(payload.unread_counts || {}, payload.total_unread || 0);
                    await syncConversationList();
                }
            } catch (error) {
                console.error('Send message failed', error);
            } finally {
                sendButton?.removeAttribute('disabled');
                messageBody.focus();
            }
        });
    }

    window.__discussionPollTimer = setInterval(pollMessages, 2000);
    pollMessages();
}

syncConversationList();
setInterval(syncConversationList, 2000);
</script>
@endsection
