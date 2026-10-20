<?php

namespace App\Http\Controllers;

use App\Messaging\RelayWaClient;
use App\Models\Merchant;
use App\Models\MessagingChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RelayWaChannelController extends Controller
{
    public function index(Merchant $merchant): View
    {
        return view('merchant.channels.index', [
            'merchant' => $merchant,
            'channels' => $merchant->messagingChannels()->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, Merchant $merchant, RelayWaClient $client): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'session_id' => ['required', 'uuid', Rule::unique('messaging_channels', 'external_account_id')->where('provider', 'relaywa')],
            'api_key' => ['required', 'string', 'starts_with:rw_session_', 'max:500'],
        ]);

        // Use only the RelayWA server origin configured by our operator.
        // A session-scoped API credential is mandatory; never accept a platform-wide key here.
        $channel = new MessagingChannel([
            'merchant_id' => $merchant->id,
            'provider' => 'relaywa',
            'external_account_id' => $data['session_id'],
            'credentials' => ['api_key' => $data['api_key']],
        ]);

        try {
            $session = $client->session($channel);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'api_key' => 'Unable to verify this session with RelayWA. Check the session-scoped key, its read permission and server connection.',
            ]);
        }

        $status = $session['status'] ?? 'disconnected';
        $allowed = ['pending', 'need_scan', 'connecting', 'connected', 'disconnected', 'reconnecting', 'logged_out', 'expired', 'error'];
        if (! in_array($status, $allowed, true)) {
            $status = 'error';
        }

        $merchant->messagingChannels()->create([
            'provider' => 'relaywa',
            'public_id' => (string) Str::uuid(),
            'external_account_id' => $data['session_id'],
            'display_name' => $data['display_name'],
            'credentials' => ['api_key' => $data['api_key'], 'webhook_secret' => $data['webhook_secret']],
            'status' => $status,
            'status_checked_at' => now(),
        ]);

        return redirect()->route('merchant.channels.index', $merchant)
            ->with('status', 'RelayWA session verified. Create a RelayWA webhook with the displayed URL, then save its signing secret below.');
    }

    public function setWebhookSecret(Request $request, Merchant $merchant, MessagingChannel $channel): RedirectResponse
    {
        $channel = $merchant->messagingChannels()->whereKey($channel->id)->firstOrFail();
        abort_unless($channel->provider === 'relaywa', 404);
        $data = $request->validate(['webhook_secret' => ['required', 'string', 'min:20', 'max:255']]);
        $credentials = $channel->credentials ?? [];
        $credentials['webhook_secret'] = $data['webhook_secret'];
        $channel->update(['credentials' => $credentials]);

        return back()->with('status', 'Webhook signing secret saved. RelayWA webhooks can now be verified.');
    }

    public function destroy(Merchant $merchant, MessagingChannel $channel): RedirectResponse
    {
        $channel = $merchant->messagingChannels()->whereKey($channel->id)->firstOrFail();
        abort_unless($channel->provider === 'relaywa', 404);

        // Detach only our mapping; never log the merchant out of RelayWA.
        $channel->delete();

        return back()->with('status', 'RelayWA connection detached. Disable the corresponding RelayWA webhook endpoint separately.');
    }
}
