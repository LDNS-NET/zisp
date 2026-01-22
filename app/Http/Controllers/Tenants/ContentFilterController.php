<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use App\Models\Tenants\TenantMikrotik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContentFilterController extends Controller
{
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;
        $settings = TenantSetting::forTenant($tenantId, 'content_filter');
        $mikrotiks = TenantMikrotik::where('status', 'online')->get();

        return inertia('Settings/ContentFilter/Index', [
            'settings' => $settings ? $settings->settings : [
                'enabled' => false,
                'categories' => [],
                'blacklist' => [],
                'whitelist' => [],
                'dns_address' => '1.1.1.3', // Default to Cloudflare Family DNS
            ],
            'mikrotiks' => $mikrotiks,
        ]);
    }

    public function update(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'categories' => 'array',
            'blacklist' => 'array',
            'whitelist' => 'array',
            'dns_address' => 'nullable|ip',
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenantId, 'category' => 'content_filter'],
            ['settings' => $validated, 'created_by' => Auth::id()]
        );

        return back()->with('success', 'Content filtering settings updated successfully.');
    }

    public function applyToRouter(Request $request, $routerId)
    {
        $router = TenantMikrotik::findOrFail($routerId);
        $settings = TenantSetting::forTenant(Auth::user()->tenant_id, 'content_filter');

        if (!$settings || !isset($settings->settings['enabled']) || !$settings->settings['enabled']) {
            return back()->with('error', 'Content filtering is disabled.');
        }

        try {
            $mikrotikService = new \App\Services\MikrotikService($router);
            $client = $mikrotikService->getClient();

            $categories = $settings->settings['categories'] ?? [];
            $blacklist = $settings->settings['blacklist'] ?? [];
            $whitelist = $settings->settings['whitelist'] ?? [];
            $dnsAddress = $settings->settings['dns_address'] ?? '1.1.1.3';

            // Build domain lists
            $domainLists = $this->getCategoryDomains($categories);
            $allBlockedDomains = array_unique(array_merge($domainLists, $blacklist));
            
            // Remove whitelisted domains
            $allBlockedDomains = array_diff($allBlockedDomains, $whitelist);

            // 1. Set DNS to parental control DNS
            $query = new \RouterOS\Query('/ip/dns/set');
            $query->equal('servers', $dnsAddress);
            $query->equal('allow-remote-requests', 'yes');
            $client->query($query)->read();

            // 2. Clear existing content filter DNS static entries
            $existingStatic = $client->query(new \RouterOS\Query('/ip/dns/static/print'))->read();
            foreach ($existingStatic as $entry) {
                if (isset($entry['comment']) && $entry['comment'] === 'Content-Filter') {
                    $removeQuery = new \RouterOS\Query('/ip/dns/static/remove');
                    $removeQuery->equal('.id', $entry['.id']);
                    $client->query($removeQuery)->read();
                }
            }

            // 3. Add DNS static entries to redirect blocked domains to 0.0.0.0
            foreach ($allBlockedDomains as $domain) {
                try {
                    $addQuery = new \RouterOS\Query('/ip/dns/static/add');
                    $addQuery->equal('name', $domain);
                    $addQuery->equal('address', '0.0.0.0');
                    $addQuery->equal('comment', 'Content-Filter');
                    $client->query($addQuery)->read();
                } catch (\Exception $e) {
                    // Continue if domain already exists
                    Log::warning('Failed to add DNS static entry', [
                        'domain' => $domain,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 4. Clear and recreate Layer 7 protocol matchers for HTTPS SNI blocking
            $existingL7 = $client->query(new \RouterOS\Query('/ip/firewall/layer7-protocol/print'))->read();
            foreach ($existingL7 as $protocol) {
                if (isset($protocol['name']) && str_starts_with($protocol['name'], 'cf-')) {
                    $removeQuery = new \RouterOS\Query('/ip/firewall/layer7-protocol/remove');
                    $removeQuery->equal('.id', $protocol['.id']);
                    $client->query($removeQuery)->read();
                }
            }

            // Create Layer 7 protocol for blocked domains (SNI matching for HTTPS)
            if (count($allBlockedDomains) > 0) {
                // Build regex pattern for SNI matching (max 10 domains per rule due to MikroTik limitations)
                $domainChunks = array_chunk($allBlockedDomains, 10);
                
                foreach ($domainChunks as $index => $chunk) {
                    $pattern = '^.*(';
                    $pattern .= implode('|', array_map(function($domain) {
                        return preg_quote($domain, '/');
                    }, $chunk));
                    $pattern .= ').*$';

                    try {
                        $l7Query = new \RouterOS\Query('/ip/firewall/layer7-protocol/add');
                        $l7Query->equal('name', 'cf-block-' . $index);
                        $l7Query->equal('regexp', $pattern);
                        $l7Query->equal('comment', 'Content-Filter');
                        $client->query($l7Query)->read();
                    } catch (\Exception $e) {
                        Log::warning('Failed to create Layer 7 protocol', [
                            'index' => $index,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // 5. Clear existing content filter firewall rules
            $existingRules = $client->query(new \RouterOS\Query('/ip/firewall/filter/print'))->read();
            foreach ($existingRules as $rule) {
                if (isset($rule['comment']) && $rule['comment'] === 'Content-Filter') {
                    $removeQuery = new \RouterOS\Query('/ip/firewall/filter/remove');
                    $removeQuery->equal('.id', $rule['.id']);
                    $client->query($removeQuery)->read();
                }
            }

            // 6. Add firewall rules to block Layer 7 protocols
            if (count($allBlockedDomains) > 0) {
                $domainChunks = array_chunk($allBlockedDomains, 10);
                
                foreach ($domainChunks as $index => $chunk) {
                    try {
                        $filterQuery = new \RouterOS\Query('/ip/firewall/filter/add');
                        $filterQuery->equal('chain', 'forward');
                        $filterQuery->equal('layer7-protocol', 'cf-block-' . $index);
                        $filterQuery->equal('action', 'reject');
                        $filterQuery->equal('reject-with', 'icmp-network-unreachable');
                        $filterQuery->equal('comment', 'Content-Filter');
                        $client->query($filterQuery)->read();
                    } catch (\Exception $e) {
                        Log::warning('Failed to create firewall rule', [
                            'index' => $index,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            Log::info('Content filtering applied to router', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'dns' => $dnsAddress,
                'categories' => $categories,
                'domains_blocked' => count($allBlockedDomains),
            ]);

            return back()->with('success', 'Content filtering policies applied to ' . $router->name . ' (' . count($allBlockedDomains) . ' domains blocked)');
        } catch (\Exception $e) {
            Log::error('Failed to apply content filtering', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Failed to apply policies: ' . $e->getMessage());
        }
    }

    /**
     * Get domain lists for selected categories
     */
    private function getCategoryDomains(array $categories): array
    {
        $domains = [];

        // Define domain lists for each category
        $categoryDomains = [
            'adult' => [
                'pornhub.com', 'xvideos.com', 'xnxx.com', 'redtube.com', 'youporn.com',
                'xhamster.com', 'tube8.com', 'spankbang.com', 'txxx.com', 'eporner.com',
            ],
            'gambling' => [
                'bet365.com', 'betway.com', 'sportingbet.com', 'bwin.com', '888casino.com',
                'pokerstars.com', 'betfair.com', 'williamhill.com', 'ladbrokes.com',
            ],
            'social' => [
                'facebook.com', 'instagram.com', 'twitter.com', 'tiktok.com', 'snapchat.com',
                'linkedin.com', 'pinterest.com', 'reddit.com', 'tumblr.com', 'whatsapp.com',
            ],
            'gaming' => [
                'twitch.tv', 'steam.com', 'epicgames.com', 'roblox.com', 'minecraft.net',
                'leagueoflegends.com', 'fortnite.com', 'pubg.com', 'callofduty.com',
            ],
            'video' => [
                'youtube.com', 'netflix.com', 'hulu.com', 'disneyplus.com', 'primevideo.com',
                'hbo.com', 'twitch.tv', 'vimeo.com', 'dailymotion.com',
            ],
            'advertising' => [
                'doubleclick.net', 'googlesyndication.com', 'googleadservices.com',
                'facebook.com/ads', 'adnxs.com', 'advertising.com', 'criteo.com',
            ],
        ];

        foreach ($categories as $category) {
            if (isset($categoryDomains[$category])) {
                $domains = array_merge($domains, $categoryDomains[$category]);
            }
        }

        return array_unique($domains);
    }
}
