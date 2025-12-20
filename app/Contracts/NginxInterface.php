<?php

namespace App\Contracts;

use App\Models\Website;

interface NginxInterface
{
    /**
     * Get OS family
     */
    public function getOsFamily(): string;

    /**
     * Generate Nginx configuration for a website
     */
    public function generateConfig(Website $website): string;

    /**
     * Write Nginx configuration file
     */
    public function writeConfig(Website $website): array;

    /**
     * Delete Nginx configuration file
     */
    public function deleteConfig(Website $website): array;

    /**
     * Enable a site (create symlink)
     */
    public function enableSite(Website $website): array;

    /**
     * Disable a site (remove symlink)
     */
    public function disableSite(Website $website): array;

    /**
     * Test Nginx configuration
     */
    public function testConfig(): array;

    /**
     * Reload Nginx
     */
    public function reload(): array;

    /**
     * Deploy website configuration (write, enable, test, reload)
     */
    public function deploy(Website $website): array;

    /**
     * Get PHP-FPM socket path
     */
    public function getPhpFpmSocketPath(string $phpVersion, string $poolName, ?string $customPool = null): string;
}
