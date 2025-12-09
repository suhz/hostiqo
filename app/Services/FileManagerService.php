<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Exception;

class FileManagerService
{
    // Allowed base paths for safety
    protected array $allowedBasePaths = [
        '/var/www',
        '/home',
        '/opt',
        '/usr/local',
    ];

    // Restricted paths that should never be accessible
    protected array $restrictedPaths = [
        '/etc/shadow',
        '/etc/passwd',
        '/root/.ssh',
        '/etc/ssh',
    ];

    /**
     * List directory contents
     */
    public function listDirectory(string $path = '/var/www'): array
    {
        // Sanitize and validate path
        $path = $this->sanitizePath($path);
        $this->validatePath($path);

        try {
            // Get directory listing with details
            $command = "ls -lAh " . escapeshellarg($path) . " 2>&1";
            $output = shell_exec($command);

            if (str_contains($output, 'No such file or directory')) {
                throw new Exception("Directory not found: {$path}");
            }

            if (str_contains($output, 'Permission denied')) {
                throw new Exception("Permission denied: {$path}");
            }

            return $this->parseDirectoryListing($output, $path);

        } catch (Exception $e) {
            throw new Exception("Failed to list directory: " . $e->getMessage());
        }
    }

    /**
     * Read file contents
     */
    public function readFile(string $path): string
    {
        $path = $this->sanitizePath($path);
        $this->validatePath($path);

        try {
            // Check if file exists
            $checkCommand = "sudo test -f " . escapeshellarg($path) . " && echo 'ok' || echo 'error'";
            $check = trim(shell_exec($checkCommand));

            if ($check !== 'ok') {
                throw new Exception("File not found or not readable");
            }

            // Read file content with sudo (limit to 10MB for safety)
            $content = shell_exec("sudo head -c 10485760 " . escapeshellarg($path));

            return $content;

        } catch (Exception $e) {
            throw new Exception("Failed to read file: " . $e->getMessage());
        }
    }

    /**
     * Write file contents
     */
    public function writeFile(string $path, string $content): bool
    {
        $path = $this->sanitizePath($path);
        $this->validatePath($path);

        try {
            // Create temporary file with content
            $tempFile = '/tmp/fm_' . uniqid() . '.tmp';
            
            // Write content to temp file (escape content properly)
            $escapedContent = base64_encode($content);
            shell_exec("echo '{$escapedContent}' | base64 -d > {$tempFile}");

            // Move temp file to target with sudo
            shell_exec("sudo mv " . escapeshellarg($tempFile) . " " . escapeshellarg($path));
            
            // Set readable permissions for created files
            shell_exec("sudo chmod 644 " . escapeshellarg($path));

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to write file: " . $e->getMessage());
        }
    }

    /**
     * Delete file or directory
     */
    public function delete(string $path, bool $recursive = false): bool
    {
        $path = $this->sanitizePath($path);
        $this->validatePath($path);

        try {
            $command = $recursive ? "sudo rm -rf " . escapeshellarg($path) : "sudo rm " . escapeshellarg($path);
            shell_exec($command);

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to delete: " . $e->getMessage());
        }
    }

    /**
     * Create directory
     */
    public function createDirectory(string $path): bool
    {
        $path = $this->sanitizePath($path);
        $this->validatePath($path);

        try {
            // Create directory with sudo
            $output = shell_exec("sudo mkdir -p " . escapeshellarg($path) . " 2>&1 && echo 'success' || echo 'failed'");
            
            if (!str_contains($output, 'success')) {
                throw new Exception("Failed to create directory: {$output}");
            }

            // Verify directory exists
            $check = trim(shell_exec("sudo test -d " . escapeshellarg($path) . " && echo 'ok' || echo 'error'"));
            if ($check !== 'ok') {
                throw new Exception("Directory was not created successfully");
            }

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to create directory: " . $e->getMessage());
        }
    }

    /**
     * Rename/move file or directory
     */
    public function rename(string $oldPath, string $newPath): bool
    {
        $oldPath = $this->sanitizePath($oldPath);
        $newPath = $this->sanitizePath($newPath);
        $this->validatePath($oldPath);
        $this->validatePath($newPath);

        try {
            shell_exec("sudo mv " . escapeshellarg($oldPath) . " " . escapeshellarg($newPath));

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to rename: " . $e->getMessage());
        }
    }

    /**
     * Change file permissions
     */
    public function chmod(string $path, string $permissions): bool
    {
        $path = $this->sanitizePath($path);
        $this->validatePath($path);

        // Validate permissions format (octal)
        if (!preg_match('/^[0-7]{3,4}$/', $permissions)) {
            throw new Exception("Invalid permissions format");
        }

        try {
            shell_exec("sudo chmod " . escapeshellarg($permissions) . " " . escapeshellarg($path));

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to change permissions: " . $e->getMessage());
        }
    }

    /**
     * Get file info
     */
    public function getFileInfo(string $path): array
    {
        $path = $this->sanitizePath($path);

        try {
            $stat = shell_exec("stat -c '%s|%a|%U|%G|%y' " . escapeshellarg($path) . " 2>&1");
            
            if (str_contains($stat, 'No such file')) {
                throw new Exception("File not found");
            }

            list($size, $perms, $owner, $group, $modified) = explode('|', trim($stat));

            return [
                'path' => $path,
                'size' => (int) $size,
                'permissions' => $perms,
                'owner' => $owner,
                'group' => $group,
                'modified' => $modified,
            ];

        } catch (Exception $e) {
            throw new Exception("Failed to get file info: " . $e->getMessage());
        }
    }

    /**
     * Upload file content
     */
    public function uploadFile(string $targetPath, string $content): bool
    {
        return $this->writeFile($targetPath, $content);
    }

    /**
     * Download file
     */
    public function downloadFile(string $path): string
    {
        return $this->readFile($path);
    }

    /**
     * Parse directory listing output
     */
    protected function parseDirectoryListing(string $output, string $currentPath): array
    {
        $lines = explode("\n", trim($output));
        $items = [];

        foreach ($lines as $line) {
            // Skip total line and empty lines
            if (empty($line) || str_starts_with($line, 'total')) {
                continue;
            }

            // Parse ls output
            if (preg_match('/^([drwxls-]+)\s+\d+\s+(\S+)\s+(\S+)\s+(\S+)\s+(\w+\s+\d+)\s+(\d{1,2}:\d{2}|\d{4})\s+(.+)$/', $line, $matches)) {
                $permissions = $matches[1];
                $owner = $matches[2];
                $group = $matches[3];
                $size = $matches[4];
                $date = $matches[5];
                $time = $matches[6];
                $name = trim($matches[7]);

                // Skip . and .. entries
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $items[] = [
                    'name' => $name,
                    'path' => rtrim($currentPath, '/') . '/' . $name,
                    'type' => $permissions[0] === 'd' ? 'directory' : 'file',
                    'permissions' => $permissions,
                    'owner' => $owner,
                    'group' => $group,
                    'size' => $size,
                    'modified' => $date . ' ' . $time,
                    'is_readable' => str_contains($permissions, 'r'),
                    'is_writable' => str_contains($permissions, 'w'),
                ];
            }
        }

        return $items;
    }

    /**
     * Sanitize path
     */
    protected function sanitizePath(string $path): string
    {
        // Remove any dangerous characters
        $path = str_replace(['..', '~'], '', $path);
        
        // Ensure absolute path
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Validate path is in allowed locations
     */
    protected function validatePath(string $path): void
    {
        // Check if path is in restricted list
        foreach ($this->restrictedPaths as $restricted) {
            if (str_starts_with($path, $restricted)) {
                throw new Exception("Access denied: Restricted path");
            }
        }

        // Check if path is in allowed base paths
        $isAllowed = false;
        foreach ($this->allowedBasePaths as $allowed) {
            if (str_starts_with($path, $allowed)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            throw new Exception("Access denied: Path outside allowed locations");
        }
    }
}
