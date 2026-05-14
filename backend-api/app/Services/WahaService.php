<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $session;
    protected bool $enabled;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('waha.base_url', 'http://localhost:3000'), '/');
        $this->apiKey   = config('waha.api_key', '');
        $this->session  = config('waha.session', 'default');
        $this->enabled  = config('waha.enabled', false);
    }

    /**
     * Send a plain text message.
     */
    public function sendText(string $phone, string $message): bool
    {
        if (!$this->enabled) {
            Log::info('[WAHA] Disabled — would send to ' . $phone . ': ' . $message);
            return false;
        }

        $chatId = $this->formatChatId($phone);

        try {
            $response = Http::withHeaders([
                'X-Api-Key'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->baseUrl}/api/sendText", [
                'session' => $this->session,
                'chatId'  => $chatId,
                'text'    => $message,
            ]);

            if ($response->successful()) {
                Log::info("[WAHA] Message sent to {$chatId}");
                return true;
            }

            Log::warning("[WAHA] Failed to send to {$chatId}: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("[WAHA] Exception sending to {$chatId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if WAHA session is running.
     */
    public function isSessionRunning(): bool
    {
        if (!$this->enabled) return false;

        try {
            $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout(5)
                ->get("{$this->baseUrl}/api/sessions/{$this->session}");

            return $response->successful() &&
                ($response->json('status') === 'WORKING' || $response->json('status') === 'running');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get QR code for session authentication.
     */
    public function getQrCode(): ?string
    {
        try {
            $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout(10)
                ->get("{$this->baseUrl}/api/{$this->session}/auth/qr");

            if ($response->successful()) {
                return $response->json('qr') ?? $response->json('value');
            }
            return null;
        } catch (\Exception $e) {
            Log::error('[WAHA] Failed to get QR: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Start a session.
     */
    public function startSession(): bool
    {
        try {
            $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout(10)
                ->post("{$this->baseUrl}/api/sessions/{$this->session}/start");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Format phone number to WhatsApp chatId format.
     * Strips non-digits, ensures country code, appends @c.us
     */
    public function formatChatId(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // Handle Indonesian numbers starting with 0
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return $digits . '@c.us';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
