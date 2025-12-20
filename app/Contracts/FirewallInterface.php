<?php

namespace App\Contracts;

interface FirewallInterface
{
    /**
     * Get firewall type name
     */
    public function getType(): string;

    /**
     * Get firewall status
     */
    public function getStatus(): array;

    /**
     * Enable firewall
     */
    public function enable(): array;

    /**
     * Disable firewall
     */
    public function disable(): array;

    /**
     * Add a firewall rule
     */
    public function addRule(string $port, string $protocol = 'tcp'): array;

    /**
     * Delete a firewall rule
     */
    public function deleteRule(string $port, string $protocol = 'tcp'): array;

    /**
     * Reset firewall to default
     */
    public function reset(): array;

    /**
     * Get list of rules
     */
    public function getRules(): array;
}
