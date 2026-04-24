<?php

namespace Bridge;

final class Config
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        date_default_timezone_set($this->get('app.timezone', 'Europe/Athens'));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->config;
    }
}
