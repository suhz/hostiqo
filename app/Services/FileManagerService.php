<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
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
            $result = Process::run("ls -lAh {$path}");

            if ($result->failed()) {
                if (str_contains($result->errorOutput(), 'No such file or directory')) {
                    throw new Exception("Directory not found: {$path}");
                }
                if (str_contains($result->errorOutput(), 'Permission denied')) {
                    throw new Exception("Permission denied: {$path}");
                }
                throw new Exception("Failed to list directory: " . $result->errorOutput());
            }

            return $this->parseDirectoryListing($result->output(), $path);

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
            $checkResult = Process::run("sudo test -f {$path}");

            if ($checkResult->failed()) {
                throw new Exception("File not found or not readable");
            }

            // Read file content with sudo (limit to 10MB for safety)
            $result = Process::run("sudo head -c 10485760 {$path}");

            if ($result->failed()) {
                throw new Exception("Failed to read file: " . $result->errorOutput());
            }

            return $result->output();

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
            $writeResult = Process::run("echo '{$escapedContent}' | base64 -d > {$tempFile}");

            if ($writeResult->failed()) {
                throw new Exception("Failed to create temp file");
            }

            // Move temp file to target with sudo
            $moveResult = Process::run("sudo mv {$tempFile} {$path}");
            
            if ($moveResult->failed()) {
                throw new Exception("Failed to move file: " . $moveResult->errorOutput());
            }
            
            // Set readable permissions for created files
            Process::run("sudo chmod 644 {$path}");

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
            $command = $recursive ? "sudo rm -rf {$path}" : "sudo rm {$path}";
            $result = Process::run($command);

            if ($result->failed()) {
                throw new Exception("Failed to delete: " . $result->errorOutput());
            }

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
            $result = Process::run("sudo mkdir -p {$path}");
            
            if ($result->failed()) {
                throw new Exception("Failed to create directory: " . $result->errorOutput());
            }

            // Verify directory exists
            $checkResult = Process::run("sudo test -d {$path}");
            if ($checkResult->failed()) {
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
            $result = Process::run("sudo mv {$oldPath} {$newPath}");

            if ($result->failed()) {
                throw new Exception("Failed to rename: " . $result->errorOutput());
            }

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
            $result = Process::run("sudo chmod {$permissions} {$path}");

            if ($result->failed()) {
                throw new Exception("Failed to change permissions: " . $result->errorOutput());
            }

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
            $result = Process::run("stat -c '%s|%a|%U|%G|%y' {$path}");
            
            if ($result->failed()) {
                throw new Exception("File not found");
            }

            list($size, $perms, $owner, $group, $modified) = explode('|', trim($result->output()));

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

        // Sort: directories first, then files (both alphabetically)
        usort($items, function($a, $b) {
            // Directories before files
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            // Alphabetically within same type
            return strcasecmp($a['name'], $b['name']);
        });

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
