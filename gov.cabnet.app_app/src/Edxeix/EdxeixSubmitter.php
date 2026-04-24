<?php

namespace Bridge\Edxeix;

use Bridge\Config;
use Bridge\HttpClient;

final class EdxeixSubmitter
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClient $http,
        private readonly SessionStore $sessionStore
    ) {
    }

    public function submit(array $payload): array
    {
        if ($this->config->get('app.dry_run', true)) {
            return [
                'success' => true,
                'status' => 0,
                'headers' => [],
                'body' => 'DRY_RUN',
                'remote_reference' => null,
                'mode' => 'dry_run',
            ];
        }

        $session = $this->sessionStore->read();
        if (empty($session['cookie_header'])) {
            throw new \RuntimeException('Missing edxeix session cookie header.');
        }

        $response = $this->http->post(
            $this->config->get('edxeix.submit_url'),
            [
                'timeout' => $this->config->get('edxeix.timeout', 45),
                'cookies' => $session['cookie_header'],
                'headers' => ['Accept: text/html'],
                'form' => $payload,
            ]
        );

        $success = $this->detectSuccess($response);

        return [
            'success' => $success,
            'status' => $response['status'],
            'headers' => $response['headers'],
            'body' => $response['body'],
            'remote_reference' => $this->extractReference($response['body']),
            'mode' => 'live',
        ];
    }

    private function detectSuccess(array $response): bool
    {
        if (in_array($response['status'], [301, 302, 303], true)) {
            return true;
        }

        $body = $response['body'] ?? '';

        if (($response['status'] ?? 0) >= 200 && ($response['status'] ?? 0) < 300) {
            if (stripos($body, 'Συμβάσεις ενοικίασης') !== false && stripos($body, 'error') === false) {
                return true;
            }
        }

        return false;
    }

    private function extractReference(string $body): ?string
    {
        if (preg_match('/([A-Z0-9]{6,})/', $body, $m)) {
            return $m[1];
        }

        return null;
    }
}
