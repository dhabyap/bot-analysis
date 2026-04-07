<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\TelegramWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Meta Pixel Webhook (Ingestion Engine)
// Placed in api.php so it is stateless and bypasses CSRF automatically.
Route::post('/webhook/meta', [MetaWebhookController::class, 'handle']);

// Telegram Bot Webhook (AI Analytical Assistant)
// Placed in api.php so it is stateless and bypasses CSRF automatically.
Route::post('/webhook/telegram', [TelegramWebhookController::class, 'handle']);

