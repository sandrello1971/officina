<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrmLeadInboundService
{
    public function __construct(
        protected string $url,
        protected string $secret,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            url: (string) config('services.crm.lead_inbound_url'),
            secret: (string) config('services.crm.lead_inbound_secret'),
        );
    }

    public function isConfigured(): bool
    {
        return ! empty($this->url) && ! empty($this->secret);
    }

    /**
     * Best-effort lead delivery to CRM with 2 retries.
     *
     * @return array{success: bool, lead_id?: string, error?: string}
     */
    public function sendLead(array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'CRM inbound not configured'];
        }

        try {
            $response = Http::timeout(10)
                ->retry(2, 1000)
                ->withHeaders([
                    'X-Marketing-Lead-Secret' => $this->secret,
                    'Accept' => 'application/json',
                ])
                ->acceptJson()
                ->post($this->url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'lead_id' => $response->json('lead_id'),
                ];
            }

            Log::warning('[CrmLeadInbound] CRM responded non-2xx', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => "CRM responded {$response->status()}: " . $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('[CrmLeadInbound] Request failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
