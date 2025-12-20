<?php

namespace App\Services\ServiceManager;

use App\Contracts\ServiceManagerInterface;
use Illuminate\Support\Facades\Process;

abstract class AbstractServiceManagerService implements ServiceManagerInterface
{
    protected array $supportedServices = [];

    abstract public function getOsFamily(): string;
    abstract protected function buildServiceList(): array;

    public function getSupportedServices(): array
    {
        return $this->supportedServices;
    }

    /**
     * Get available services (installed on system)
     */
    public function getAvailableServices(): array
    {
        $services = [];

        foreach ($this->supportedServices as $key => $info) {
            // Check if service exists using systemctl status
            $result = Process::run("systemctl status {$info['service']} 2>&1");
            $output = $result->output();
            
            // Service doesn't exist if output contains these messages
            $notFound = str_contains($output, 'could not be found') || 
                       str_contains($output, 'not-found') ||
                       str_contains($output, 'Unit') && str_contains($output, 'not found');
            
            if (!$notFound) {
                $status = $this->getServiceStatus($key);
                $services[$key] = array_merge($info, $status);
            }
        }

        return $services;
    }

    /**
     * Get status of a specific service
     */
    public function getServiceStatus(string $serviceKey): array
    {
        if (!isset($this->supportedServices[$serviceKey])) {
            return [
                'running' => false,
                'enabled' => false,
                'status' => 'unknown',
                'error' => 'Service not supported'
            ];
        }

        $serviceName = $this->supportedServices[$serviceKey]['service'];
        
        // Get detailed status from systemctl status
        $result = Process::run("systemctl status {$serviceName} 2>&1");
        $output = $result->output();
        
        // Parse status from output
        $isRunning = str_contains($output, 'Active: active (running)');
        $isEnabled = str_contains($output, 'enabled;') || str_contains($output, '; enabled');
        
        // Determine status string
        if (str_contains($output, 'Active: active (running)')) {
            $status = 'running';
        } elseif (str_contains($output, 'Active: inactive (dead)')) {
            $status = 'stopped';
        } elseif (str_contains($output, 'Active: failed')) {
            $status = 'failed';
        } else {
            $status = 'unknown';
        }

        return [
            'running' => $isRunning,
            'enabled' => $isEnabled,
            'status' => $status,
        ];
    }

    /**
     * Start a service
     */
    public function startService(string $serviceKey): array
    {
        if (!isset($this->supportedServices[$serviceKey])) {
            return ['success' => false, 'error' => 'Service not supported'];
        }

        $serviceName = $this->supportedServices[$serviceKey]['service'];
        $result = Process::run("sudo /bin/systemctl start {$serviceName}");

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? "{$serviceName} started" : "Failed to start {$serviceName}",
            'error' => $result->errorOutput(),
        ];
    }

    /**
     * Stop a service
     */
    public function stopService(string $serviceKey): array
    {
        if (!isset($this->supportedServices[$serviceKey])) {
            return ['success' => false, 'error' => 'Service not supported'];
        }

        $serviceName = $this->supportedServices[$serviceKey]['service'];
        $result = Process::run("sudo /bin/systemctl stop {$serviceName}");

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? "{$serviceName} stopped" : "Failed to stop {$serviceName}",
            'error' => $result->errorOutput(),
        ];
    }

    /**
     * Restart a service
     */
    public function restartService(string $serviceKey): array
    {
        if (!isset($this->supportedServices[$serviceKey])) {
            return ['success' => false, 'error' => 'Service not supported'];
        }

        $serviceName = $this->supportedServices[$serviceKey]['service'];
        $result = Process::run("sudo /bin/systemctl restart {$serviceName}");

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? "{$serviceName} restarted" : "Failed to restart {$serviceName}",
            'error' => $result->errorOutput(),
        ];
    }

    /**
     * Reload a service
     */
    public function reloadService(string $serviceKey): array
    {
        if (!isset($this->supportedServices[$serviceKey])) {
            return ['success' => false, 'error' => 'Service not supported'];
        }

        $info = $this->supportedServices[$serviceKey];
        
        if (!($info['supports_reload'] ?? false)) {
            return $this->restartService($serviceKey);
        }

        $serviceName = $info['service'];
        $result = Process::run("sudo /bin/systemctl reload {$serviceName}");

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? "{$serviceName} reloaded" : "Failed to reload {$serviceName}",
            'error' => $result->errorOutput(),
        ];
    }
}
