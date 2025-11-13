<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;

class MikrotikScriptGenerator
{
    /**
     * Generate a one-time RouterOS onboarding script
     *
     * @param TenantMikrotik $mikrotik
     * @param string $systemUrl
     * @return string
     */
    public function generateScript(TenantMikrotik $mikrotik, string $systemUrl): string
    {
        $systemUrl = rtrim($systemUrl, '/');
        $syncToken = $mikrotik->sync_token;
        $mikrotikId = $mikrotik->id;

        $stubPath = resource_path('scripts/onboarding.rsc.stub');
        $template = is_file($stubPath) ? file_get_contents($stubPath) : '';

        if (empty($template)) {
            // Fallback: minimal script to avoid empty output
            $template = ':put "ZISP fallback onboarding"';
        }

        $placeholders = [
            '{{TIMESTAMP}}' => now()->format('Y-m-d H:i:s'),
            '{{DEVICE_NAME}}' => (string) $mikrotik->name,
            '{{SYNC_TOKEN}}' => (string) $syncToken,
            '{{SYSTEM_URL}}' => (string) $systemUrl,
            '{{MIKROTIK_ID}}' => (string) $mikrotikId,

            // New v7+ script placeholders
            '{{name}}' => (string) ($mikrotik->name ?? 'router'),
            '{{router_id}}' => (string) $mikrotikId,
            '{{username}}' => (string) ($mikrotik->api_username ?? 'admin'),
            '{{router_password}}' => (string) ($mikrotik->api_password ?? ''),
            '{{api_port}}' => (string) ($mikrotik->api_port ?? 8728),

            '{{radius_secret}}' => (string) (config('radius.secret') ?? env('RADIUS_SECRET', '')),
            '{{radius_ip}}' => (string) (config('radius.ip') ?? env('RADIUS_IP', '')),
            '{{trusted_ip}}' => (string) (config('zisp.trusted_ip') ?? env('ZISP_TRUSTED_IP', '')), // optional

            '{{wg_server_endpoint}}' => (string) (config('wireguard.server_endpoint') ?? env('WG_SERVER_ENDPOINT', '')),
            '{{wg_server_pubkey}}' => (string) (config('wireguard.server_pubkey') ?? env('WG_SERVER_PUBKEY', '')),
            '{{wg_subnet}}' => (string) (config('wireguard.subnet') ?? env('WG_SUBNET', '')),
            '{{wg_port}}' => (string) (config('wireguard.port') ?? env('WG_PORT', '51820')),
            '{{wg_client_ip}}' => (string) (config('wireguard.client_ip') ?? env('WG_CLIENT_IP', '')),
            '{{wg_register_url}}' => (string) (config('wireguard.register_url') ?? env('WG_REGISTER_URL', '')),

            // Build sync URL with token
            '{{sync_url}}' => (string) ($systemUrl . '/mikrotiks/' . $mikrotikId . '/sync?token=' . $syncToken),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Generate a bash SSH wrapper script to upload and run the RouterOS script
     */
    public function generateSSHScript(TenantMikrotik $mikrotik, string $systemUrl, string $routerUsername, string $routerHost): string
    {
        $systemUrl = rtrim($systemUrl, '/');
        $rscContent = $this->generateScript($mikrotik, $systemUrl);
        $filename = $this->getScriptFilename($mikrotik);

        return <<<BASH
#!/bin/bash
set -e

ROUTER_HOST="{$routerHost}"
ROUTER_USER="{$routerUsername}"
ROUTER_PASSWORD="\${1:-}"

if [ -z "\$ROUTER_PASSWORD" ]; then
    echo "Usage: \$0 <router-password>"
    exit 1
fi

echo "Uploading onboarding script..."
sshpass -p "\$ROUTER_PASSWORD" scp -o StrictHostKeyChecking=no /dev/stdin \$ROUTER_USER@\$ROUTER_HOST:{$filename} <<'EOF'
{$rscContent}
EOF

echo "Importing script on router..."
sshpass -p "\$ROUTER_PASSWORD" ssh -o StrictHostKeyChecking=no \$ROUTER_USER@\$ROUTER_HOST "/import {$filename}"

echo "✓ Onboarding complete!"
BASH;
    }

    public function getScriptFilename(TenantMikrotik $mikrotik): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $mikrotik->name ?? 'mikrotik');
        return "zisp_onboarding_{$sanitized}_{$mikrotik->id}.rsc";
    }

    public function getSSHScriptFilename(TenantMikrotik $mikrotik): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $mikrotik->name ?? 'mikrotik');
        return "zisp_onboard_{$sanitized}_{$mikrotik->id}.sh";
    }

    public function storeScript(TenantMikrotik $mikrotik, string $systemUrl): void
    {
        $script = $this->generateScript($mikrotik, $systemUrl);
        $mikrotik->update([
            'onboarding_script_content' => $script,
            'onboarding_status' => 'in_progress',
        ]);
    }
}
