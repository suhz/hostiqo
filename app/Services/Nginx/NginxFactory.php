<?php

namespace App\Services\Nginx;

use App\Contracts\NginxInterface;

class NginxFactory
{
    /**
     * Create nginx service based on OS
     */
    public static function create(): NginxInterface
    {
        $osFamily = self::detectOsFamily();
        $isLocal = in_array(config('app.env'), ['local', 'dev', 'development']);
        
        if ($isLocal) {
            return new LocalNginxService();
        }
        
        if ($osFamily === 'rhel') {
            return new RhelNginxService();
        }
        
        return new DebianNginxService();
    }

    /**
     * Detect OS family
     */
    protected static function detectOsFamily(): string
    {
        if (file_exists('/etc/redhat-release')) {
            return 'rhel';
        }
        
        if (file_exists('/etc/os-release')) {
            $content = file_get_contents('/etc/os-release');
            if (preg_match('/ID_LIKE=.*rhel|ID_LIKE=.*fedora|ID=.*rocky|ID=.*alma|ID=.*centos/i', $content)) {
                return 'rhel';
            }
        }
        
        return 'debian';
    }
}
