<?php

namespace App\Traits;

trait DetectsOperatingSystem
{
    protected ?string $osFamily = null;

    /**
     * Detect OS family (debian or rhel)
     */
    protected function detectOsFamily(): string
    {
        if ($this->osFamily !== null) {
            return $this->osFamily;
        }

        // Check for RHEL-based
        if (file_exists('/etc/redhat-release')) {
            $this->osFamily = 'rhel';
            return $this->osFamily;
        }
        
        // Check /etc/os-release for RHEL-like distros
        if (file_exists('/etc/os-release')) {
            $content = file_get_contents('/etc/os-release');
            if (preg_match('/ID_LIKE=.*rhel|ID_LIKE=.*fedora|ID=.*rocky|ID=.*alma|ID=.*centos/i', $content)) {
                $this->osFamily = 'rhel';
                return $this->osFamily;
            }
        }
        
        $this->osFamily = 'debian';
        return $this->osFamily;
    }

    /**
     * Get OS family
     */
    public function getOsFamily(): string
    {
        return $this->detectOsFamily();
    }

    /**
     * Check if running on RHEL-based system
     */
    public function isRhel(): bool
    {
        return $this->detectOsFamily() === 'rhel';
    }

    /**
     * Check if running on Debian-based system
     */
    public function isDebian(): bool
    {
        return $this->detectOsFamily() === 'debian';
    }

    /**
     * Get web server user based on OS
     */
    public function getWebServerUser(): string
    {
        return $this->isRhel() ? 'nginx' : 'www-data';
    }

    /**
     * Get web server group based on OS
     */
    public function getWebServerGroup(): string
    {
        return $this->isRhel() ? 'nginx' : 'www-data';
    }

    /**
     * Convert PHP version to RHEL format (8.4 -> 84)
     */
    protected function phpVersionToRhel(string $version): string
    {
        return str_replace('.', '', $version);
    }
}
