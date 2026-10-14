<?php

namespace App\Infrastructure\Shopify;

use InvalidArgumentException;

final readonly class ShopifyStoreEndpoint
{
    public function __construct(
        private string $shop,
        private string $apiVersion,
    ) {
        if (! preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/D', $shop)) {
            throw new InvalidArgumentException('La tienda Shopify no está configurada correctamente.');
        }

        if (! preg_match('/\A\d{4}-(?:01|04|07|10)\z/D', $apiVersion)) {
            throw new InvalidArgumentException('La versión de Shopify Admin API no es válida.');
        }
    }

    public function tokenUrl(): string
    {
        return "https://{$this->shop}.myshopify.com/admin/oauth/access_token";
    }

    public function graphqlUrl(): string
    {
        return "https://{$this->shop}.myshopify.com/admin/api/{$this->apiVersion}/graphql.json";
    }
}
