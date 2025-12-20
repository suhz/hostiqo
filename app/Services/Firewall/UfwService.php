<?php

namespace App\Services\Firewall;

class UfwService extends AbstractFirewallService
{
    public function getType(): string
    {
        return 'ufw';
    }

    public function getStatus(): array
    {
        $result = $this->runCommand('sudo /usr/sbin/ufw status verbose');
        
        if (!$result['success']) {
            return [
                'active' => false,
                'status' => 'unknown',
                'error' => $result['error'],
            ];
        }

        $output = $result['output'];
        $isActive = str_contains($output, 'Status: active');
        
        return [
            'active' => $isActive,
            'status' => $isActive ? 'active' : 'inactive',
            'output' => $output,
        ];
    }

    public function enable(): array
    {
        $result = $this->runCommand('sudo /usr/sbin/ufw --force enable');
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'UFW enabled successfully' : 'Failed to enable UFW',
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function disable(): array
    {
        $result = $this->runCommand('sudo /usr/sbin/ufw disable');
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'UFW disabled successfully' : 'Failed to disable UFW',
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function addRule(string $port, string $protocol = 'tcp'): array
    {
        $rule = "{$port}/{$protocol}";
        $result = $this->runCommand("sudo /usr/sbin/ufw allow {$rule}");
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "Rule added: {$rule}" : "Failed to add rule: {$rule}",
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function deleteRule(string $port, string $protocol = 'tcp'): array
    {
        $rule = "{$port}/{$protocol}";
        $result = $this->runCommand("sudo /usr/sbin/ufw delete allow {$rule}");
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "Rule deleted: {$rule}" : "Failed to delete rule: {$rule}",
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function reset(): array
    {
        $result = $this->runCommand('sudo /usr/sbin/ufw --force reset');
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'UFW reset successfully' : 'Failed to reset UFW',
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function getRules(): array
    {
        $result = $this->runCommand('sudo /usr/sbin/ufw status numbered');
        
        if (!$result['success']) {
            return [];
        }

        $rules = [];
        $lines = $this->parseOutput($result['output']);
        
        foreach ($lines as $line) {
            if (preg_match('/^\[\s*(\d+)\]\s+(.+)$/', $line, $matches)) {
                $rules[] = [
                    'number' => $matches[1],
                    'rule' => trim($matches[2]),
                ];
            }
        }

        return $rules;
    }
}
