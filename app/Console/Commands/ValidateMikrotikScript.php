<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MikrotikScriptGenerator;
use App\Models\Tenants\TenantMikrotik;

class ValidateMikrotikScript extends Command
{
    protected $signature = 'mikrotik:validate-script {--device-id=1 : Device ID to validate}';
    protected $description = 'Validate the generated Mikrotik onboarding script for syntax errors';

    public function handle(MikrotikScriptGenerator $generator)
    {
        $deviceId = $this->option('device-id');
        
        $mikrotik = TenantMikrotik::find($deviceId);
        if (!$mikrotik) {
            $this->error("Device with ID {$deviceId} not found.");
            return 1;
        }

        $systemUrl = config('app.url');
        $script = $generator->generateScript($mikrotik, $systemUrl);

        // Basic syntax validation
        $this->info('📋 Validating RouterOS script...');
        $this->newLine();

        // Check for common issues
        $issues = [];

        // Check for unbalanced quotes
        $singleQuotes = substr_count($script, "'") - substr_count($script, "\\'");
        $doubleQuotes = substr_count($script, '"') - substr_count($script, '\\"');
        
        if ($singleQuotes % 2 !== 0) {
            $issues[] = 'Unbalanced single quotes detected';
        }
        if ($doubleQuotes % 2 !== 0) {
            $issues[] = 'Unbalanced double quotes detected';
        }

        // Check for common RouterOS syntax patterns
        if (!preg_match('/^:/m', $script)) {
            $issues[] = 'Missing RouterOS local variable declarations (should start with :)';
        }

        if (empty($issues)) {
            $this->info('✅ Script validation passed!');
            $this->info('Script statistics:');
            $this->line('  Lines: ' . count(explode("\n", $script)));
            $this->line('  Size: ' . strlen($script) . ' bytes');
            $this->line('  Device ID: ' . $mikrotik->id);
            $this->line('  Sync Token: ' . substr($mikrotik->sync_token, 0, 10) . '...');
            $this->newLine();
            $this->info('Ready to import on your Mikrotik device!');
            return 0;
        } else {
            $this->error('❌ Script validation failed with issues:');
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
            return 1;
        }
    }
}
