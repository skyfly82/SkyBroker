<?php

namespace App\Infra\Carriers\InPost;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class InPostHttpClient
{
    private string $baseUrl;
    private ?string $orgId;
    private ?string $token;

    public function __construct(?string $baseUrl = null, ?string $orgId = null, ?string $token = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? env('INPOST_API_BASE_URL', ''), '/');
        $this->orgId   = $orgId   ?? env('INPOST_ORG_ID');
        $this->token   = $token   ?? env('INPOST_TOKEN');
    }

    private function http(): PendingRequest
    {
        if (empty($this->token)) {
            throw new \RuntimeException('InPost token not configured.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
            ->timeout(30);
    }

    // TODO: Implement real calls according to ShipX API
}
