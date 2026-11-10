<?php

namespace App\Http\Controllers;

use App\Models\DiscussionConversation;
use App\Models\DiscussionMessage;
use App\Models\DiscussionMessageDeletion;
use App\Models\User;
use App\Notifications\DiscussionMessageReceived;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiscussionController extends Controller
{
    public function __construct(private Notifier $notifier)
    {
    }

    private function isDiscussionSchemaReady(): bool
    {
        return Schema::hasTable('discussion_messages')
            && Schema::hasTable('discussion_conversations')
            && Schema::hasTable('discussion_conversation_user')
            && Schema::hasColumn('discussion_messages', 'conversation_id')
            && Schema::hasColumn('discussion_conversation_user', 'last_read_at')
            && Schema::hasColumn('discussion_conversation_user', 'archived_at')
            && Schema::hasColumn('discussion_conversation_user', 'deleted_at');
    }

    private function hasClearedCutoffSupport(): bool
    {
        return Schema::hasColumn('discussion_conversation_user', 'cleared_after_message_id');
    }

    private function hasMessageDeletionSupport(): bool
    {
        return Schema::hasTable('discussion_message_deletions')
            && Schema::hasColumn('discussion_messages', 'deleted_for_all_at');
    }

    private function clearedCutoffsMap(int $userId, array $conversationIds): Collection
    {
        if (empty($conversationIds) || !$this->hasClearedCutoffSupport()) {
            return collect();
        }

        return DB::table('discussion_conversation_user')
            ->where('user_id', $userId)
            ->whereIn('discussion_conversation_id', $conversationIds)
            ->pluck('cleared_after_message_id', 'discussion_conversation_id');
    }

    private function visibleMessagesQuery(int $conversationId, int $userId, ?int $clearedAfterMessageId = null)
    {
        $query = DiscussionMessage::query()
            ->where('conversation_id', $conversationId);

        if ($clearedAfterMessageId !== null && $clearedAfterMessageId > 0) {
            $query->where('id', '>', $clearedAfterMessageId);
        }

        if ($this->hasMessageDeletionSupport()) {
            $query->whereNull('deleted_for_all_at')
                ->whereNotExists(function ($sub) use ($userId) {
                    $sub->selectRaw('1')
                        ->from('discussion_message_deletions')
                        ->whereColumn('discussion_message_deletions.discussion_message_id', 'discussion_messages.id')
                        ->where('discussion_message_deletions.user_id', $userId);
                });
        }

        return $query;
    }

    public function index(Request $request): View
    {
        if (!$this->isDiscussionSchemaReady()) {
            return view('discussions.index', [
                'conversations' => collect(),
                'messages' => collect(),
                'selectedConversation' => null,
                'availableUsers' => collect(),
                'unreadCounts' => [],
                'totalUnread' => 0,
                'dbNotReady' => true,
            ]);
        }

        $user = Auth::user();
        $conversations = $this->getVisibleConversations($user);

        $selectedConversation = null;
        $requestedConversationId = (int) $request->integer('conversation', 0);
        if ($requestedConversationId > 0) {
            $selectedConversation = $conversations->firstWhere('id', $requestedConversationId);
        }

        if ($selectedConversation) {
            $this->markConversationAsRead($selectedConversation->id, $user->id);
            $selectedConversation->setRelation('pivot', tap($selectedConversation->pivot, function ($pivot) {
                $pivot->last_read_at = now();
            }));
        }

        $messages = collect();
        if ($selectedConversation) {
            $cutoff = (int) ($this->clearedCutoffsMap($user->id, [$selectedConversation->id])
                ->get($selectedConversation->id) ?? 0);

            $messages = $this->visibleMessagesQuery(
                $selectedConversation->id,
                $user->id,
                $cutoff > 0 ? $cutoff : null
            )
                ->with('user')
                ->orderBy('id')
                ->get();
        }

        $availableUsers = User::query()
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $unreadData = $this->computeUnreadData($conversations, $user->id);

        return view('discussions.index', [
            'conversations' => $conversations,
            'messages' => $messages,
            'selectedConversation' => $selectedConversation,
            'availableUsers' => $availableUsers,
            'unreadCounts' => $unreadData['counts'],
            'totalUnread' => $unreadData['total'],
            'dbNotReady' => false,
        ]);
    }

    public function conversationsList(): JsonResponse
    {
        if (!$this->isDiscussionSchemaReady()) {
            return response()->json([
                'ok' => false,
                'conversations' => [],
                'unread_counts' => [],
                'total_unread' => 0,
            ]);
        }

        $user = Auth::user();
        $conversations = $this->getVisibleConversations($user);
        $unreadData = $this->computeUnreadData($conversations, $user->id);

        $serialized = $conversations
            ->map(fn (DiscussionConversation $conversation) => $this->serializeConversationSummary(
                $conversation,
                $user->id,
                (int) ($unreadData['counts'][$conversation->id] ?? 0),
            ))
            ->values();

        return response()->json([
            'ok' => true,
            'conversations' => $serialized,
            'unread_counts' => $unreadData['counts'],
            'total_unread' => $unreadData['total'],
        ]);
    }

    public function createConversation(Request $request): RedirectResponse
    {
        if (!$this->isDiscussionSchemaReady()) {
            return redirect()->route('discussions.index')->withErrors([
                'conversation' => 'Le module discussion n est pas encore initialise. Lance les migrations.',
            ]);
        }

        $user = Auth::user();
        $validated = $request->validate([
            'is_group' => ['nullable', 'boolean'],
            'title' => ['required_if:is_group,true', 'nullable', 'string', 'max:160'],
            'participant_ids' => ['required_if:is_group,true', 'array'],
            'participant_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'participant_id' => [
                'required_without:is_group',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ]);

        $isGroup = $request->boolean('is_group');

        if ($isGroup) {
            $participantIds = collect($validated['participant_ids'] ?? [])
                ->reject(fn ($id) => (int) $id === $user->id)
                ->unique()
                ->values()
                ->toArray();

            if (empty($participantIds)) {
                return redirect()->route('discussions.index')->withErrors([
                    'conversation' => 'Un groupe doit contenir au moins un autre membre.',
                ]);
            }

            $conversation = DiscussionConversation::create([
                'title' => trim($validated['title']),
                'is_group' => true,
                'created_by' => $user->id,
            ]);

            $syncData = [
                $user->id => ['last_read_at' => now(), 'is_admin' => true, 'archived_at' => null, 'deleted_at' => null],
            ];
            foreach ($participantIds as $pId) {
                $syncData[$pId] = ['last_read_at' => null, 'is_admin' => false, 'archived_at' => null, 'deleted_at' => null];
            }
            $conversation->participants()->sync($syncData);

            return redirect()->route('discussions.index', ['conversation' => $conversation->id]);
        }

        $participantId = (int) $validated['participant_id'];
        if ($participantId === $user->id) {
            return redirect()->route('discussions.index')->withErrors([
                'conversation' => 'Tu ne peux pas creer une discussion avec toi-meme.',
            ]);
        }

        $candidate = $user->discussionConversations()
            ->with('participants:id')
            ->where('is_group', false)
            ->get()
            ->first(function ($conversation) use ($user, $participantId) {
                $ids = $conversation->participants->pluck('id')->push($user->id)->unique()->values();
                return $ids->count() === 2 && $ids->contains($participantId);
            });

        if ($candidate) {
            $candidate->participants()->updateExistingPivot($user->id, [
                'archived_at' => null,
                'deleted_at' => null,
                'last_read_at' => now(),
            ]);

            return redirect()->route('discussions.index', ['conversation' => $candidate->id]);
        }

        $participant = User::findOrFail($participantId);
        $conversation = DiscussionConversation::create([
            'title' => null,
            'is_group' => false,
            'created_by' => $user->id,
        ]);

        $conversation->participants()->sync([
            $user->id => ['last_read_at' => now(), 'is_admin' => true, 'archived_at' => null, 'deleted_at' => null],
            $participant->id => ['last_read_at' => null, 'is_admin' => false, 'archived_at' => null, 'deleted_at' => null],
        ]);

        return redirect()->route('discussions.index', ['conversation' => $conversation->id]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (!$this->isDiscussionSchemaReady()) {
            return redirect()->route('discussions.index')->withErrors([
                'body' => 'Le module discussion n est pas encore initialise. Lance les migrations.',
            ]);
        }

        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:discussion_conversations,id'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $body = trim($validated['body']);
        if ($body === '') {
            return redirect()->route('discussions.index', ['conversation' => $validated['conversation_id']])->withErrors([
                'body' => 'Le message ne peut pas etre vide.',
            ]);
        }

        $conversation = DiscussionConversation::findOrFail((int) $validated['conversation_id']);
        $userId = Auth::id();

        if (!$conversation->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'Cette conversation ne t appartient pas.');
        }

        // If user had deleted/archive state, restore visibility when sending again.
        $conversation->participants()->updateExistingPivot($userId, [
            'deleted_at' => null,
            'archived_at' => null,
        ]);

        $message = DiscussionMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'body' => $body,
        ]);

        // Si un participant avait supprime la conversation de son espace,
        // un nouveau message la fait reapparaitre chez lui automatiquement
        // (tout en conservant son historique anterieur efface via cleared_after_message_id).
        DB::table('discussion_conversation_user')
            ->where('discussion_conversation_id', $conversation->id)
            ->where('user_id', '!=', $userId)
            ->update([
                'deleted_at' => null,
                'archived_at' => null,
            ]);

        // Prevenir les autres participants : sans ca, un message n'est vu que
        // par ceux qui ont la page discussion ouverte au bon moment.
        $this->notifier->send(
            $conversation->participants()->where('users.id', '!=', $userId)->get(),
            new DiscussionMessageReceived($conversation, $message, Auth::user()?->name ?? 'Un collegue')
        );

        if ($request->expectsJson() || $request->wantsJson()) {
            $conversations = $this->getVisibleConversations(Auth::user());
            $unreadData = $this->computeUnreadData($conversations, $userId);
            return response()->json([
                'ok' => true,
                'message' => $this->serializeMessage($message->load('user'), $userId),
                'unread_counts' => $unreadData['counts'],
                'total_unread' => $unreadData['total'],
            ]);
        }

        return redirect()->route('discussions.index', ['conversation' => $conversation->id]);
    }

    public function poll(Request $request, DiscussionConversation $conversation): JsonResponse
    {
        if (!$this->isDiscussionSchemaReady()) {
            return response()->json([
                'ok' => false,
                'message' => 'Module discussion non initialise',
            ], 422);
        }

        $userId = Auth::id();
        $pivot = DB::table('discussion_conversation_user')
            ->where('discussion_conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->first();

        if (!$pivot) {
            abort(403, 'Cette conversation ne t appartient pas.');
        }

        if ($pivot->deleted_at !== null || $pivot->archived_at !== null) {
            $user = Auth::user();
            $conversations = $this->getVisibleConversations($user);
            $unreadData = $this->computeUnreadData($conversations, $userId);

            return response()->json([
                'ok' => true,
                'conversation_hidden' => true,
                'messages' => [],
                'removed_message_ids' => [],
                'unread_counts' => $unreadData['counts'],
                'total_unread' => $unreadData['total'],
            ]);
        }

        $afterId = (int) $request->integer('after_id', 0);
        $cutoff = $this->hasClearedCutoffSupport() ? (int) ($pivot->cleared_after_message_id ?? 0) : 0;
        $effectiveAfterId = max($afterId, $cutoff);

        $messages = $this->visibleMessagesQuery($conversation->id, $userId)
            ->where('id', '>', $effectiveAfterId)
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscussionMessage $message) => $this->serializeMessage($message, $userId))
            ->values();

        $removedMessageIds = [];
        if ($afterId > 0) {
            $hasMsgDel = $this->hasMessageDeletionSupport();
            if ($cutoff > 0 || $hasMsgDel) {
                $removedMessageIds = DiscussionMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('id', '<=', $afterId)
                    ->where(function ($q) use ($cutoff, $hasMsgDel, $userId) {
                        if ($cutoff > 0) {
                            $q->where('id', '<=', $cutoff);
                        }
                        if ($hasMsgDel) {
                            $method = $cutoff > 0 ? 'orWhereNotNull' : 'whereNotNull';
                            $q->{$method}('deleted_for_all_at');
                            $q->orWhereExists(function ($sub) use ($userId) {
                                $sub->selectRaw('1')
                                    ->from('discussion_message_deletions')
                                    ->whereColumn('discussion_message_deletions.discussion_message_id', 'discussion_messages.id')
                                    ->where('discussion_message_deletions.user_id', $userId);
                            });
                        }
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            }
        }

        $user = Auth::user();
        $this->markConversationAsRead($conversation->id, $user->id);
        $conversations = $this->getVisibleConversations($user);
        $unreadData = $this->computeUnreadData($conversations, $user->id);

        return response()->json([
            'ok' => true,
            'conversation_hidden' => false,
            'messages' => $messages,
            'removed_message_ids' => $removedMessageIds,
            'unread_counts' => $unreadData['counts'],
            'total_unread' => $unreadData['total'],
        ]);
    }

    public function unreadSummary(): JsonResponse
    {
        if (!$this->isDiscussionSchemaReady()) {
            return response()->json([
                'ok' => false,
                'total_unread' => 0,
                'has_unread' => false,
                'unread_counts' => [],
            ]);
        }

        $user = Auth::user();
        $conversations = $this->getVisibleConversations($user);
        $unreadData = $this->computeUnreadData($conversations, $user->id);

        return response()->json([
            'ok' => true,
            'total_unread' => $unreadData['total'],
            'has_unread' => $unreadData['total'] > 0,
            'unread_counts' => $unreadData['counts'],
        ]);
    }

    public function archiveConversation(Request $request, DiscussionConversation $conversation): RedirectResponse|JsonResponse
    {
        $userId = Auth::id();
        if (!$conversation->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'Cette conversation ne t appartient pas.');
        }

        $conversation->participants()->updateExistingPivot($userId, [
            'archived_at' => Carbon::now(),
            'deleted_at' => null,
        ]);

        if ($request->expectsJson() || $request->wantsJson()) {
            $conversations = $this->getVisibleConversations(Auth::user());
            $unreadData = $this->computeUnreadData($conversations, $userId);

            return response()->json([
                'ok' => true,
                'action' => 'archived',
                'conversation_id' => $conversation->id,
                'unread_counts' => $unreadData['counts'],
                'total_unread' => $unreadData['total'],
            ]);
        }

        return redirect()->route('discussions.index')->with('success', 'Conversation archivee.');
    }

    public function destroyConversation(Request $request, DiscussionConversation $conversation): RedirectResponse|JsonResponse
    {
        $userId = Auth::id();
        if (!$conversation->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'Cette conversation ne t appartient pas.');
        }

        // La suppression d'une conversation est directe et strictement personnelle :
        // elle ne supprime jamais le fil chez les autres participants.
        $now = Carbon::now();
        $pivotUpdate = [
            'deleted_at' => $now,
            'archived_at' => null,
            'last_read_at' => $now,
        ];

        if ($this->hasClearedCutoffSupport()) {
            $latestMessageId = (int) DiscussionMessage::query()
                ->where('conversation_id', $conversation->id)
                ->max('id');
            $pivotUpdate['cleared_after_message_id'] = $latestMessageId > 0 ? $latestMessageId : null;
        }

        $conversation->participants()->updateExistingPivot($userId, $pivotUpdate);

        if ($request->expectsJson() || $request->wantsJson()) {
            $conversations = $this->getVisibleConversations(Auth::user());
            $unreadData = $this->computeUnreadData($conversations, $userId);

            return response()->json([
                'ok' => true,
                'action' => 'deleted_for_me',
                'removed_conversation_id' => $conversation->id,
                'unread_counts' => $unreadData['counts'],
                'total_unread' => $unreadData['total'],
            ]);
        }

        return redirect()->route('discussions.index')->with('success', 'Conversation supprimee.');
    }

    public function destroyMessage(Request $request, DiscussionMessage $message): RedirectResponse|JsonResponse
    {
        if (!$this->isDiscussionSchemaReady() || !$this->hasMessageDeletionSupport()) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Module discussion non initialise',
                ], 422);
            }

            return redirect()->route('discussions.index')->withErrors([
                'message' => 'Le module discussion n est pas encore initialise. Lance les migrations.',
            ]);
        }

        $userId = Auth::id();
        $conversation = $message->conversation;
        if (!$conversation || !$conversation->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'Ce message ne t appartient pas.');
        }

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['me', 'all'])],
        ]);
        $mode = $validated['mode'] ?? 'me';

        if ($mode === 'all' && (int) $message->user_id !== (int) $userId) {
            abort(403, 'Tu ne peux supprimer pour tous que tes propres messages.');
        }

        if ($mode === 'all') {
            $message->forceFill([
                'deleted_for_all_at' => Carbon::now(),
                'deleted_for_all_by' => $userId,
            ])->save();
            $action = 'deleted_for_all';
            $flash = 'Message supprime pour tous.';
        } else {
            DiscussionMessageDeletion::firstOrCreate([
                'discussion_message_id' => $message->id,
                'user_id' => $userId,
            ]);
            $action = 'deleted_for_me';
            $flash = 'Message supprime pour toi.';
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            $conversations = $this->getVisibleConversations(Auth::user());
            $unreadData = $this->computeUnreadData($conversations, $userId);

            return response()->json([
                'ok' => true,
                'action' => $action,
                'removed_message_id' => $message->id,
                'unread_counts' => $unreadData['counts'],
                'total_unread' => $unreadData['total'],
            ]);
        }

        return redirect()->route('discussions.index', ['conversation' => $conversation->id])->with('success', $flash);
    }

    private function getVisibleConversations(User $user): Collection
    {
        $conversations = $user->discussionConversations()
            ->wherePivotNull('archived_at')
            ->wherePivotNull('deleted_at')
            ->with(['participants'])
            ->get()
            ->filter(function (DiscussionConversation $conversation) use ($user) {
                // Hide stale conversations with no counterpart and no explicit title.
                if (!empty($conversation->title)) {
                    return true;
                }

                return $conversation->participants->contains(fn ($participant) => (int) $participant->id !== (int) $user->id);
            })
            ->values();

        if ($conversations->isEmpty()) {
            return $conversations;
        }

        $conversationIds = $conversations->pluck('id')->all();
        $hasCleared = $this->hasClearedCutoffSupport();
        $hasMsgDel = $this->hasMessageDeletionSupport();

        $latestQuery = DB::table('discussion_messages as dm')
            ->join('discussion_conversation_user as dcu', function ($join) use ($user) {
                $join->on('dcu.discussion_conversation_id', '=', 'dm.conversation_id')
                    ->where('dcu.user_id', '=', $user->id);
            })
            ->whereIn('dm.conversation_id', $conversationIds);

        if ($hasCleared) {
            $latestQuery->where(function ($q) {
                $q->whereNull('dcu.cleared_after_message_id')
                    ->orWhereColumn('dm.id', '>', 'dcu.cleared_after_message_id');
            });
        }

        if ($hasMsgDel) {
            $latestQuery->whereNull('dm.deleted_for_all_at')
                ->whereNotExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('discussion_message_deletions as dmd')
                        ->whereColumn('dmd.discussion_message_id', 'dm.id')
                        ->where('dmd.user_id', $user->id);
                });
        }

        $latestMessageIds = $latestQuery
            ->groupBy('dm.conversation_id')
            ->selectRaw('dm.conversation_id, MAX(dm.id) as latest_id')
            ->pluck('latest_id', 'conversation_id');

        $latestMessages = $latestMessageIds->isNotEmpty()
            ? DiscussionMessage::query()
                ->whereIn('id', $latestMessageIds->values()->all())
                ->get()
                ->keyBy('id')
            : collect();

        return $conversations
            ->each(function (DiscussionConversation $conversation) use ($latestMessageIds, $latestMessages) {
                $latestId = $latestMessageIds->get($conversation->id);
                $latest = $latestId ? $latestMessages->get((int) $latestId) : null;
                $conversation->setRelation('messages', $latest ? collect([$latest]) : collect());
            })
            ->sortByDesc(function (DiscussionConversation $conversation) {
                $lastMessage = $conversation->messages->first();
                return optional($lastMessage)->created_at ?? $conversation->updated_at;
            })
            ->values();
    }

    private function serializeConversationSummary(DiscussionConversation $conversation, int $currentUserId, int $unread): array
    {
        $other = $conversation->participants->firstWhere('id', '!=', $currentUserId);
        $title = $conversation->title ?: ($other?->name ?? 'Discussion interne');
        $last = $conversation->messages->first();

        return [
            'id' => $conversation->id,
            'title' => $title,
            'initials' => mb_strtoupper(mb_substr($title, 0, 2)),
            'last_body' => $last?->body ?? 'Aucun message pour le moment',
            'last_time' => $last?->created_at?->format('H:i') ?? '',
            'unread' => $unread,
            'url' => route('discussions.index', ['conversation' => $conversation->id]),
            'archive_url' => route('discussions.conversations.archive', $conversation),
            'delete_url' => route('discussions.conversations.destroy', $conversation),
        ];
    }

    private function serializeMessage(DiscussionMessage $message, ?int $currentUserId = null): array
    {
        $currentUserId ??= Auth::id();

        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name ?? 'Utilisateur',
            'body' => $message->body,
            'time' => optional($message->created_at)->locale('fr')->isoFormat('HH:mm'),
            'delete_url' => route('discussions.messages.destroy', $message),
            'can_delete_for_all' => (int) $message->user_id === (int) $currentUserId,
        ];
    }

    private function markConversationAsRead(int $conversationId, int $userId): void
    {
        DB::table('discussion_conversation_user')
            ->where('discussion_conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => Carbon::now()]);
    }

    private function computeUnreadData(Collection $conversations, int $userId): array
    {
        $counts = [];
        $total = 0;

        if ($conversations->isEmpty()) {
            return ['counts' => [], 'total' => 0];
        }

        $clearedCutoffs = $this->clearedCutoffsMap($userId, $conversations->pluck('id')->all());

        foreach ($conversations as $conversation) {
            $cutoff = (int) ($clearedCutoffs->get($conversation->id) ?? 0);
            $query = $this->visibleMessagesQuery(
                $conversation->id,
                $userId,
                $cutoff > 0 ? $cutoff : null
            )->where('user_id', '!=', $userId);

            $lastReadAt = $conversation->pivot?->last_read_at;
            if ($lastReadAt) {
                $query->where('created_at', '>', $lastReadAt);
            }

            $count = $query->count();
            $counts[$conversation->id] = $count;
            $total += $count;
        }

        return ['counts' => $counts, 'total' => $total];
    }
}
