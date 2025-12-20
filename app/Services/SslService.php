<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SslService
{
    /**
     * Request SSL certificate using certbot
     */
    public function requestCertificate(Website $website): array
    {
        try {
            $domain = $website->domain;
            $webroot = $website->root_path;

            // Use certbot with webroot plugin
            $command = "sudo /usr/bin/certbot certonly --webroot -w {$webroot} -d {$domain} -d www.{$domain} --non-interactive --agree-tos --email admin@{$domain}";
            
            $result = Process::run($command);

            if ($result->successful()) {
                Log::info('SSL certificate obtained successfully', [
                    'domain' => $domain,
                    'output' => $result->output()
                ]);

                return [
                    'success' => true,
                    'message' => 'SSL certificate obtained successfully',
                ];
            }

            return [
                'success' => false,
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to request SSL certificate', [
                'domain' => $website->domain,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete SSL certificate
     */
    public function deleteCertificate(Website $website): array
    {
        try {
            $domain = $website->domain;
            
            $result = Process::run("sudo /usr/bin/certbot delete --cert-name {$domain} --non-interactive");

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'SSL certificate deleted' : 'Failed to delete certificate',
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Renew SSL certificates
     */
    public function renewCertificates(): array
    {
        try {
            $result = Process::run('sudo /usr/bin/certbot renew --non-interactive');

            return [
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
