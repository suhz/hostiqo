<?php

namespace App\Contracts;

interface ServiceManagerInterface
{
    /**
     * Get OS family
     */
    public function getOsFamily(): string;

    /**
     * Get list of supported services
     */
    public function getSupportedServices(): array;

    /**
     * Get available services (installed on system)
     */
    public function getAvailableServices(): array;

    /**
     * Get status of a specific service
     */
    public function getServiceStatus(string $serviceKey): array;

    /**
     * Start a service
     */
    public function startService(string $serviceKey): array;

    /**
     * Stop a service
     */
    public function stopService(string $serviceKey): array;

    /**
     * Restart a service
     */
    public function restartService(string $serviceKey): array;

    /**
     * Reload a service
     */
    public function reloadService(string $serviceKey): array;
}
