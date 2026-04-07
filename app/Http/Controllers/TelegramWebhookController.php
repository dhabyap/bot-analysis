<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming commands/messages from Telegram Admin (Webhook).
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();

            // Validate payload structure
            if (!isset($payload['message']['text']) || !isset($payload['message']['chat']['id'])) {
                return response()->json(['status' => 'ignored'], 200);
            }

            $chatId = $payload['message']['chat']['id'];
            $text = $payload['message']['text'];

            $adminId = env('TELEGRAM_ADMIN_ID');

            // Only allow designated Admin
            if ((string)$chatId !== (string)$adminId) {
                return response()->json(['status' => 'unauthorized'], 200);
            }

            // 1. Context Generator: Aggregate today's metrics
            $context = $this->getTodayMetricsContext();

            // 2. Chat with Gemini
            $geminiResponse = $this->askGemini($text, $context);

            // 3. Send reply back to Telegram
            $this->sendMessageToTelegram($chatId, $geminiResponse);

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error: ' . $e->getMessage());
            // Send fallback message to avoid hanging webhook
            if (isset($chatId)) {
                $this->sendMessageToTelegram($chatId, "⚠️ Terjadi kesalahan pada server saat merespon: " . $e->getMessage());
            }
            return response()->json(['status' => 'error'], 200);
        }
    }

    /**
     * Get aggregated metrics for today.
     */
    private function getTodayMetricsContext(): string
    {
        $today = now()->format('Y-m-d');
        
        $totalEvents = DB::table('meta_events')
            ->whereDate('created_at', $today)
            ->count();

        $totalPurchases = DB::table('meta_events')
            ->whereDate('created_at', $today)
            ->where('event_name', 'Purchase')
            ->count();

        $totalValue = DB::table('meta_events')
            ->whereDate('created_at', $today)
            ->where('event_name', 'Purchase')
            ->sum('value');

        // Other events grouping
        $breakdown = DB::table('meta_events')
            ->select('event_name', DB::raw('count(*) as total'))
            ->whereDate('created_at', $today)
            ->groupBy('event_name')
            ->get();

        $breakdownStr = "";
        foreach ($breakdown as $row) {
            $breakdownStr .= "- {$row->event_name}: {$row->total}\n";
        }

        $formattedValue = 'Rp ' . number_format((float)$totalValue, 0, ',', '.');

        $context = <<<EOT
DATA HARI INI ({$today}):
- Total Keseluruhan Traffic/Event: {$totalEvents}
- Total Transaksi (Purchase): {$totalPurchases}
- Total Nilai Transaksi: {$formattedValue}

Rincian Event:
{$breakdownStr}
EOT;

        return $context;
    }

    /**
     * Send promptly to Gemini API 1.5 Flash
     */
    private function askGemini(string $adminPrompt, string $context): string
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return "Sistem Error: GEMINI_API_KEY belum dikonfigurasi.";
        }

        $systemInstruction = "Kamu adalah Digital Marketing Assistant ahli untuk Toko ZeClub. Tolong jawab pertanyaan admin berdasarkan <konteks_database> yang diberikan di bawah ini. Jika admin bertanya di luar konteks yang tersedia, berikan jawaban ringkas berdasarkan pengetahuanmu tapi sebutkan bahwa data tidak tersedia di konteks hari ini.";
        
        $fullPrompt = "{$systemInstruction}\n\n<konteks_database>\n{$context}\n</konteks_database>\n\nPertanyaan Admin:\n{$adminPrompt}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 800,
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, saya tidak bisa merespon pertanyaan tersebut saat ini.';
        }

        Log::error('Gemini API Error: ' . $response->body());
        return "⚠️ Gagal menghubungi Gemini API.";
    }

    /**
     * Send response back to Telegram Admin
     */
    private function sendMessageToTelegram($chatId, $text)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) return;

        Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);
    }
}
