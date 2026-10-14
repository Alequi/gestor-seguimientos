<?php

namespace App\Infrastructure\Shopify;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class ShopifyClientCredentialsTokenProvider
{
    private ?string $accessToken = null;

    private ?CarbonImmutable $expiresAt = null;

    public function __construct(
        private readonly ShopifyStoreEndpoint $endpoint,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function accessToken(): string
    {
        if ($this->accessToken !== null && $this->expiresAt?->greaterThan(CarbonImmutable::now()->addMinute())) {
            return $this->accessToken;
        }

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new ShopifyCommunicationException('Faltan las credenciales de Shopify.');
        }

        try {
            $response = Http::asForm()->acceptJson()->post($this->endpoint->tokenUrl(), [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);
        } catch (ConnectionException) {
            throw new ShopifyCommunicationException('No se pudo autenticar con Shopify.');
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in');

        if (! $response->successful() || ! is_string($token) || $token === '' || ! is_int($expiresIn) || $expiresIn <= 0) {
            throw new ShopifyCommunicationException('Shopify no devolvió un token de acceso válido.');
        }

        $this->accessToken = $token;
        $this->expiresAt = CarbonImmutable::now()->addSeconds($expiresIn);

        return $token;
    }
}
