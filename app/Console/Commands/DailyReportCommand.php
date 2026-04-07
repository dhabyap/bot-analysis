<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DailyReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:daily-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send yesterday\'s aggregated marketing report to Telegram Admin.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $yesterday = now()->subDay()->format('Y-m-d');
            
            $totalEvents = DB::table('meta_events')
                ->whereDate('created_at', $yesterday)
                ->count();

            $totalPurchases = DB::table('meta_events')
                ->whereDate('created_at', $yesterday)
                ->where('event_name', 'Purchase')
                ->count();

            $totalValue = DB::table('meta_events')
                ->whereDate('created_at', $yesterday)
                ->where('event_name', 'Purchase')
                ->sum('value');

            $breakdown = DB::table('meta_events')
                ->select('event_name', DB::raw('count(*) as total'))
                ->whereDate('created_at', $yesterday)
                ->groupBy('event_name')
                ->get();

            $breakdownStr = "";
            foreach ($breakdown as $row) {
                $breakdownStr .= "▫️ {$row->event_name}: {$row->total}\n";
            }

            $formattedValue = 'Rp ' . number_format((float)$totalValue, 0, ',', '.');
            
            $reportMessage = "📊 *DAILY MARKETING REPORT* 📊\n";
            $reportMessage .= "🗓 *Date:* {$yesterday}\n\n";
            $reportMessage .= "📈 *Total Traffic/Events:* {$totalEvents}\n";
            $reportMessage .= "🛒 *Total Purchases:* {$totalPurchases}\n";
            $reportMessage .= "💰 *Total Revenue:* {$formattedValue}\n\n";
            $reportMessage .= "📋 *Event Breakdown:*\n{$breakdownStr}\n";
            $reportMessage .= "#TokoZeClub_DailyReport";

            $this->sendToTelegram($reportMessage);

            $this->info("Daily report for {$yesterday} has been sent successfully.");

        } catch (\Exception $e) {
            Log::error('DailyReportCommand Error: ' . $e->getMessage());
            $this->error('Failed to send daily report.');
        }
    }

    private function sendToTelegram($message)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_ADMIN_ID');

        if (!$botToken || !$chatId) {
            $this->error('Telegram configuration missing in .env');
            return;
        }

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }
}
