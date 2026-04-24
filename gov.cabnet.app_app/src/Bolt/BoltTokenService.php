<?php

namespace Bridge\Bolt;

use Bridge\Config;
use Bridge\HttpClient;

final class BoltTokenService
{
    private ?string $cachedToken = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly Config $config,
        private readonly HttpClient $http
    ) {
    }

    public function getAccessToken(): string
    {
        if ($this->cachedToken !== null && time() < ($this->expiresAt - 60)) {
            return $this->cachedToken;
        }

        $response = $this->http->post(
            $this->config->get('bolt.token_url'),
            [
                'timeout' => $this->config->get('bolt.timeout', 30),
                'headers' => ['Content-Type: application/x-www-form-urlencoded'],
                'form' => [
                    'client_id' => $this->config->get('bolt.client_id'),
                    'client_secret' => $this->config->get('bolt.client_secret'),
                    'grant_type' => 'client_credentials',
                    'scope' => $this->config->get('bolt.scope', 'fleet-integration:api'),
                ],
            ]
        );

        $json = json_decode($response['body'], true);
        if (!is_array($json) || empty($json['access_token'])) {
            throw new \RuntimeException('Bolt token response was invalid.');
        }

        $this->cachedToken = $json['access_token'];
        $this->expiresAt = time() + (int) ($json['expires_in'] ?? 600);

        return $this->cachedToken;
    }
}
