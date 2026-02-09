# M-Pesa C2B Registration Guide

## Overview

This guide explains how to register your C2B webhook URLs with Safaricom M-Pesa to enable automatic payment processing for PPPoE users.

## Prerequisites

1. M-Pesa API credentials (Consumer Key, Consumer Secret, Shortcode, Passkey)
2. Publicly accessible domain with HTTPS
3. Laravel application running and accessible

## Method 1: Using Artisan Command (Recommended)

The easiest way to register C2B URLs is using the provided artisan command.

### Basic Usage

```bash
php artisan mpesa:register-c2b
```

This will:
- Use default credentials from `.env` file
- Register URLs: `https://zimaradius.net/api/mpesa/c2b/validation` and `https://zimaradius.net/api/mpesa/c2b/confirmation`
- Show confirmation prompt before proceeding

### With Tenant-Specific Credentials

If you have multiple tenants with their own M-Pesa accounts:

```bash
php artisan mpesa:register-c2b --tenant=1
```

### With Custom URLs

```bash
php artisan mpesa:register-c2b \
  --validation-url=https://custom.com/api/mpesa/c2b/validation \
  --confirmation-url=https://custom.com/api/mpesa/c2b/confirmation
```

### Response Type Options

```bash
# Completed (default) - Safaricom waits for your response
php artisan mpesa:register-c2b --response-type=Completed

# Cancelled - Safaricom doesn't wait for your response
php artisan mpesa:register-c2b --response-type=Cancelled
```

### Example Output

```
🚀 Starting M-Pesa C2B URL Registration...

📋 Configuration:
╔══════════════════╦══════════════════════════════════════════╗
║ Setting          ║ Value                                    ║
╠══════════════════╬══════════════════════════════════════════╣
║ Environment      ║ sandbox                                  ║
║ Shortcode        ║ 600000                                   ║
║ Validation URL   ║ https://yourdomain.com/api/mpesa/c2b/... ║
║ Confirmation URL ║ https://yourdomain.com/api/mpesa/c2b/... ║
║ Response Type    ║ Completed                                ║
╚══════════════════╩══════════════════════════════════════════╝

Do you want to proceed with C2B URL registration? (yes/no) [yes]:
> yes

📡 Registering URLs with Safaricom...

✅ C2B URLs registered successfully!

📝 Response: C2B URLs registered successfully
```

## Method 2: Programmatic Registration

You can also register C2B URLs programmatically in your code:

```php
use App\Services\MpesaService;

$mpesa = new MpesaService([
    'consumer_key' => 'YOUR_CONSUMER_KEY',
    'consumer_secret' => 'YOUR_CONSUMER_SECRET',
    'shortcode' => 'YOUR_SHORTCODE',
    'passkey' => 'YOUR_PASSKEY',
    'environment' => 'sandbox', // or 'production'
]);

$result = $mpesa->registerC2BURLS(
    validationUrl: 'https://yourdomain.com/api/mpesa/c2b/validation',
    confirmationUrl: 'https://yourdomain.com/api/mpesa/c2b/confirmation',
    responseType: 'Completed'
);

if ($result['success']) {
    // Registration successful
    echo $result['message'];
} else {
    // Registration failed
    echo $result['message'];
}
```

## Method 3: Manual Registration via Daraja Portal

1. Log in to [Safaricom Daraja Portal](https://developer.safaricom.co.ke/)
2. Navigate to your app
3. Go to "APIs" → "C2B"
4. Register URLs:
   - **Validation URL**: `https://zimaradius.net/api/mpesa/c2b/validation`
   - **Confirmation URL**: `https://zimaradius.net/api/mpesa/c2b/confirmation`
   - **Response Type**: Completed

---

## Multi-Tenant C2B Registration

### Understanding Multi-Tenant Setup

Your system supports **multiple tenants** using different M-Pesa paybills. All tenants use the **SAME callback URLs** but payments are automatically routed to the correct tenant:

```
https://zimaradius.net/api/mpesa/c2b/validation
https://zimaradius.net/api/mpesa/c2b/confirmation
```

**How it works:**
1. Each tenant has their own paybill number (e.g., Tenant A: `600123`, Tenant B: `789456`)
2. ALL tenants register the SAME URLs on their Daraja portals
3. When payment comes in, system identifies tenant by looking up the user's `account_number`
4. Payment is saved with the correct `tenant_id`

### For Tenants Using Their Own M-Pesa API

When a tenant wants to use their own M-Pesa credentials:

#### Step 1: Tenant Configures Admin Panel

1. Tenant logs into admin panel
2. Goes to **Settings → Payment Gateway → M-Pesa**
3. Enters their credentials:
   - Consumer Key
   - Consumer Secret
   - Shortcode (their paybill number)
   - Passkey
   - Environment (sandbox/production)
4. Enables **"Use Own API"** toggle
5. Saves settings

#### Step 2: Register C2B URLs (3 Options)

**Option A: Automated Registration via Command (Recommended)**

You or the tenant can run:

```bash
# Replace 5 with the actual tenant ID
php artisan mpesa:register-c2b --tenant=5
```

This command:
- Retrieves tenant's credentials from database
- Automatically calls Safaricom's C2B registration API
- Registers `https://zimaradius.net/api/mpesa/c2b/*` on their behalf
- Shows confirmation of success/failure

**Example Output:**
```
🚀 Starting M-Pesa C2B URL Registration...

📋 Configuration:
╔══════════════════╦══════════════════════════════════════════╗
║ Setting          ║ Value                                    ║
╠══════════════════╬══════════════════════════════════════════╣
║ Environment      ║ production                               ║
║ Shortcode        ║ 600123 (Tenant's paybill)               ║
║ Validation URL   ║ https://zimaradius.net/api/mpesa/c2b/... ║
║ Confirmation URL ║ https://zimaradius.net/api/mpesa/c2b/... ║
║ Response Type    ║ Completed                                ║
╚══════════════════╩══════════════════════════════════════════╝

✅ C2B URLs registered successfully!
```

**Option B: Tenant Registers Manually on Daraja Portal**

Provide tenant with these instructions:

1. Log into [Safaricom Daraja Portal](https://developer.safaricom.co.ke/) with THEIR account
2. Navigate to THEIR app/paybill
3. Go to "APIs" → "C2B"
4. Click "Register URL"
5. Enter:
   - **Validation URL**: `https://zimaradius.net/api/mpesa/c2b/validation`
   - **Confirmation URL**: `https://zimaradius.net/api/mpesa/c2b/confirmation`
   - **Response Type**: Completed
6. Click "Submit"

**Option C: Automated During Tenant Setup**

You can integrate the registration into your tenant onboarding workflow:

```php
use App\Services\MpesaService;
use App\Models\TenantPaymentGateway;

// After tenant saves M-Pesa settings
$gateway = TenantPaymentGateway::where('tenant_id', $tenantId)
    ->where('provider', 'mpesa')
    ->first();

if ($gateway && $gateway->use_own_api) {
    $mpesa = new MpesaService([
        'consumer_key' => $gateway->mpesa_consumer_key,
        'consumer_secret' => $gateway->mpesa_consumer_secret,
        'shortcode' => $gateway->mpesa_shortcode,
        'passkey' => $gateway->mpesa_passkey,
        'environment' => $gateway->mpesa_env,
    ]);

    $result = $mpesa->registerC2BURLS(
        validationUrl: config('app.url') . '/api/mpesa/c2b/validation',
        confirmationUrl: config('app.url') . '/api/mpesa/c2b/confirmation'
    );
    
    // Show result to tenant
}
```

### For Tenants Using Default/Shared M-Pesa

Tenants who don't have their own M-Pesa API:

1. They use YOUR default paybill (configured in `.env`)
2. No registration needed - you already registered the URLs
3. Users pay using YOUR shortcode
4. System still tracks `tenant_id` correctly via user lookup

### Verifying Multi-Tenant Registration

After registering URLs for a tenant, verify it's working:

```bash
# Test with tenant's user account
curl -X POST https://zimaradius.net/api/mpesa/c2b/validation \
  -H "Content-Type: application/json" \
  -d '{
    "TransactionType": "Pay Bill",
    "BillRefNumber": "TENANT_USER_ACCOUNT_NUMBER",
    "TransAmount": "500"
  }'
```

Expected response:
- `{"ResultCode":0,"ResultDesc":"Accepted"}` if user exists
- `{"ResultCode":1,"ResultDesc":"Rejected"}` if user doesn't exist

### Important Notes for Multi-Tenant

> [!IMPORTANT]
> **Each tenant using their own M-Pesa MUST register the URLs on their Daraja portal!**
> 
> Without registration, Safaricom won't know where to send callbacks for that specific paybill.

> [!WARNING]
> **All tenants use the SAME URLs** - `https://zimaradius.net/api/mpesa/c2b/*`
> 
> Do NOT create tenant-specific URLs like `https://tenant1.zimaradius.net/api/...`

> [!TIP]
> **Use the artisan command for easy registration:**
> ```bash
> php artisan mpesa:register-c2b --tenant=TENANT_ID
> ```
> This saves time and eliminates manual errors.

---


## Environment Configuration

Ensure your `.env` file has the following M-Pesa credentials:

```env
# M-Pesa Configuration
MPESA_ENV=sandbox
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=600000
MPESA_PASSKEY=your_passkey

# Make sure APP_URL is set correctly
APP_URL=https://yourdomain.com
```

## Testing the Integration

### Step 1: Verify Webhook Endpoints

Test that your endpoints are accessible:

```bash
curl https://yourdomain.com/api/mpesa/c2b/validation
curl https://yourdomain.com/api/mpesa/c2b/confirmation
```

Both should return 200 OK or a validation error.

### Step 2: Simulate C2B Callback (Sandbox)

Use the test data below to simulate a payment:

```bash
curl -X POST https://yourdomain.com/api/mpesa/c2b/confirmation \
  -H "Content-Type: application/json" \
  -d '{
    "TransactionType": "Pay Bill",
    "TransID": "TEST'.date('YmdHis').'",
    "TransTime": "'.date('YmdHis').'",
    "TransAmount": "500",
    "BusinessShortCode": "600000",
    "BillRefNumber": "0712345678",
    "MSISDN": "254712345678",
    "FirstName": "John",
    "LastName": "Doe"
  }'
```

**Important**: Replace `0712345678` with an actual `account_number` from your `network_users` table.

### Step 3: Monitor Logs

Watch the Laravel logs to see payment processing:

```bash
tail -f storage/logs/laravel.log | grep "M-Pesa C2B"
```

You should see logs like:
```
[2026-02-08 09:43:20] local.INFO: M-Pesa C2B Confirmation received {"trans_id":"TEST20260208094320","account_number":"0712345678","amount":"500"...}
[2026-02-08 09:43:20] local.INFO: M-Pesa C2B Confirmation: Payment record created {"payment_id":1,"tenant_id":1,"user_id":15,"amount":"500"...}
[2026-02-08 09:43:20] local.INFO: M-Pesa C2B Confirmation: Payment processed successfully {"tenant_id":1,"expires_at":"2026-03-08 09:43:20"...}
```

### Step 4: Verify Database

Check that payment was created:

```sql
SELECT id, user_id, tenant_id, receipt_number, amount, phone, paid_at, status
FROM tenant_payments
ORDER BY id DESC LIMIT 1;
```

Check that user was credited:

```sql
SELECT id, username, account_number, expires_at, status
FROM network_users
WHERE account_number = '0712345678';
```

## How Users Pay

### For PPPoE/Static IP Users

1. User dials **M-Pesa** on their phone
2. Selects **Lipa na M-Pesa** → **Paybill**
3. Enters your **Business Number** (Shortcode)
4. Enters their **Account Number** (from `network_users.account_number` - usually their phone number like `0712345678`)
5. Enters **Amount**
6. Enters **M-Pesa PIN**
7. Confirms transaction

### Behind the Scenes

Once the user completes payment:

1. Safaricom sends a callback to your **Validation URL**
   - System checks if account exists
   - Returns "Accepted" or "Rejected"

2. If accepted, Safaricom sends callback to **Confirmation URL**
   - System creates payment record with `tenant_id`
   - Credits user by extending `expires_at`
   - Unsuspends user on MikroTik
   - Returns "Success"

## Troubleshooting

### URLs Not Registering

**Error**: "Invalid Access Token"
- Solution: Verify your Consumer Key and Consumer Secret are correct

**Error**: "Bad Request - Invalid ShortCode"
- Solution: Ensure shortcode matches your M-Pesa account

### Payments Not Processing

**Missing user**
- Check logs: `M-Pesa C2B Confirmation: User not found`
- Solution: Verify user's `account_number` matches what they entered

**Duplicate payment**
- Check logs: `Payment already processed`
- This is normal - M-Pesa may send duplicate callbacks

**No tenant_id**
- Check logs for `tenant_id` field
- Solution: User must exist with a valid `tenant_id`

### Testing in Production

1. Switch environment in `.env`:
   ```env
   MPESA_ENV=production
   ```

2. Re-register URLs with production credentials:
   ```bash
   php artisan mpesa:register-c2b
   ```

3. Make a real test payment with a small amount

## Support

For M-Pesa API issues:
- [Safaricom Daraja Documentation](https://developer.safaricom.co.ke/Documentation)
- [Daraja Support](https://developer.safaricom.co.ke/support)

For system issues, check:
- Laravel logs: `storage/logs/laravel.log`
- Server logs: Check nginx/apache error logs
