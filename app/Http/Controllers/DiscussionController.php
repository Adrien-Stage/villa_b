<?php

namespace App\Http\Controllers;

use App\Models\DiscussionConversation;
use App\Models\DiscussionMessage;
use App\Models\User;
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
            $messages = DiscussionMessage::query()
                ->with('user')
                ->where('conversation_id', $selectedConversation->id)
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

        // Si un participant avait supprime la conversation "juste pour moi",
        // un nouveau message la fait reapparaitre chez lui automatiquement.
        DB::table('discussion_conversation_user')
            ->where('discussion_conversation_id', $conversation->id)
            ->where('user_id', '!=', $userId)
            ->update([
                'deleted_at' => null,
                'archived_at' => null,
            ]);

        if ($request->expectsJson() || $request->wantsJson()) {
            $conversations = $this->getVisibleConversations(Auth::user());
            $unreadData = $this->computeUnreadData($conversations, $userId);
            return response()->json([
                'ok' => true,
                'message' => $this->serializeMessage($message->load('user')),
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
        if (!$conversation->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'Cette conversation ne t appartient pas.');
        }

        $afterId = (int) $request->integer('after_id', 0);
        $messages = DiscussionMessage::query()
            ->with('user')
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get()
            ->map(fn (DiscussionMessage $message) => $this->serializeMessage($message))
            ->values();

        $user = Auth::user();
        $this->markConversationAsRead($conversation->id, $user->id);
        $conversations = $this->getVisibleConversations($user);
        $unreadData = $this->computeUnreadData($conversations, $user->id);

        return response()->json([
            'ok' => true,
            'messages' => $messages,
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
            return response()->json(['ok' => true, 'action' => 'archived', 'conversation_id' => $conversation->id]);
        }

        return redirect()->route('discussions.index')->with('success', 'Conversation archivee.');
    }

    public function destroyConversation(Request $request, DiscussionConversation $conversation): RedirectResponse|JsonResponse
    {
        $userId = Auth::id();
        if (!$conversation->participants()->where('users.id', $userId)->exists()) {
            abort(403, 'Cette conversation ne t appartient pas.');
        }

        $mode = $request->input('mode', 'me');
        if (!in_array($mode, ['me', 'all'], true)) {
            $mode = 'me';
        }

        if ($mode === 'all') {
            $conversationId = $conversation->id;
            $conversation->delete();

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'ok' => true,
                    'action' => 'deleted_for_all',
                    'removed_conversation_id' => $conversationId,
                ]);
            }

            return redirect()->route('discussions.index')->with('success', 'Conversation supprimee pour tous.');
        }

        $conversation->participants()->updateExistingPivot($userId, [
            'deleted_at' => Carbon::now(),
            'archived_at' => null,
        ]);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'action' => 'deleted_for_me',
                'removed_conversation_id' => $conversation->id,
            ]);
        }

        return redirect()->route('discussions.index')->with('success', 'Conversation supprimee pour toi.');
    }

    private function getVisibleConversations(User $user): Collection
    {
        return $user->discussionConversations()
            ->wherePivotNull('archived_at')
            ->wherePivotNull('deleted_at')
            ->with([
                'participants',
                'messages' => fn ($q) => $q->latest('id')->take(1),
            ])
            ->get()
            ->filter(function (DiscussionConversation $conversation) use ($user) {
                // Hide stale conversations with no counterpart and no explicit title.
                if (!empty($conversation->title)) {
                    return true;
                }

                return $conversation->participants->contains(fn ($participant) => (int) $participant->id !== (int) $user->id);
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
            'initials' => strtoupper(substr($title, 0, 2)),
            'last_body' => $last?->body ?? 'Aucun message pour le moment',
            'last_time' => $last?->created_at?->format('H:i') ?? '',
            'unread' => $unread,
            'url' => route('discussions.index', ['conversation' => $conversation->id]),
            'archive_url' => route('discussions.conversations.archive', $conversation),
            'delete_url' => route('discussions.conversations.destroy', $conversation),
        ];
    }

    private function serializeMessage(DiscussionMessage $message): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name ?? 'Utilisateur',
            'body' => $message->body,
            'time' => optional($message->created_at)->locale('fr')->isoFormat('HH:mm'),
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

        foreach ($conversations as $conversation) {
            $query = DiscussionMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $userId);

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
