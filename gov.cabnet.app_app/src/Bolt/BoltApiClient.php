<?php

namespace Bridge\Bolt;

use Bridge\Config;
use Bridge\HttpClient;

final class BoltApiClient
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClient $http,
        private readonly BoltTokenService $tokenService
    ) {
    }

    public function getJson(string $path, array $query = []): array
    {
        $token = $this->tokenService->getAccessToken();

        $response = $this->http->get(
            rtrim($this->config->get('bolt.base_url'), '/') . '/' . ltrim($path, '/'),
            [
                'timeout' => $this->config->get('bolt.timeout', 30),
                'headers' => [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token,
                ],
                'query' => $query,
            ]
        );

        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            throw new \RuntimeException('Bolt API returned non-JSON response.');
        }

        return $json;
    }
}
