<?php

namespace App\Services\PhpFpm;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DebianPhpFpmService extends AbstractPhpFpmService
{
    public function __construct()
    {
        $this->webServerUser = 'www-data';
        $this->webServerGroup = 'www-data';
    }

    public function getOsFamily(): string
    {
        return 'debian';
    }

    public function getPoolDirectoryPath(string $phpVersion): string
    {
        return "/etc/php/{$phpVersion}/fpm/pool.d";
    }

    public function getSocketPath(string $phpVersion, string $poolName): string
    {
        return "/var/run/php/php{$phpVersion}-fpm-{$poolName}.sock";
    }

    public function getLogPath(string $phpVersion): string
    {
        return "/var/log/php{$phpVersion}-fpm";
    }

    public function getWebServerUser(): string
    {
        return $this->webServerUser;
    }

    public function getWebServerGroup(): string
    {
        return $this->webServerGroup;
    }

    public function writePoolConfig(Website $website): array
    {
        try {
            if ($website->project_type !== 'php') {
                return [
                    'success' => true,
                    'message' => 'Not a PHP project, skipping PHP-FPM pool configuration'
                ];
            }

            $poolName = $website->php_pool_name ?? $this->generatePoolName($website);
            $config = $this->generatePoolConfig($website);
            
            $poolDir = $this->getPoolDirectoryPath($website->php_version);
            $filepath = "{$poolDir}/{$poolName}.conf";
            $logDir = $this->getLogPath($website->php_version);
            $socketPath = $this->getSocketPath($website->php_version, $poolName);

            // Create log directory
            Process::run("sudo /bin/mkdir -p {$logDir}");
            Process::run("sudo /bin/chown {$this->webServerUser}:{$this->webServerGroup} {$logDir}");

            // Write config
            $tempFile = tempnam(sys_get_temp_dir(), 'phpfpm_');
            File::put($tempFile, $config);
            
            $result = Process::run("sudo /bin/cp {$tempFile} {$filepath}");
            @unlink($tempFile);
            
            if ($result->failed()) {
                throw new \Exception("Failed to write pool config: " . $result->errorOutput());
            }

            Process::run("sudo /bin/chmod 644 {$filepath}");

            // Update pool name in database
            if (!$website->php_pool_name) {
                $website->update(['php_pool_name' => $poolName]);
            }

            return [
                'success' => true,
                'filepath' => $filepath,
                'pool_name' => $poolName,
                'socket_path' => $socketPath,
                'message' => 'PHP-FPM pool configuration created successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to write PHP-FPM pool config', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function deletePoolConfig(Website $website): array
    {
        try {
            if ($website->project_type !== 'php' || !$website->php_pool_name) {
                return [
                    'success' => true,
                    'message' => 'No PHP-FPM pool to delete'
                ];
            }

            $poolDir = $this->getPoolDirectoryPath($website->php_version);
            $filepath = "{$poolDir}/{$website->php_pool_name}.conf";

            $result = Process::run("sudo /bin/rm -f {$filepath}");
            
            if ($result->failed()) {
                throw new \Exception("Failed to delete pool config: " . $result->errorOutput());
            }

            return [
                'success' => true,
                'message' => 'PHP-FPM pool configuration deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete PHP-FPM pool config', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function testConfig(string $phpVersion, ?string $poolConfigPath = null): array
    {
        $service = "php{$phpVersion}-fpm";
        $result = Process::run("sudo /usr/sbin/php-fpm{$phpVersion} -t");

        return [
            'success' => $result->successful(),
            'output' => $result->output() . $result->errorOutput(),
        ];
    }

    public function restart(string $phpVersion): array
    {
        $service = "php{$phpVersion}-fpm";
        $result = Process::run("sudo /bin/systemctl restart {$service}");

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? "PHP-FPM {$phpVersion} restarted" : "Failed to restart PHP-FPM",
            'error' => $result->errorOutput(),
        ];
    }

    public function reload(string $phpVersion): array
    {
        $service = "php{$phpVersion}-fpm";
        $result = Process::run("sudo /bin/systemctl reload {$service}");

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? "PHP-FPM {$phpVersion} reloaded" : "Failed to reload PHP-FPM",
            'error' => $result->errorOutput(),
        ];
    }
}
