<?php

namespace App\Infrastructure\Shopify;

use App\Application\Exceptions\ShopifyCommunicationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final readonly class ShopifyAdminGraphqlClient
{
    public function __construct(
        private ShopifyStoreEndpoint $endpoint,
        private ShopifyClientCredentialsTokenProvider $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function execute(string $document, array $variables = []): array
    {
        $payload = ['query' => $document];

        if ($variables !== []) {
            if (array_is_list($variables)) {
                throw new InvalidArgumentException('Las variables GraphQL deben tener nombres.');
            }

            $payload['variables'] = $variables;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Shopify-Access-Token' => $this->tokens->accessToken()])
                ->post($this->endpoint->graphqlUrl(), $payload);
        } catch (ConnectionException) {
            throw new ShopifyCommunicationException('No se pudo consultar Shopify.');
        }

        $data = $response->json('data');
        $errors = $response->json('errors');

        if (! $response->successful() || ! is_array($data) || ($errors !== null && $errors !== [])) {
            throw new ShopifyCommunicationException('Shopify no devolvió una respuesta GraphQL válida.');
        }

        return $data;
    }
}
