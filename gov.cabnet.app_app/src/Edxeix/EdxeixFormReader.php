<?php

namespace Bridge\Edxeix;

use Bridge\Config;
use Bridge\HttpClient;

final class EdxeixFormReader
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClient $http,
        private readonly SessionStore $sessionStore
    ) {
    }

    public function fetchCreateFormState(): array
    {
        $session = $this->sessionStore->read();
        if (empty($session['cookie_header'])) {
            throw new \RuntimeException('Missing edxeix cookie header. Import a fresh session first.');
        }

        $response = $this->http->get(
            $this->config->get('edxeix.create_url'),
            [
                'timeout' => $this->config->get('edxeix.timeout', 45),
                'cookies' => $session['cookie_header'],
                'headers' => ['Accept: text/html'],
            ]
        );

        if (($response['status'] ?? 0) >= 400) {
            throw new \RuntimeException('Failed to fetch edxeix create page. HTTP ' . $response['status']);
        }

        return $this->parseHtml($response['body'], $session);
    }

    private function parseHtml(string $html, array $session): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $csrf = $this->readInputValue($xpath, '_token') ?: ($session['csrf_token'] ?? $this->config->get('edxeix.fallback_csrf_token', ''));
        $lessor = $this->readInputValue($xpath, 'lessor') ?: $this->config->get('edxeix.lessor_id');
        $broker = $this->readInputValue($xpath, 'broker') ?: $this->config->get('edxeix.default_broker', '');

        $driverOptions = $this->readSelectOptions($xpath, 'driver');
        $vehicleOptions = $this->readSelectOptions($xpath, 'vehicle');

        // EDXEIX currently renders the start select as name="starting_point" / id="starting_point".
        // Older bridge code used starting_point_id. Read both so the bridge remains compatible if
        // EDXEIX changes the field name again.
        $startingPointOptions = $this->readSelectOptions($xpath, 'starting_point');
        if (!$startingPointOptions) {
            $startingPointOptions = $this->readSelectOptions($xpath, 'starting_point_id');
        }

        return [
            'csrf_token' => $csrf,
            'lessor' => $lessor,
            'broker' => $broker,
            'driver_options' => $driverOptions,
            'vehicle_options' => $vehicleOptions,
            'starting_point_options' => $startingPointOptions,
            'html' => $html,
        ];
    }

    private function readInputValue(\DOMXPath $xpath, string $name): string
    {
        $nodes = $xpath->query('//input[@name="' . $name . '"]');
        if (!$nodes || $nodes->length === 0) {
            return '';
        }

        return (string) $nodes->item(0)->attributes?->getNamedItem('value')?->nodeValue;
    }

    private function readSelectOptions(\DOMXPath $xpath, string $name): array
    {
        $options = [];
        $nodes = $xpath->query('//select[@name="' . $name . '"]/option[@value]');
        if (!$nodes) {
            return $options;
        }

        foreach ($nodes as $option) {
            $value = trim((string) $option->attributes?->getNamedItem('value')?->nodeValue);
            $label = trim((string) $option->textContent);

            if ($value === '') {
                continue;
            }

            $options[$value] = $label;
        }

        return $options;
    }
}
