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
        @php
            $memberPickerUsers = $availableUsers->map(function ($u) {
                $parts = preg_split('/\s+/u', trim((string) $u->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($parts) >= 2) {
                    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
                } else {
                    $initials = mb_strtoupper(mb_substr(trim((string) $u->name), 0, 2) ?: 'ST');
                }

                return [
                    'id' => (int) $u->id,
                    'name' => (string) $u->name,
                    'role' => (string) ($u->role ?? 'staff'),
                    'role_label' => ucfirst(str_replace('_', ' ', (string) ($u->role ?? 'staff'))),
                    'initials' => $initials,
                ];
            })->values()->all();

            $initialGroupMemberIds = collect((array) old('participant_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        @endphp
        <div class="h-full grid grid-cols-1 lg:grid-cols-[360px_1fr] overflow-hidden">
            <aside class="h-full min-h-0 border-r border-secondary/20 bg-accent/15 relative flex flex-col">
                <div class="px-4 py-4 border-b border-secondary/20 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <h1 class="font-heading text-lg font-semibold text-primary">Discussions</h1>
                        <p class="text-xs text-primary/45 mt-1 truncate">Communication interne de l'etablissement</p>
                    </div>
                    <button type="button"
                            id="new-group-header-btn"
                            onclick="openNewDiscussionModal(true)"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-secondary/30 bg-white text-xs font-semibold text-primary hover:bg-accent/20 transition-colors flex-shrink-0">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                        Nouveau groupe
                    </button>
                </div>

                @if(session('success'))
                    <div class="mx-3 mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 flex items-center justify-between gap-2">
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mx-3 mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

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
                                        <p class="text-sm font-semibold text-primary truncate flex items-center gap-1.5">
                                            <span class="truncate">{{ $title }}</span>
                                            @if($conversation->is_group)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-secondary/20 text-primary/80 text-[10px] font-medium flex-shrink-0">Groupe</span>
                                            @endif
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
                        onclick="openNewDiscussionModal(false)"
                        title="Nouvelle discussion"
                        aria-label="Nouvelle discussion"
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
                            
                            @if($selectedConversation->is_group)
                                @php
                                    $isGroupAdmin = (bool) $selectedConversation->participants->firstWhere('id', auth()->id())?->pivot?->is_admin;
                                @endphp
                                <button type="button"
                                        id="manage-group-btn"
                                        onclick="openManageGroupModal()"
                                        class="text-xs px-3 py-1.5 bg-secondary/20 hover:bg-secondary/40 text-primary rounded-lg transition-colors font-medium flex items-center gap-1">
                                    <i data-lucide="{{ $isGroupAdmin ? 'settings' : 'users' }}" class="w-3.5 h-3.5"></i>
                                    {{ $isGroupAdmin ? 'Gérer le groupe' : 'Membres' }}
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
                        @if($errors->has('body'))
                            <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                                {{ $errors->first('body') }}
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
                            <p class="text-primary/40 text-sm mt-1">Ou demarre une nouvelle discussion ou un groupe de travail.</p>
                            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                                <button type="button"
                                        onclick="openNewDiscussionModal(false)"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-surface-dark transition-colors">
                                    <i data-lucide="message-square-plus" class="w-3.5 h-3.5"></i>
                                    Nouvelle discussion
                                </button>
                                <button type="button"
                                        onclick="openNewDiscussionModal(true)"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-secondary/30 bg-white text-primary text-xs font-semibold hover:bg-accent/20 transition-colors">
                                    <i data-lucide="users" class="w-3.5 h-3.5"></i>
                                    Créer un groupe
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </section>
        </div>

        <x-modal id="new-discussion-modal" title="Nouvelle discussion" formAction="{{ route('discussions.conversations.store') }}" closeAction="closeNewDiscussionModal()">
            <div x-data="{
                    isGroup: {{ old('is_group') ? 'true' : 'false' }}
                 }"
                 x-on:set-discussion-group-mode.window="isGroup = Boolean($event.detail)">
                <input type="hidden" name="is_group" value="{{ old('is_group') ? '1' : '0' }}" :value="isGroup ? '1' : '0'">

                @if($errors->hasAny(['title', 'participant_ids', 'participant_ids.*', 'participant_id', 'conversation']))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-2 p-1 rounded-xl bg-accent/20 border border-secondary/20 mb-4">
                    <button type="button"
                            id="tab-direct-discussion"
                            x-on:click="isGroup = false; $dispatch('set-discussion-group-mode', false)"
                            :class="!isGroup ? 'bg-primary text-white shadow-sm' : 'text-primary/70 hover:text-primary'"
                            class="py-2 px-3 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1.5">
                        <i data-lucide="user" class="w-3.5 h-3.5"></i>
                        Discussion privée
                    </button>
                    <button type="button"
                            id="tab-group-discussion"
                            x-on:click="isGroup = true; $dispatch('set-discussion-group-mode', true)"
                            :class="isGroup ? 'bg-primary text-white shadow-sm' : 'text-primary/70 hover:text-primary'"
                            class="py-2 px-3 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1.5">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                        Groupe de discussion
                    </button>
                </div>

                <div x-show="isGroup" x-transition class="space-y-3">
                    <div>
                        <label for="group-title-input" class="block text-xs font-semibold text-primary/60 uppercase tracking-widest mb-1.5">Nom du groupe *</label>
                        <input id="group-title-input"
                               type="text"
                               name="title"
                               value="{{ old('title') }}"
                               maxlength="160"
                               :required="isGroup"
                               :disabled="!isGroup"
                               class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary"
                               placeholder="Ex: Équipe Réception">
                    </div>
                    <div x-data="groupMemberPicker(@js($memberPickerUsers), @js($initialGroupMemberIds), {{ old('is_group') ? 'true' : 'false' }}, true)"
                         x-on:set-discussion-group-mode.window="onModeChange($event.detail)"
                         x-on:discussion-group-members-error.window="clientError = $event.detail"
                         x-on:click.outside="showDropdown = false">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="group-member-search-input" class="block text-xs font-semibold text-primary/60 uppercase tracking-widest">Membres du groupe *</label>
                            <span class="text-[11px] text-primary/50"
                                  x-text="selectedMembers.length > 0 ? (selectedMembers.length + ' sélectionné(s)') : '{{ $availableUsers->count() }} disponible(s)'">
                                {{ $availableUsers->count() }} disponible(s)
                            </span>
                        </div>
                        @if($availableUsers->isEmpty())
                            <div class="rounded-lg border border-secondary/20 bg-accent/10 px-3 py-3 text-xs text-primary/60">
                                Aucun autre collaborateur actif disponible pour créer un groupe.
                            </div>
                        @else
                            <div class="space-y-2">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="search" class="h-4 w-4 text-primary/40"></i>
                                    </div>
                                    <input id="group-member-search-input"
                                           type="text"
                                           x-model="search"
                                           x-ref="searchInput"
                                           x-on:focus="showDropdown = true"
                                           x-on:input="onSearchInput()"
                                           x-on:keydown="onKeydown($event)"
                                           autocomplete="off"
                                           placeholder="Rechercher un membre par nom..."
                                           class="w-full pl-10 pr-8 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:ring-2 focus:ring-primary/15 focus:border-secondary">
                                    <button type="button"
                                            x-show="search.length > 0"
                                            x-on:click="clearSearch()"
                                            style="display: none;"
                                            title="Effacer la recherche"
                                            aria-label="Effacer la recherche"
                                            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-primary/40 hover:text-primary transition-colors">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                    </button>
                                </div>

                                <div x-show="showDropdown && search.trim().length > 0"
                                     style="display: none;"
                                     class="w-full bg-white rounded-lg shadow-md border border-secondary/25 overflow-hidden">
                                    <ul class="max-h-48 overflow-y-auto py-1 divide-y divide-secondary/10">
                                        <template x-for="(member, index) in filteredUsers" :key="member.id">
                                            <li>
                                                <button type="button"
                                                        x-on:mousedown.prevent="addMember(member)"
                                                        :class="index === activeIndex ? 'bg-accent/25' : 'hover:bg-accent/15'"
                                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 transition-colors">
                                                    <div class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 text-[10px] font-semibold"
                                                         x-text="member.initials"></div>
                                                    <div class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm font-medium text-primary" x-text="member.name"></span>
                                                        <span class="block truncate text-[10px] text-primary/50 uppercase tracking-wide" x-text="member.role_label"></span>
                                                    </div>
                                                    <span class="text-primary/45 flex-shrink-0">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                        <li x-show="filteredUsers.length === 0"
                                            class="px-3 py-2.5 text-xs text-primary/55"
                                            x-text="matchingAlreadySelectedCount > 0 ? 'Déjà ajouté au groupe.' : 'Aucun collaborateur trouvé.'"></li>
                                    </ul>
                                </div>

                                <p x-show="clientError"
                                   x-text="clientError"
                                   style="display: none;"
                                   class="text-xs text-red-600"></p>

                                <div x-show="selectedMembers.length > 0" style="display: none;" class="flex flex-wrap gap-1.5 pt-0.5">
                                    <template x-for="member in selectedMembers" :key="member.id">
                                        <span class="inline-flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-full bg-accent/25 border border-secondary/30 text-xs text-primary">
                                            <span class="w-5 h-5 rounded-full bg-primary text-white text-[9px] font-semibold flex items-center justify-center flex-shrink-0"
                                                  x-text="member.initials"></span>
                                            <span class="font-medium truncate max-w-[160px]" x-text="member.name"></span>
                                            <button type="button"
                                                    x-on:click="removeMember(member.id)"
                                                    title="Retirer ce membre"
                                                    aria-label="Retirer ce membre"
                                                    class="text-primary/45 hover:text-red-600 transition-colors focus:outline-none">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                            </button>
                                        </span>
                                    </template>
                                </div>

                                <template x-for="member in selectedMembers" :key="'input-' + member.id">
                                    <input type="hidden" name="participant_ids[]" :value="member.id" :disabled="!pickerActive">
                                </template>

                                <p x-show="selectedMembers.length === 0 && (!showDropdown || search.trim().length === 0)"
                                   class="text-[11px] text-primary/45">
                                    Saisis le nom d'un collaborateur ci-dessus pour l'ajouter au groupe.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <div x-show="!isGroup" x-transition>
                    <label for="direct-participant-select" class="block text-xs font-semibold text-primary/60 uppercase tracking-widest mb-1.5">Démarrer avec *</label>
                    <select id="direct-participant-select"
                            name="participant_id"
                            :required="!isGroup"
                            :disabled="isGroup"
                            class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        <option value="">Choisir un collaborateur</option>
                        @foreach($availableUsers as $participant)
                            <option value="{{ $participant->id }}" {{ (string) old('participant_id') === (string) $participant->id ? 'selected' : '' }}>
                                {{ $participant->name }} - {{ str_replace('_', ' ', $participant->role ?? 'staff') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <x-slot:footer>
                <button type="submit"
                        x-data="{ isGroupSubmit: {{ old('is_group') ? 'true' : 'false' }} }"
                        x-on:set-discussion-group-mode.window="isGroupSubmit = Boolean($event.detail)"
                        class="w-full py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-surface-dark transition-colors">
                    <span x-text="isGroupSubmit ? 'Créer le groupe' : 'Démarrer la discussion'">Démarrer la discussion</span>
                </button>
            </x-slot:footer>
        </x-modal>

        @if($selectedConversation && $selectedConversation->is_group)
            @php
                $isGroupAdmin = (bool) $selectedConversation->participants->firstWhere('id', auth()->id())?->pivot?->is_admin;
                $existingMemberIds = $selectedConversation->participants->pluck('id')->map(fn ($id) => (int) $id)->all();
                $addableUsers = $availableUsers->reject(fn ($u) => in_array((int) $u->id, $existingMemberIds, true));
                $addableMemberPickerUsers = array_values(array_filter(
                    $memberPickerUsers,
                    fn ($u) => !in_array((int) $u['id'], $existingMemberIds, true)
                ));
            @endphp
            <x-modal id="manage-group-modal" title="{{ $isGroupAdmin ? 'Gérer le groupe' : 'Membres du groupe' }}" closeAction="closeManageGroupModal()">
                <div class="space-y-5">
                    @if($isGroupAdmin)
                        <form method="POST" action="{{ route('discussions.conversations.update', $selectedConversation) }}" class="space-y-2">
                            @csrf
                            @method('PATCH')
                            <label for="manage-group-title" class="block text-xs font-semibold text-primary/60 uppercase tracking-widest">Nom du groupe</label>
                            <div class="flex items-center gap-2">
                                <input id="manage-group-title"
                                       type="text"
                                       name="title"
                                       value="{{ $selectedConversation->title }}"
                                       required
                                       maxlength="160"
                                       class="flex-1 px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <button type="submit" class="px-3.5 py-2 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-surface-dark transition-colors">
                                    Renommer
                                </button>
                            </div>
                        </form>
                    @endif

                    <div>
                        <p class="text-xs font-semibold text-primary/60 uppercase tracking-widest mb-2">
                            Membres ({{ $selectedConversation->participants->count() }})
                        </p>
                        <div class="max-h-48 overflow-y-auto border border-secondary/20 rounded-lg divide-y divide-secondary/10 bg-white">
                            @foreach($selectedConversation->participants as $member)
                                <div class="flex items-center justify-between gap-2 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-primary truncate">
                                            {{ $member->name }}
                                            @if((int) $member->id === (int) auth()->id())
                                                <span class="text-xs text-primary/45 font-normal">(vous)</span>
                                            @endif
                                        </p>
                                        <p class="text-[10px] text-primary/50 uppercase">{{ str_replace('_', ' ', $member->role ?? 'staff') }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        @if($member->pivot?->is_admin)
                                            <span class="text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded uppercase font-semibold">Admin</span>
                                        @endif
                                        @if($isGroupAdmin && (int) $member->id !== (int) auth()->id())
                                            <form method="POST" action="{{ route('discussions.conversations.members.destroy', [$selectedConversation, $member]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 px-2 py-1 rounded hover:bg-red-50 transition-colors">
                                                    Retirer
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($isGroupAdmin)
                        @if($addableUsers->isNotEmpty())
                            <form method="POST"
                                  action="{{ route('discussions.conversations.members.store', $selectedConversation) }}"
                                  class="space-y-2.5"
                                  x-data="groupMemberPicker(@js($addableMemberPickerUsers), [], true, false)"
                                  x-on:click.outside="showDropdown = false">
                                @csrf
                                <div class="flex items-center justify-between">
                                    <label for="manage-group-member-search-input" class="block text-xs font-semibold text-primary/60 uppercase tracking-widest">Ajouter des membres</label>
                                    <span class="text-[11px] text-primary/50"
                                          x-show="selectedMembers.length > 0"
                                          style="display: none;"
                                          x-text="selectedMembers.length + ' sélectionné(s)'"></span>
                                </div>

                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="search" class="h-4 w-4 text-primary/40"></i>
                                    </div>
                                    <input id="manage-group-member-search-input"
                                           type="text"
                                           x-model="search"
                                           x-ref="searchInput"
                                           x-on:focus="showDropdown = true"
                                           x-on:input="onSearchInput()"
                                           x-on:keydown="onKeydown($event)"
                                           autocomplete="off"
                                           placeholder="Rechercher un membre à ajouter..."
                                           class="w-full pl-10 pr-8 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:ring-2 focus:ring-primary/15 focus:border-secondary">
                                    <button type="button"
                                            x-show="search.length > 0"
                                            x-on:click="clearSearch()"
                                            style="display: none;"
                                            title="Effacer la recherche"
                                            aria-label="Effacer la recherche"
                                            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-primary/40 hover:text-primary transition-colors">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                    </button>
                                </div>

                                <div x-show="showDropdown && search.trim().length > 0"
                                     style="display: none;"
                                     class="w-full bg-white rounded-lg shadow-md border border-secondary/25 overflow-hidden">
                                    <ul class="max-h-40 overflow-y-auto py-1 divide-y divide-secondary/10">
                                        <template x-for="(member, index) in filteredUsers" :key="member.id">
                                            <li>
                                                <button type="button"
                                                        x-on:mousedown.prevent="addMember(member)"
                                                        :class="index === activeIndex ? 'bg-accent/25' : 'hover:bg-accent/15'"
                                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 transition-colors">
                                                    <div class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 text-[10px] font-semibold"
                                                         x-text="member.initials"></div>
                                                    <div class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm font-medium text-primary" x-text="member.name"></span>
                                                        <span class="block truncate text-[10px] text-primary/50 uppercase tracking-wide" x-text="member.role_label"></span>
                                                    </div>
                                                    <span class="text-primary/45 flex-shrink-0">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                        <li x-show="filteredUsers.length === 0"
                                            class="px-3 py-2.5 text-xs text-primary/55"
                                            x-text="matchingAlreadySelectedCount > 0 ? 'Déjà sélectionné.' : 'Aucun collaborateur trouvé.'"></li>
                                    </ul>
                                </div>

                                <div x-show="selectedMembers.length > 0" style="display: none;" class="flex flex-wrap gap-1.5">
                                    <template x-for="member in selectedMembers" :key="member.id">
                                        <span class="inline-flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-full bg-accent/25 border border-secondary/30 text-xs text-primary">
                                            <span class="w-5 h-5 rounded-full bg-primary text-white text-[9px] font-semibold flex items-center justify-center flex-shrink-0"
                                                  x-text="member.initials"></span>
                                            <span class="font-medium truncate max-w-[160px]" x-text="member.name"></span>
                                            <button type="button"
                                                    x-on:click="removeMember(member.id)"
                                                    title="Retirer ce membre"
                                                    aria-label="Retirer ce membre"
                                                    class="text-primary/45 hover:text-red-600 transition-colors focus:outline-none">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                            </button>
                                        </span>
                                    </template>
                                </div>

                                <template x-for="member in selectedMembers" :key="'manage-input-' + member.id">
                                    <input type="hidden" name="participant_ids[]" :value="member.id">
                                </template>

                                <button type="submit"
                                        :disabled="selectedMembers.length === 0"
                                        class="w-full py-2 rounded-lg border border-secondary/30 bg-accent/15 text-primary text-xs font-semibold hover:bg-accent/30 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                    Ajouter au groupe
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-primary/45">Tous les collaborateurs actifs font déjà partie de ce groupe.</p>
                        @endif
                    @endif
                </div>

                <x-slot:footer>
                    <form method="POST" action="{{ route('discussions.conversations.leave', $selectedConversation) }}" class="w-full">
                        @csrf
                        <button type="submit" class="w-full py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50 text-xs font-semibold transition-colors">
                            Quitter le groupe
                        </button>
                    </form>
                </x-slot:footer>
            </x-modal>
        @endif

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
const normalizeMemberSearchText = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

const registerGroupMemberPicker = () => {
    if (!window.Alpine || window.__groupMemberPickerRegistered) return;
    window.__groupMemberPickerRegistered = true;

    window.Alpine.data('groupMemberPicker', function(users = [], initialIds = [], initialActive = true, followGroupMode = false) {
        return {
            users: Array.isArray(users) ? users : [],
            selectedIds: [],
            search: '',
            showDropdown: false,
            activeIndex: 0,
            pickerActive: Boolean(initialActive),
            followGroupMode: Boolean(followGroupMode),
            clientError: '',

            init() {
                const validIds = new Set(this.users.map((u) => Number(u.id)));
                const restored = [];
                (Array.isArray(initialIds) ? initialIds : []).forEach((id) => {
                    const numericId = Number(id);
                    if (validIds.has(numericId) && !restored.includes(numericId)) {
                        restored.push(numericId);
                    }
                });
                this.selectedIds = restored;
            },

            get selectedMembers() {
                return this.selectedIds
                    .map((id) => this.users.find((u) => Number(u.id) === Number(id)))
                    .filter(Boolean);
            },

            matchesSearch(member) {
                const terms = normalizeMemberSearchText(this.search).split(/\s+/).filter(Boolean);
                if (terms.length === 0) return false;
                const haystack = normalizeMemberSearchText(`${member.name} ${member.role_label} ${member.role}`);
                return terms.every((term) => haystack.includes(term));
            },

            get filteredUsers() {
                const terms = normalizeMemberSearchText(this.search).split(/\s+/).filter(Boolean);
                if (terms.length === 0) return [];
                const firstTerm = terms[0];

                return this.users
                    .filter((member) => !this.selectedIds.includes(Number(member.id)) && this.matchesSearch(member))
                    .sort((a, b) => {
                        const nameA = normalizeMemberSearchText(a.name);
                        const nameB = normalizeMemberSearchText(b.name);
                        const rankA = nameA.startsWith(firstTerm) ? 0 : (nameA.includes(' ' + firstTerm) ? 1 : 2);
                        const rankB = nameB.startsWith(firstTerm) ? 0 : (nameB.includes(' ' + firstTerm) ? 1 : 2);
                        return rankA - rankB || nameA.localeCompare(nameB);
                    });
            },

            get matchingAlreadySelectedCount() {
                if (!this.search.trim()) return 0;
                return this.users.filter((member) => this.selectedIds.includes(Number(member.id)) && this.matchesSearch(member)).length;
            },

            onModeChange(asGroup) {
                if (!this.followGroupMode) return;
                this.pickerActive = Boolean(asGroup);
                this.search = '';
                this.showDropdown = false;
                this.activeIndex = 0;
                this.clientError = '';
            },

            onSearchInput() {
                this.showDropdown = true;
                this.activeIndex = 0;
            },

            clearSearch() {
                this.search = '';
                this.activeIndex = 0;
                this.showDropdown = false;
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },

            addMember(member) {
                const id = Number(member?.id || 0);
                if (id > 0 && !this.selectedIds.includes(id)) {
                    this.selectedIds.push(id);
                }
                this.clientError = '';
                this.search = '';
                this.showDropdown = false;
                this.activeIndex = 0;
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },

            removeMember(id) {
                const numericId = Number(id);
                this.selectedIds = this.selectedIds.filter((existingId) => existingId !== numericId);
            },

            onKeydown(event) {
                const results = this.filteredUsers;
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    if (!this.showDropdown && this.search.trim()) {
                        this.showDropdown = true;
                    }
                    if (results.length > 0) {
                        this.activeIndex = (this.activeIndex + 1) % results.length;
                    }
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (results.length > 0) {
                        this.activeIndex = (this.activeIndex - 1 + results.length) % results.length;
                    }
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    const picked = results[this.activeIndex] || results[0];
                    if (this.showDropdown && picked) {
                        this.addMember(picked);
                    }
                } else if (event.key === 'Escape') {
                    if (this.showDropdown && this.search.trim().length > 0) {
                        event.preventDefault();
                        event.stopPropagation();
                        this.showDropdown = false;
                    }
                }
            },
        };
    });
};

document.addEventListener('alpine:init', registerGroupMemberPicker);
if (window.Alpine) {
    registerGroupMemberPicker();
}

const discussionState = {
    csrf: '{{ csrf_token() }}',
    indexUrl: '{{ route('discussions.index') }}',
    selectedConversationId: Number(document.getElementById('conversation-list')?.dataset.selectedConversationId || 0),
    deleteMessageUrl: '',
    deleteMessageId: null,
    deletingConversationIds: new Set(),
    deletingMessagePending: false,
};

document.querySelector('#new-discussion-modal form')?.addEventListener('submit', function(event) {
    const isGroupInput = this.querySelector('input[name="is_group"]');
    if (isGroupInput && isGroupInput.value === '1') {
        const activeMembers = this.querySelectorAll('input[name="participant_ids[]"]:not([disabled])');
        if (activeMembers.length === 0) {
            event.preventDefault();
            window.dispatchEvent(new CustomEvent('discussion-group-members-error', {
                detail: 'Sélectionne au moins un autre membre pour créer le groupe.',
            }));
            document.getElementById('group-member-search-input')?.focus();
        }
    }
});

window.openNewDiscussionModal = function(asGroup = false) {
    const modal = document.getElementById('new-discussion-modal');
    if (!modal) return;
    window.dispatchEvent(new CustomEvent('set-discussion-group-mode', { detail: Boolean(asGroup) }));
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeNewDiscussionModal = function() {
    const modal = document.getElementById('new-discussion-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.openManageGroupModal = function() {
    const modal = document.getElementById('manage-group-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeManageGroupModal = function() {
    const modal = document.getElementById('manage-group-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
};

@if($errors->hasAny(['title', 'participant_ids', 'participant_ids.*', 'participant_id', 'conversation']))
document.addEventListener('DOMContentLoaded', function() {
    window.openNewDiscussionModal({{ old('is_group') ? 'true' : 'false' }});
});
@endif

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
            return;
        }
        const groupModal = document.getElementById('manage-group-modal');
        if (groupModal && !groupModal.classList.contains('hidden')) {
            closeManageGroupModal();
            return;
        }
        const newModal = document.getElementById('new-discussion-modal');
        if (newModal && !newModal.classList.contains('hidden')) {
            closeNewDiscussionModal();
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
    const groupBadgeHtml = conversation.is_group
        ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-secondary/20 text-primary/80 text-[10px] font-medium flex-shrink-0">Groupe</span>'
        : '';
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
                        <p class="text-sm font-semibold text-primary truncate flex items-center gap-1.5">
                            <span class="truncate">${escapeHtml(conversation.title || 'Discussion')}</span>
                            ${groupBadgeHtml}
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
                <p class="text-primary/40 text-sm mt-1">Ou demarre une nouvelle discussion ou un groupe de travail.</p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <button type="button"
                            onclick="openNewDiscussionModal(false)"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-surface-dark transition-colors">
                        <i data-lucide="message-square-plus" class="w-3.5 h-3.5"></i>
                        Nouvelle discussion
                    </button>
                    <button type="button"
                            onclick="openNewDiscussionModal(true)"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-secondary/30 bg-white text-primary text-xs font-semibold hover:bg-accent/20 transition-colors">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                        Créer un groupe
                    </button>
                </div>
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
