<?php

namespace Bridge\Edxeix;

final class SessionStore
{
    public function __construct(private readonly string $sessionFile)
    {
    }

    public function read(): array
    {
        if (!file_exists($this->sessionFile)) {
            return [
                'cookie_header' => '',
                'csrf_token' => '',
                'last_refreshed_at' => null,
            ];
        }

        $json = json_decode((string) file_get_contents($this->sessionFile), true);
        return is_array($json) ? $json : [
            'cookie_header' => '',
            'csrf_token' => '',
            'last_refreshed_at' => null,
        ];
    }

    public function write(array $data): void
    {
        $existing = $this->read();
        $merged = array_merge($existing, $data, [
            'last_refreshed_at' => date(DATE_ATOM),
        ]);

        $dir = dirname($this->sessionFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->sessionFile,
            json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
