# M-Pesa C2B Integration Guide

This document explains the M-Pesa Customer to Business (C2B) integration, URL registration, and the technical workflow for processing payments in ZimaRadius.

## 1. Overview

C2B (Customer to Business) allows your customers to pay for internet packages using your M-Pesa Paybill or Till Number. Funds are transferred from the customer's M-Pesa wallet to your Business Short Code.

The application automatically:
1.  **Validates** the payment (checks if the account number exists).
2.  **Confirms** the payment (records the transaction).
3.  **Extends** the user's internet expiry date.
4.  **Unsuspends** the user on the MikroTik router (if applicable).

---

## 2. URL Registration

Safaricom requires you to register "Callback URLs" so they know where to send payment notifications. 

### Important URL Requirements:
- URLs **must** be HTTPS in production.
- URLs **must not** contain the word "mpesa" (Safaricom security policy). 
- **ZimaRadius uses clean URLs:**
  - **Validation:** `https://yourdomain.com/api/payments/c2b/validate`
  - **Confirmation:** `https://yourdomain.com/api/payments/c2b/confirm`

### How to Register URLs:

#### A. System-Level (Default Paybill/Till)
If you are using the primary credentials defined in your `.env`:

**For Paybills (Default):**
```bash
php artisan mpesa:register-c2b
```

**For Till Numbers (Buy Goods):**
```bash
php artisan mpesa:register-c2b --shortcode-type=till
```
*Follow the interactive prompt to confirm.*

#### B. Tenant-Specific (Custom API)
If a tenant provides their own M-Pesa credentials in the Settings:
- Choose the **Shortcode Type** (Paybill or Till) in the dropdown.
- URLs are **automatically registered** when they save their credentials.
- They can also manually trigger registration by clicking the **"Register C2B URLs with Safaricom"** button in **Settings → Payment**.

---

## 3. Paybill vs Till Numbers

While both methods are supported for **STK Push** and **C2B URL Registration**, there is a key difference in how Safaricom handles manual payments:

| Feature | Paybill | Till Number (Buy Goods) |
| :--- | :--- | :--- |
| **STK Push** | Supported (CustomerPayBillOnline) | Supported (CustomerBuyGoodsOnline) |
| **URL Registration** | Supported | Supported |
| **Manual Payment Identification** | **High** (Account Number required) | **Low** (No Account Number prompt) |

### ⚠️ Important Limitation for Till Numbers:
When a customer makes a **manual manual payment** (entering your Till number via the M-Pesa menu), Safaricom **does not prompt them for an Account Number**. 

Because of this, the notification sent to ZimaRadius will have an empty `BillRefNumber`. The system will not be able to automatically identify which subscriber made the payment. 

**Recommendation:**
- Use **Paybill** if you want to support both automated STK pushes and manual walk-in payments.
- Use **Till Numbers** primarily if your business relies almost exclusively on **STK Push triggers** (where the system already knows the user from the app context).

---

## 3. The Technical Workflow

### Step 1: Validation
When a customer enters your Paybill and Account Number, Safaricom sends a request to our validation endpoint.
- **Route:** `POST /api/payments/c2b/validate`
- **Logic:** The app looks up the `BillRefNumber` (Account Number) in the `network_users` table.
- **Response:** 
  - If found: Returns `{"ResultCode": 0, "ResultDesc": "Accepted"}`.
  - If not found: Returns `{"ResultCode": 1, "ResultDesc": "Rejected"}`.

### Step 2: Confirmation
Once the customer completes the payment, Safaricom sends a final notification.
- **Route:** `POST /api/payments/c2b/confirm`
- **Logic:** 
  - Verifies the `TransID` (Receipt Number) hasn't been processed before.
  - Creates a `TenantPayment` record.
  - **MSISDN Handling:** In production, Safaricom hashes the phone number. We ignore the hash and store the phone number from the user's profile to ensure history is readable.
  - Extends the user's expiry date based on their selected package.

---

## 4. Testing & Simulation

Before going live, you can test the flow using the Sandbox environment.

### C2B Simulation Command:
```bash
php artisan mpesa:simulate-c2b --amount=10 --msisdn=254700000000 --bill-ref=USER_ACCOUNT
```
This mimics a customer payment and allows you to verify if the payment is recorded and the expiry extended.

---

## 5. Troubleshooting

### Logs
All M-Pesa activity is logged. You can monitor it in real-time:
```bash
tail -f storage/logs/laravel.log | grep "M-Pesa C2B"
```

### Common Errors:
- **401 Unauthorized:** Check your Consumer Key and Secret.
- **URL Registration Failed:** Ensure your site is accessible via public HTTPS and the shortcode is active.
- **No data appearing:** Check the "URL Management" tab in your Safaricom Daraja portal. If URLs are missing, run the registration command again.

> **Note:** Safaricom only allows ONE registration call in production. To update URLs, you must first delete the existing ones on the Daraja portal under URL Management.
