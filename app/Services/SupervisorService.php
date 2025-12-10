<?php

namespace App\Services;

use App\Models\SupervisorProgram;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Exception;

class SupervisorService
{
    /**
     * Generate supervisor config from program model
     */
    public function generateConfig(SupervisorProgram $program): string
    {
        $config = "[program:{$program->name}]\n";
        $config .= "command={$program->command}\n";
        $config .= "directory={$program->directory}\n";
        $config .= "numprocs={$program->numprocs}\n";
        $config .= "process_name=%(program_name)s_%(process_num)02d\n";
        $config .= "user={$program->user}\n";
        $config .= "autostart=" . ($program->autostart ? 'true' : 'false') . "\n";
        $config .= "autorestart=" . ($program->autorestart ? 'true' : 'false') . "\n";
        $config .= "startsecs={$program->startsecs}\n";
        $config .= "stopwaitsecs={$program->stopwaitsecs}\n";
        $config .= "stdout_logfile={$program->getLogFilePath()}\n";
        $config .= "stdout_logfile_maxbytes={$program->stdout_logfile_maxbytes}\n";
        $config .= "stdout_logfile_backups={$program->stdout_logfile_backups}\n";
        $config .= "redirect_stderr={$program->redirect_stderr}\n";
        $config .= "stopasgroup={$program->stopasgroup}\n";
        $config .= "killasgroup={$program->killasgroup}\n";
        
        // Add environment variables if set
        if ($program->environment && is_array($program->environment)) {
            $envVars = [];
            foreach ($program->environment as $key => $value) {
                $envVars[] = "{$key}=\"{$value}\"";
            }
            if (!empty($envVars)) {
                $config .= "environment=" . implode(',', $envVars) . "\n";
            }
        }
        
        return $config;
    }

    /**
     * Deploy supervisor config to system
     */
    public function deploy(SupervisorProgram $program): array
    {
        try {
            $config = $this->generateConfig($program);
            $configPath = $program->getConfigFilePath();
            $tempFile = '/tmp/' . $program->getConfigFileName();
            
            // Write to temp file
            File::put($tempFile, $config);
            
            // Copy to supervisor conf.d
            $result = Process::run(['sudo', 'cp', $tempFile, $configPath]);
            if ($result->failed()) {
                throw new Exception("Failed to copy config file: " . $result->errorOutput());
            }
            
            // Set permissions
            Process::run(['sudo', 'chmod', '644', $configPath]);
            
            // Clean up temp file
            File::delete($tempFile);
            
            // Reload supervisor
            $reloadResult = $this->reloadSupervisor();
            if (!$reloadResult['success']) {
                throw new Exception("Failed to reload supervisor: " . $reloadResult['message']);
            }
            
            Log::info("Supervisor program deployed", [
                'program' => $program->name,
                'config_path' => $configPath
            ]);
            
            return [
                'success' => true,
                'message' => "Program {$program->name} deployed successfully",
                'config_path' => $configPath
            ];
            
        } catch (Exception $e) {
            Log::error("Failed to deploy supervisor program", [
                'program' => $program->name ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Remove supervisor config from system
     */
    public function remove(SupervisorProgram $program): array
    {
        try {
            $configPath = $program->getConfigFilePath();
            
            // Stop the program first
            $this->stopProgram($program->name);
            
            // Remove config file
            $result = Process::run(['sudo', 'rm', '-f', $configPath]);
            if ($result->failed()) {
                throw new Exception("Failed to remove config file: " . $result->errorOutput());
            }
            
            // Reload supervisor
            $reloadResult = $this->reloadSupervisor();
            if (!$reloadResult['success']) {
                throw new Exception("Failed to reload supervisor: " . $reloadResult['message']);
            }
            
            Log::info("Supervisor program removed", [
                'program' => $program->name,
                'config_path' => $configPath
            ]);
            
            return [
                'success' => true,
                'message' => "Program {$program->name} removed successfully"
            ];
            
        } catch (Exception $e) {
            Log::error("Failed to remove supervisor program", [
                'program' => $program->name ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reload supervisor configuration
     */
    public function reloadSupervisor(): array
    {
        try {
            // Run supervisorctl reread
            $rereadResult = Process::run(['sudo', 'supervisorctl', 'reread']);
            if ($rereadResult->failed()) {
                throw new Exception("supervisorctl reread failed: " . $rereadResult->errorOutput());
            }
            
            // Run supervisorctl update
            $updateResult = Process::run(['sudo', 'supervisorctl', 'update']);
            if ($updateResult->failed()) {
                throw new Exception("supervisorctl update failed: " . $updateResult->errorOutput());
            }
            
            return [
                'success' => true,
                'message' => 'Supervisor reloaded successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Start a supervisor program
     */
    public function startProgram(string $programName): array
    {
        try {
            $result = Process::run(['sudo', 'supervisorctl', 'start', $programName . ':*']);
            
            if ($result->failed()) {
                throw new Exception($result->errorOutput());
            }
            
            return [
                'success' => true,
                'message' => "Program {$programName} started successfully",
                'output' => $result->output()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Stop a supervisor program
     */
    public function stopProgram(string $programName): array
    {
        try {
            $result = Process::run(['sudo', 'supervisorctl', 'stop', $programName . ':*']);
            
            if ($result->failed()) {
                throw new Exception($result->errorOutput());
            }
            
            return [
                'success' => true,
                'message' => "Program {$programName} stopped successfully",
                'output' => $result->output()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Restart a supervisor program
     */
    public function restartProgram(string $programName): array
    {
        try {
            $result = Process::run(['sudo', 'supervisorctl', 'restart', $programName . ':*']);
            
            if ($result->failed()) {
                throw new Exception($result->errorOutput());
            }
            
            return [
                'success' => true,
                'message' => "Program {$programName} restarted successfully",
                'output' => $result->output()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get supervisor program status
     */
    public function getProgramStatus(string $programName): array
    {
        try {
            $result = Process::run(['sudo', 'supervisorctl', 'status', $programName . ':*']);
            
            $output = $result->output();
            $processes = [];
            
            // Parse output
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                // Parse: program_name_00   RUNNING   pid 1234, uptime 1:23:45
                if (preg_match('/^(\S+)\s+(\S+)\s+(.*)$/', $line, $matches)) {
                    $processes[] = [
                        'name' => $matches[1],
                        'status' => $matches[2],
                        'info' => $matches[3] ?? ''
                    ];
                }
            }
            
            return [
                'success' => true,
                'processes' => $processes
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'processes' => []
            ];
        }
    }

    /**
     * Get all supervisor programs
     */
    public function getAllPrograms(): array
    {
        try {
            $result = Process::run(['sudo', 'supervisorctl', 'status']);
            
            $output = $result->output();
            $programs = [];
            
            // Parse output
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                if (preg_match('/^(\S+)\s+(\S+)\s+(.*)$/', $line, $matches)) {
                    $programName = explode(':', $matches[1])[0]; // Get base program name
                    
                    if (!isset($programs[$programName])) {
                        $programs[$programName] = [
                            'name' => $programName,
                            'status' => $matches[2],
                            'info' => $matches[3] ?? '',
                            'processes' => []
                        ];
                    }
                    
                    $programs[$programName]['processes'][] = [
                        'name' => $matches[1],
                        'status' => $matches[2],
                        'info' => $matches[3] ?? ''
                    ];
                }
            }
            
            return [
                'success' => true,
                'programs' => array_values($programs)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'programs' => []
            ];
        }
    }

    /**
     * Get program logs
     */
    public function getProgramLogs(SupervisorProgram $program, int $lines = 100): string
    {
        try {
            $logFile = $program->getLogFilePath();
            
            if (!File::exists($logFile)) {
                return "Log file not found: {$logFile}";
            }
            
            $result = Process::run(['sudo', 'tail', '-n', (string)$lines, $logFile]);
            
            return $result->output();
            
        } catch (Exception $e) {
            return "Error reading logs: " . $e->getMessage();
        }
    }
}
