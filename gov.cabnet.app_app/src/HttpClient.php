<?php

namespace Bridge;

final class HttpClient
{
    public function request(string $method, string $url, array $options = []): array
    {
        $ch = curl_init();
        $headers = $options['headers'] ?? [];
        $timeout = (int) ($options['timeout'] ?? 30);

        if (!empty($options['cookies'])) {
            $headers[] = 'Cookie: ' . $options['cookies'];
        }

        if (!empty($options['form'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['form']);
        } elseif (!empty($options['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
        }

        if (!empty($options['query']) && is_array($options['query'])) {
            $queryString = http_build_query($options['query']);
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP error: ' . $error . ' (' . $errno . ')');
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerString = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);

        return [
            'status' => $status,
            'headers_raw' => $headerString,
            'headers' => $this->parseHeaders($headerString),
            'body' => $body,
            'success' => $status >= 200 && $status < 400,
        ];
    }

    public function get(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): array
    {
        return $this->request('POST', $url, $options);
    }

    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        foreach (preg_split("/\r\n|\n|\r/", trim($headerString)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)][] = trim($value);
            }
        }
        return $headers;
    }
}
