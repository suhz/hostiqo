<?php

namespace App\Contracts;

use App\Models\Website;

interface PhpFpmInterface
{
    /**
     * Get OS family
     */
    public function getOsFamily(): string;

    /**
     * Get pool directory path for a specific PHP version
     */
    public function getPoolDirectoryPath(string $phpVersion): string;

    /**
     * Get socket path for a PHP-FPM pool
     */
    public function getSocketPath(string $phpVersion, string $poolName): string;

    /**
     * Get log directory path
     */
    public function getLogPath(string $phpVersion): string;

    /**
     * Generate PHP-FPM pool configuration for a website
     */
    public function generatePoolConfig(Website $website): string;

    /**
     * Write PHP-FPM pool configuration
     */
    public function writePoolConfig(Website $website): array;

    /**
     * Delete PHP-FPM pool configuration
     */
    public function deletePoolConfig(Website $website): array;

    /**
     * Test PHP-FPM configuration
     */
    public function testConfig(string $phpVersion, ?string $poolConfigPath = null): array;

    /**
     * Restart PHP-FPM service
     */
    public function restart(string $phpVersion): array;

    /**
     * Reload PHP-FPM service
     */
    public function reload(string $phpVersion): array;

    /**
     * Get web server user
     */
    public function getWebServerUser(): string;

    /**
     * Get web server group
     */
    public function getWebServerGroup(): string;
}
