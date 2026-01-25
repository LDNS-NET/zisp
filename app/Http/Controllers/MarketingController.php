<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class MarketingController extends Controller
{
    /**
     * Handle Service Landing Pages (Mikrotik, Hotspot, etc.)
     */
    public function service($slug)
    {
        $services = [
            'mikrotik-billing-system' => [
                'title' => 'Mikrotik Billing & Management Software - ZISP',
                'description' => 'Complete Mikrotik Radius billing solution. Automate disconnects, speed limits, and PPPoE sessions directly from your dashboard.',
                'keywords' => 'Mikrotik billing, radius server mikrotik, mikrotik user manager alternative, ISP bandwidth management',
                'hero_title' => 'Automated Mikrotik Billing Made Simple',
                'hero_subtitle' => 'Stop managing routers manually. Sync users, plans, and disconnects automatically.',
                'features' => [
                    'Real-time RouterOS API Sync',
                    'Auto-disconnect on Expired Subscription',
                    'Dynamic Queue Management',
                    'PPPoE & Hotspot User Support',
                    'Live Bandwidth Monitoring'
                ]
            ],
            'hotspot-billing' => [
                'title' => 'WiFi Hotspot Billing Software with M-Pesa & Vouchers',
                'description' => 'Turn your WiFi into a business. ZISP Hotspot billing supports voucher generation, M-Pesa push, and splash page customization.',
                'keywords' => 'Hotspot billing software, WiFi voucher system, Mpesa hotspot integration, captive portal billing',
                'hero_title' => 'Monetize Your WiFi Hotspot',
                'hero_subtitle' => 'Generate vouchers, accept M-Pesa automatically, and control user sessions.',
                'features' => [
                    'Beautiful Splash Pages',
                    'Voucher Generation & Printing',
                    'M-Pesa STK Push Integration',
                    'Trial / Free Access Limits',
                    'Social Media Login (Coming Soon)'
                ]
            ],
            'pppoe-billing' => [
                'title' => 'Automated PPPoE Billing System for WISP & Fiber ISPs',
                'description' => 'Streamline your PPPoE client management. Auto-suspend non-paying users and manage static IPs with ZISP.',
                'keywords' => 'PPPoE server billing, WISP billing software, Fiber ISP management, automated internet billing',
                'hero_title' => 'Best PPPoE Billing for WISPs',
                'hero_subtitle' => 'Manage thousands of PPPoE subscribers with automated billing and suspension.',
                'features' => [
                    'Static IP Management',
                    'Pool & Profile Sync',
                    'Automated Suspension',
                    'SMS Reminders 3 Days Before Expiry',
                    'Customer Portal for Renewals'
                ]
            ],
            'mobile-money-integration' => [
                'title' => 'African ISP Billing with M-Pesa, Paystack & Flutterwave',
                'description' => 'Accept payments automatically. ZISP integrates seamlessly with M-Pesa (Kenya/Tanzania), MTN Mobile Money, Paystack, and Flutterwave.',
                'keywords' => 'ISP payment gateway, Mpesa billing integration, Paystack for ISP, mobile money automated billing',
                'hero_title' => 'Automated Payments for African ISPs',
                'hero_subtitle' => 'Say goodbye to manual receipting. Instant reconciliation for M-Pesa, MTN, and Cards.',
                'features' => [
                    'M-Pesa STK Push (Kenya/Tanzania)',
                    'Paystack & Flutterwave Integration',
                    'Automatic Payment Reconciliation',
                    'Instant Service Reconnection',
                    'Daily Financial Reports'
                ]
            ],
            'african-isp-billing-system' => [
                'title' => 'Best ISP Billing Software in Africa - ZISP',
                'description' => 'A complete billing solution tailored for African ISPs. Supports M-Pesa, multiple routers, and automated SMS notifications.',
                'keywords' => 'ISP software Africa, internet billing system Africa, telecom billing software',
                'hero_title' => 'Built for the Unique Challenges of African ISPs',
                'hero_subtitle' => 'Offline capabilities, mobile money first, and lightweight bandwidth usage.',
                'features' => [
                    'Works with Low Bandwidth',
                    'Mobile-First Admin Panel',
                    'SMS-Based Customer Communication',
                    'Multi-Currency Support',
                    'Local Support Team'
                ]
            ]
        ];

        if (!array_key_exists($slug, $services)) {
            abort(404);
        }

        $data = $services[$slug];
        $data['slug'] = $slug;
        $data['canonical'] = "https://zispbilling.cloud/{$slug}";

        return Inertia::render('Marketing/ServiceLanding', [
            'seo' => $data
        ]);
    }

    /**
     * Handle Country Landing Pages
     */
    public function country($country)
    {
        // Slug format: isp-billing-software-kenya -> "kenya" via route parameter logic or we parse it here.
        // We'll define the route as /isp-billing-software-{country}
        
        $countryMap = [
            'kenya' => [
                'name' => 'Kenya',
                'currency' => 'KES',
                'payment_methods' => 'M-Pesa, Airtel Money',
                'flag' => '🇰🇪'
            ],
            'uganda' => [
                'name' => 'Uganda',
                'currency' => 'UGX',
                'payment_methods' => 'MTN MoMo, Airtel Money',
                'flag' => '🇺🇬'
            ],
            'tanzania' => [
                'name' => 'Tanzania',
                'currency' => 'TZS',
                'payment_methods' => 'M-Pesa, Tigo Pesa, Airtel',
                'flag' => '🇹🇿'
            ],
            'ghana' => [
                'name' => 'Ghana',
                'currency' => 'GHS',
                'payment_methods' => 'MTN MoMo, Vodafone Cash',
                'flag' => '🇬🇭'
            ],
            'nigeria' => [
                'name' => 'Nigeria',
                'currency' => 'NGN',
                'payment_methods' => 'Paystack, Flutterwave, Transfers',
                'flag' => '🇳🇬'
            ],
            'zambia' => [
                'name' => 'Zambia',
                'currency' => 'ZMW',
                'payment_methods' => 'Airtel Money, MTN Money',
                'flag' => '🇿🇲'
            ],
            'rwanda' => [
                'name' => 'Rwanda',
                'currency' => 'RWF',
                'payment_methods' => 'MTN MoMo',
                'flag' => '🇷🇼'
            ],
            'south-sudan' => [
                'name' => 'South Sudan',
                'currency' => 'SSP',
                'payment_methods' => 'mGurush',
                'flag' => '🇸🇸'
            ],
            'somalia' => [
                'name' => 'Somalia',
                'currency' => 'USD',
                'payment_methods' => 'EVC Plus, Premier Wallet',
                'flag' => '🇸🇴'
            ],
            'drc' => [
                'name' => 'DRC',
                'currency' => 'USD',
                'payment_methods' => 'M-Pesa, Orange Money, Airtel',
                'flag' => '🇨🇩'
            ],
            'ethiopia' => [
                'name' => 'Ethiopia',
                'currency' => 'ETB',
                'payment_methods' => 'Telebirr',
                'flag' => '🇪🇹'
            ],
        ];

        if (!array_key_exists($country, $countryMap)) {
            abort(404);
        }

        $cData = $countryMap[$country];
        $slug = "isp-billing-software-{$country}";

        $seo = [
            'title' => "ISP Billing Software in {$cData['name']} - M-Pesa & Mikrotik Integrated",
            'description' => "The leading ISP management system in {$cData['name']}. Automate your billing with {$cData['payment_methods']} and Mikrotik integration. Start your trial today.",
            'keywords' => "ISP software {$cData['name']}, Mikrotik billing {$cData['name']}, internet business software, {$cData['payment_methods']} integration",
            'hero_title' => "The Best ISP Billing Software in {$cData['name']} {$cData['flag']}",
            'hero_subtitle' => "Automate your internet business in {$cData['name']} with local payment integrations like {$cData['payment_methods']}.",
            'country' => $cData,
            'slug' => $slug,
            'canonical' => "https://zispbilling.cloud/{$slug}"
        ];

        return Inertia::render('Marketing/CountryLanding', [
            'seo' => $seo
        ]);
    }
}
