<?php

namespace App\Http\Controllers;

use App\Messaging\CommerceOutbox;
use App\Models\Conversation;
use App\Models\Merchant;
use App\Saas\Exceptions\SubscriptionLimitException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request, Merchant $merchant): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,closed'],
            'assigned' => ['nullable', 'in:mine,unassigned'],
            'unread' => ['nullable', 'in:1'],
        ]);
        $query = $merchant->conversations()->with(['customer', 'assignee', 'channel'])
            ->orderByDesc('last_message_at')->orderByDesc('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (($filters['assigned'] ?? null) === 'mine') {
            $query->where('assigned_to', $request->user()->id);
        } elseif (($filters['assigned'] ?? null) === 'unassigned') {
            $query->whereNull('assigned_to');
        }
        if (($filters['unread'] ?? null) === '1') {
            $query->where('unread_count', '>', 0);
        }

        return view('merchant.inbox.index', [
            'merchant' => $merchant,
            'conversations' => $query->paginate(25)->withQueryString(),
            'unread' => $merchant->conversations()->where('unread_count', '>', 0)->count(),
        ]);
    }

    public function show(Request $request, Merchant $merchant, Conversation $conversation): View
    {
        $conversation = $merchant->conversations()->with(['customer', 'assignee', 'channel'])
            ->whereKey($conversation->id)->firstOrFail();

        return view('merchant.inbox.show', [
            'merchant' => $merchant,
            'conversation' => $conversation,
            'messages' => $conversation->messages()->with('outbound')
                ->latest('id')->paginate(40)->withQueryString(),
            'members' => $merchant->users()->orderBy('name')->get(),
            'isManager' => $this->isManager($request, $merchant),
            'canReply' => $this->mayReply($request, $merchant, $conversation),
            'automationSettings' => $merchant->automationSetting,
            'followups' => $merchant->automationFollowups()->where('conversation_id', $conversation->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function markRead(Merchant $merchant, Conversation $conversation): RedirectResponse
    {
        $conversation = $merchant->conversations()->whereKey($conversation->id)->firstOrFail();
        $conversation->update(['unread_count' => 0]);

        return back()->with('status', 'Conversation marked read.');
    }

    public function reply(Request $request, Merchant $merchant, Conversation $conversation, CommerceOutbox $outbox): RedirectResponse
    {
        $conversation = $merchant->conversations()->with(['customer', 'channel'])
            ->whereKey($conversation->id)->firstOrFail();

        abort_unless($this->mayReply($request, $merchant, $conversation), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        if (config('commerce.messaging_driver') !== 'relaywa' || $conversation->channel?->status !== 'connected') {
            throw ValidationException::withMessages(['body' => 'Messaging is disabled or the channel is disconnected. No message was queued.']);
        }
        if ($conversation->status === 'closed') {
            throw ValidationException::withMessages(['body' => 'Reopen the conversation before replying.']);
        }

        // Dedicated key per intentional submit; no repeated send on accidental
        // double-submit within one request, and the outbox never retries unknown sends.
        $id = (string) Str::uuid();
        try {
            $outbox->queueText(
                $merchant, $conversation->channel, $conversation->customer->phone,
                $data['body'], 'staff-'.$conversation->id.'-'.$id
            );
        } catch (SubscriptionLimitException $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }

        return back()->with('status', 'Reply queued. Check delivery status in the thread.');
    }

    public function assign(Request $request, Merchant $merchant, Conversation $conversation): RedirectResponse
    {
        $conversation = $merchant->conversations()->whereKey($conversation->id)->firstOrFail();
        $data = $request->validate([
            'assigned_to' => [
                'nullable', 'integer', Rule::exists('merchant_user', 'user_id')->where('merchant_id', $merchant->id),
            ],
        ]);
        $conversation->update(['assigned_to' => $data['assigned_to'] ?? null]);

        return back()->with('status', 'Team assignment updated.');
    }

    public function changeStatus(Request $request, Merchant $merchant, Conversation $conversation): RedirectResponse
    {
        $conversation = $merchant->conversations()->whereKey($conversation->id)->firstOrFail();
        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $conversation->update(['status' => $data['status']]);

        return back()->with('status', 'Conversation status updated.');
    }

    private function isManager(Request $request, Merchant $merchant): bool
    {
        return $request->user()->merchants()->whereKey($merchant->id)
            ->wherePivotIn('role', ['owner', 'manager'])->exists();
    }

    private function mayReply(Request $request, Merchant $merchant, Conversation $conversation): bool
    {
        return $this->isManager($request, $merchant)
            || $conversation->assigned_to === null
            || $conversation->assigned_to === $request->user()->id;
    }
}
