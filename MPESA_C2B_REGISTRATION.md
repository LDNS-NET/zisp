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
- Register URLs: `https://yourdomain.com/api/mpesa/c2b/validation` and `https://yourdomain.com/api/mpesa/c2b/confirmation`
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
   - **Validation URL**: `https://yourdomain.com/api/mpesa/c2b/validation`
   - **Confirmation URL**: `https://yourdomain.com/api/mpesa/c2b/confirmation`
   - **Response Type**: Completed

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
