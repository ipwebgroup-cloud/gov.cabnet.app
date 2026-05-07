<?php

namespace Bridge\Receipts;

use RuntimeException;

final class AadeMyDataClient
{
    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config receipts.aade_mydata config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** @return array<string,mixed> */
    public function readiness(): array
    {
        $environment = $this->environment();
        $userId = trim((string)($this->config['user_id'] ?? ''));
        $subscriptionKey = trim((string)($this->config['subscription_key'] ?? ''));

        return [
            'enabled' => filter_var($this->config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'environment' => $environment,
            'endpoint_base' => $this->endpointBase(),
            'send_invoices_url' => $this->url('/SendInvoices'),
            'request_transmitted_docs_url' => $this->url('/RequestTransmittedDocs'),
            'user_id_present' => $userId !== '',
            'user_id_length' => strlen($userId),
            'subscription_key_present' => $subscriptionKey !== '',
            'subscription_key_length' => strlen($subscriptionKey),
            'issuer_vat_number' => (string)($this->config['issuer_vat_number'] ?? ''),
            'issuer_name_present' => trim((string)($this->config['issuer_name'] ?? '')) !== '',
        ];
    }

    /**
     * Connectivity-only GET against RequestTransmittedDocs. This has no invoice
     * side effects and is meant to validate endpoint reachability and headers.
     *
     * @return array<string,mixed>
     */
    public function pingRequestTransmittedDocs(int $mark = 1): array
    {
        $this->assertCredentialsPresent();

        $url = $this->url('/RequestTransmittedDocs') . '?mark=' . max(1, $mark);
        return $this->request('GET', $url, null);
    }

    /**
     * Transmission primitive for the controlled official issuer. This method has
     * side effects at AADE/myDATA and must only be called by a manually-confirmed
     * CLI flow. It returns raw response_body for internal MARK/UID extraction;
     * callers must never print or expose that raw body.
     *
     * @return array<string,mixed>
     */
    public function sendInvoicesXml(string $xml): array
    {
        $this->assertCredentialsPresent();
        if (trim($xml) === '') {
            throw new RuntimeException('Empty myDATA XML payload.');
        }

        return $this->request('POST', $this->url('/SendInvoices'), $xml, [
            'Content-Type: application/xml; charset=UTF-8',
        ], true);
    }

    private function assertCredentialsPresent(): void
    {
        if (trim((string)($this->config['user_id'] ?? '')) === '') {
            throw new RuntimeException('Missing AADE/myDATA user_id.');
        }
        if (trim((string)($this->config['subscription_key'] ?? '')) === '') {
            throw new RuntimeException('Missing AADE/myDATA subscription_key.');
        }
    }

    private function environment(): string
    {
        $env = strtolower(trim((string)($this->config['environment'] ?? 'test')));
        return $env === 'production' ? 'production' : 'test';
    }

    private function endpointBase(): string
    {
        $override = trim((string)($this->config['endpoint_base'] ?? ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        return $this->environment() === 'production'
            ? 'https://mydatapi.aade.gr/myDATA'
            : 'https://mydataapidev.aade.gr';
    }

    private function url(string $path): string
    {
        return $this->endpointBase() . '/' . ltrim($path, '/');
    }

    /**
     * @param array<int,string> $extraHeaders
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, ?string $body = null, array $extraHeaders = [], bool $includeBody = false): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not available.');
        }

        $headers = array_merge([
            'aade-user-id: ' . trim((string)$this->config['user_id']),
            'ocp-apim-subscription-key: ' . trim((string)$this->config['subscription_key']),
            'Accept: application/xml,text/xml,*/*',
        ], $extraHeaders);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int)($this->config['timeout'] ?? 30),
            CURLOPT_CONNECTTIMEOUT => (int)($this->config['connect_timeout'] ?? 15),
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false) {
            $failed = [
                'ok' => false,
                'http_status' => 0,
                'curl_errno' => $curlErrno,
                'error' => $curlError !== '' ? $curlError : 'curl_exec_failed',
                'response_excerpt_suppressed' => true,
                'response_bytes' => 0,
                'response_sha256' => hash('sha256', ''),
            ];
            if ($includeBody) {
                $failed['response_body'] = '';
            }
            return $failed;
        }

        $responseBody = substr((string)$raw, $headerSize);

        $out = [
            'ok' => $status >= 200 && $status < 300,
            'http_status' => $status,
            'curl_errno' => $curlErrno,
            'error' => $curlError !== '' ? $curlError : null,
            'response_excerpt_suppressed' => true,
            'response_bytes' => strlen($responseBody),
            'response_sha256' => hash('sha256', $responseBody),
        ];
        if ($includeBody) {
            $out['response_body'] = $responseBody;
        }

        return $out;
    }
}
