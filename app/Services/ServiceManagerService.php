<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Exception;

class ServiceManagerService
{
    protected string $osFamily;
    protected array $supportedServices;

    public function __construct()
    {
        $this->osFamily = $this->detectOsFamily();
        $this->supportedServices = $this->buildServiceList();
    }

    /**
     * Detect OS family (debian or rhel)
     */
    protected function detectOsFamily(): string
    {
        // Check for RHEL-based
        if (file_exists('/etc/redhat-release')) {
            return 'rhel';
        }
        
        // Check /etc/os-release
        if (file_exists('/etc/os-release')) {
            $content = file_get_contents('/etc/os-release');
            if (preg_match('/ID_LIKE=.*rhel|ID_LIKE=.*fedora|ID=.*rocky|ID=.*alma|ID=.*centos/i', $content)) {
                return 'rhel';
            }
        }
        
        return 'debian';
    }

    /**
     * Build service list based on OS family
     */
    protected function buildServiceList(): array
    {
        $isRhel = $this->osFamily === 'rhel';

        return [
            // Web Server
            'nginx' => [
                'name' => 'Nginx',
                'service' => 'nginx',
                'supports_reload' => true,
                'icon' => 'hexagon'
            ],
            
            // Databases
            'redis' => [
                'name' => 'Redis',
                'service' => $isRhel ? 'redis' : 'redis-server',
                'supports_reload' => false,
                'icon' => 'lightning',
            ],
            'mysql' => [
                'name' => $isRhel ? 'MariaDB' : 'MySQL',
                'service' => $isRhel ? 'mariadb' : 'mysql',
                'supports_reload' => false,
                'icon' => 'database',
            ],
            
            // PHP Versions - Debian style
            'php8.4-fpm' => [
                'name' => 'PHP 8.4 FPM',
                'service' => $isRhel ? 'php84-php-fpm' : 'php8.4-fpm',
                'supports_reload' => true,
                'icon' => 'code',
            ],
            'php8.3-fpm' => [
                'name' => 'PHP 8.3 FPM',
                'service' => $isRhel ? 'php83-php-fpm' : 'php8.3-fpm',
                'supports_reload' => true,
                'icon' => 'code',
            ],
            'php8.2-fpm' => [
                'name' => 'PHP 8.2 FPM',
                'service' => $isRhel ? 'php82-php-fpm' : 'php8.2-fpm',
                'supports_reload' => true,
                'icon' => 'code',
            ],
            'php8.1-fpm' => [
                'name' => 'PHP 8.1 FPM',
                'service' => $isRhel ? 'php81-php-fpm' : 'php8.1-fpm',
                'supports_reload' => true,
                'icon' => 'code',
            ],
            'php8.0-fpm' => [
                'name' => 'PHP 8.0 FPM',
                'service' => $isRhel ? 'php80-php-fpm' : 'php8.0-fpm',
                'supports_reload' => true,
                'icon' => 'code',
            ],
            'php7.4-fpm' => [
                'name' => 'PHP 7.4 FPM',
                'service' => $isRhel ? 'php74-php-fpm' : 'php7.4-fpm',
                'supports_reload' => true,
                'icon' => 'code',
            ],
            
            // Process Managers
            'supervisor' => [
                'name' => 'Supervisor',
                'service' => $isRhel ? 'supervisord' : 'supervisor',
                'supports_reload' => true,
                'icon' => 'display',
            ],
            
            // Security
            'fail2ban' => [
                'name' => 'fail2ban',
                'service' => 'fail2ban',
                'supports_reload' => true,
                'icon' => 'shield-shaded',
            ],
            
            // Firewall
            'firewall' => [
                'name' => $isRhel ? 'Firewalld' : 'UFW Firewall',
                'service' => $isRhel ? 'firewalld' : 'ufw',
                'supports_reload' => false,
                'icon' => 'shield-check',
            ],
        ];
    }

    /**
     * Get OS family
     */
    public function getOsFamily(): string
    {
        return $this->osFamily;
    }

    /**
     * Get all available services
     */
    public function getAvailableServices(): array
    {
        $services = [];

        foreach ($this->supportedServices as $key => $info) {
            try {
                $serviceName = $info['service'];
                
                // Check if service exists
                $result = Process::run("systemctl list-unit-files {$serviceName}.service 2>&1");
                
                if (str_contains($result->output(), $serviceName)) {
                    $status = $this->getServiceStatus($key);
                    $services[$key] = array_merge($info, $status);
                }
            } catch (Exception $e) {
                // Service not available, skip
                continue;
            }
        }

        return $services;
    }

    /**
     * Get service status
     */
    public function getServiceStatus(string $service): array
    {
        try {
            if (!isset($this->supportedServices[$service])) {
                throw new Exception("Unsupported service: {$service}");
            }

            $serviceName = $this->supportedServices[$service]['service'];

            // Check if service is active
            $isActiveResult = Process::run("systemctl is-active {$serviceName} 2>&1");
            $isActive = trim($isActiveResult->output()) === 'active';
            
            // Check if service is enabled
            $isEnabledResult = Process::run("systemctl is-enabled {$serviceName} 2>&1");
            $isEnabled = trim($isEnabledResult->output()) === 'enabled';
            
            // Get detailed status
            $statusResult = Process::run("systemctl status {$serviceName} 2>&1 | head -20");
            $statusOutput = $statusResult->output();

            // Parse PID and uptime
            $pid = null;
            $uptime = null;
            $cpu = null;
            $memory = null;

            if (preg_match('/Main PID: (\d+)/', $statusOutput, $matches)) {
                $pid = $matches[1];
                
                // Get CPU and memory usage for this PID
                $psResult = Process::run("ps -p {$pid} -o %cpu,%mem 2>&1 | tail -1");
                if ($psResult->successful()) {
                    $psOutput = trim($psResult->output());
                    if ($psOutput && !str_contains($psOutput, 'error')) {
                        $parts = preg_split('/\s+/', $psOutput);
                        if (count($parts) >= 2) {
                            $cpu = floatval($parts[0]);
                            $memory = floatval($parts[1]);
                        }
                    }
                }
            }

            if (preg_match('/Active: active \(running\) since (.+?);/', $statusOutput, $matches)) {
                $uptime = $matches[1];
            }

            return [
                'status' => $isActive ? 'running' : 'stopped',
                'is_active' => $isActive,
                'is_enabled' => $isEnabled,
                'pid' => $pid,
                'uptime' => $uptime,
                'cpu' => $cpu,
                'memory' => $memory,
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'is_active' => false,
                'is_enabled' => false,
                'pid' => null,
                'uptime' => null,
                'cpu' => null,
                'memory' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Start a service
     */
    public function startService(string $service): array
    {
        try {
            if (!isset($this->supportedServices[$service])) {
                throw new Exception("Unsupported service: {$service}");
            }

            $serviceName = $this->supportedServices[$service]['service'];
            
            $result = Process::run("sudo systemctl start {$serviceName} 2>&1");
            
            if ($result->failed()) {
                throw new Exception("Failed to start service: " . $result->errorOutput());
            }
            
            // Wait and check status
            sleep(1);
            $status = $this->getServiceStatus($service);
            
            if ($status['is_active']) {
                return [
                    'success' => true,
                    'message' => "Service {$this->supportedServices[$service]['name']} started successfully",
                    'status' => $status
                ];
            } else {
                throw new Exception("Service failed to start");
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Stop a service
     */
    public function stopService(string $service): array
    {
        try {
            if (!isset($this->supportedServices[$service])) {
                throw new Exception("Unsupported service: {$service}");
            }

            $serviceName = $this->supportedServices[$service]['service'];
            
            $result = Process::run("sudo systemctl stop {$serviceName} 2>&1");
            
            if ($result->failed()) {
                throw new Exception("Failed to stop service: " . $result->errorOutput());
            }
            
            // Wait and check status
            sleep(1);
            $status = $this->getServiceStatus($service);
            
            if (!$status['is_active']) {
                return [
                    'success' => true,
                    'message' => "Service {$this->supportedServices[$service]['name']} stopped successfully",
                    'status' => $status
                ];
            } else {
                throw new Exception("Service failed to stop");
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restart a service
     */
    public function restartService(string $service): array
    {
        try {
            if (!isset($this->supportedServices[$service])) {
                throw new Exception("Unsupported service: {$service}");
            }

            $serviceName = $this->supportedServices[$service]['service'];
            
            $result = Process::run("sudo systemctl restart {$serviceName} 2>&1");
            
            if ($result->failed()) {
                throw new Exception("Failed to restart service: " . $result->errorOutput());
            }
            
            // Wait and check status
            sleep(1);
            $status = $this->getServiceStatus($service);
            
            if ($status['is_active']) {
                return [
                    'success' => true,
                    'message' => "Service {$this->supportedServices[$service]['name']} restarted successfully",
                    'status' => $status
                ];
            } else {
                throw new Exception("Service failed to restart");
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reload a service (if supported)
     */
    public function reloadService(string $service): array
    {
        try {
            if (!isset($this->supportedServices[$service])) {
                throw new Exception("Unsupported service: {$service}");
            }

            if (!$this->supportedServices[$service]['supports_reload']) {
                throw new Exception("Service does not support reload");
            }

            $serviceName = $this->supportedServices[$service]['service'];
            
            $result = Process::run("sudo systemctl reload {$serviceName} 2>&1");
            
            if ($result->failed()) {
                throw new Exception("Failed to reload service: " . $result->errorOutput());
            }
            
            return [
                'success' => true,
                'message' => "Service {$this->supportedServices[$service]['name']} reloaded successfully",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get service logs
     */
    public function getServiceLogs(string $service, int $lines = 100): string
    {
        try {
            if (!isset($this->supportedServices[$service])) {
                throw new Exception("Unsupported service: {$service}");
            }

            $serviceName = $this->supportedServices[$service]['service'];
            
            $result = Process::run("sudo journalctl -u {$serviceName} -n {$lines} --no-pager 2>&1");
            
            return $result->output();

        } catch (Exception $e) {
            return "Error fetching logs: " . $e->getMessage();
        }
    }
}
