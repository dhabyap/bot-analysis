<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    /**
     * Handle incoming Meta Pixel Webhooks.
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();
            
            // Extract some basic info assuming standard Meta Conversions API mapping
            // Adjust exactly to match the payload you send from the client/pixel
            $eventName = $payload['event_name'] ?? 'Unknown';
            $source = $payload['event_source_url'] ?? null;
            
            // Extract value if it is a purchase or valued event
            $value = null;
            if (isset($payload['custom_data']['value'])) {
                $value = $payload['custom_data']['value'];
            }

            // Inser to DB using Query Builder for speed (or Eloquent without hydrating if model exists)
            DB::table('meta_events')->insert([
                'event_name' => $eventName,
                'source' => $source,
                'value' => $value,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Real-time Telegram Alert for Purchases
            if (strtolower($eventName) === 'purchase') {
                $this->sendTelegramAlert($eventName, $value, $source);
            }

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Meta Webhook Error: ' . $e->getMessage());
            // Always return 200 to webhooks so Meta doesn't retry infinitely on logic errors
            return response()->json(['status' => 'error'], 200); 
        }
    }

    /**
     * Send synchronous alert to Telegram via cURL/HTTP Client.
     */
    private function sendTelegramAlert($eventName, $value, $source)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_ADMIN_ID');

        if (!$botToken || !$chatId) {
            return;
        }

        $formattedValue = $value ? 'Rp ' . number_format($value, 0, ',', '.') : 'Unknown Value';
        
        $message = "🚨 *NEW HIGH-VALUE EVENT* 🚨\n\n";
        $message .= "🛒 *Event:* {$eventName}\n";
        $message .= "💰 *Value:* {$formattedValue}\n";
        $message .= "🔗 *Source:* {$source}\n\n";
        $message .= "#TokoZeClub_Bot";

        Http::timeout(5) // Ensure quick timeout to not block
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);
    }
}
