<?php

declare(strict_types=1);

namespace App;

/**
 * Immutable value object holding runtime debug configuration settings.
 */
final readonly class Config
{
    public function __construct(
        public string $phpIniPath,
        public string $clientHost = 'localhost',
        public int $clientPort = 9003,
        public string $ideKey = 'PHPSTORM',
    ) {
    }

    /**
     * Create a Config with overridden values.
     */
    public function with(
        ?string $phpIniPath = null,
        ?string $clientHost = null,
        ?int $clientPort = null,
        ?string $ideKey = null,
    ): self {
        return new self(
            phpIniPath: $phpIniPath ?? $this->phpIniPath,
            clientHost: $clientHost ?? $this->clientHost,
            clientPort: $clientPort ?? $this->clientPort,
            ideKey: $ideKey ?? $this->ideKey,
        );
    }
}
