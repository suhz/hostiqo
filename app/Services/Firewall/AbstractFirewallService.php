<?php

namespace App\Services\Firewall;

use App\Contracts\FirewallInterface;
use Illuminate\Support\Facades\Process;

abstract class AbstractFirewallService implements FirewallInterface
{
    /**
     * Run a command with sudo
     */
    protected function runCommand(string $command): array
    {
        $result = Process::run($command);
        
        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'error' => $result->errorOutput(),
        ];
    }

    /**
     * Parse command output into array of lines
     */
    protected function parseOutput(string $output): array
    {
        return array_filter(array_map('trim', explode("\n", $output)));
    }
}
