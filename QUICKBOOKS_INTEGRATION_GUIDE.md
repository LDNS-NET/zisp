# QuickBooks Online Integration: Technical Architecture Guide

This document explains how the QuickBooks Online (QBO) integration is built within the ZISP multi-tenant environment.

## 1. Architecture: The "Central App" Model
The system uses a **Centralized OAuth 2.0 Application model**. This means:
- **One Application**: You register a single application on the Intuit Developer Portal.
- **Central Credentials**: The `Client ID` and `Client Secret` are stored in the server's `.env` file and shared across all tenants.
- **User Permission**: When a tenant clicks "Connect", they authorize *your* central app to access *their* specific QuickBooks data.

### Why this model for 1000s of tenants?
- **Ease of Use**: Customers don't need to be developers or manage API keys.
- **Standardized Setup**: You have full control over the integration names and branding shown in QuickBooks.
- **Scalability**: One set of credentials manages the OAuth handshake, but each tenant receives their own unique, isolated tokens.

---

## 2. Multi-Tenant Data Isolation
Even though the APP is central, the **DATA is 100% isolated**.

### Token Storage
Every tenant has their own entry in the `tenant_settings` table (Central Database):
- `realmId`: The unique ID of that tenant's QuickBooks Company.
- `access_token` & `refresh_token`: Unique keys valid only for that specific tenant.
- These tokens ensure that even if two tenants are syncing at the same time, they can never see or touch each other's accounting data.

### Request Filtering
We use **Stancl Tenancy** and **Laravel Global Scopes**:
- Each financial model (`TenantInvoice`, `TenantPayment`, `TenantExpenses`, `TenantEquipment`) includes a `tenant_id`.
- The `QuickBooksService` filters all queries by the current `tenant('id')`.
- In background jobs, we use `tenancy()->initialize($tenant)` to move the entire application context into that specific tenant's sandbox before syncing starts.

---

## 3. Core Components

### `App\Services\QuickBooksService`
The "Brain" of the integration.
- Handles OAuth token exchanges and automatic refreshes.
- Maps internal models to QuickBooks entities (e.g., mapping a `TenantInvoice` to a QBO `Invoice`).
- Performs the actual API calls using the official Intuit V3 PHP SDK.

### `App\Http\Controllers\QuickBooksAuthController`
Manages the user-facing OAuth handshake:
- `redirect()`: Sends the admin to Intuit to sign in.
- `callback()`: Receives the authorization code, exchanges it for tokens, and saves them for that specific tenant.

### `App\Console\Commands\SyncQuickBooksData`
The "Orchestrator" for background syncing:
- Loops through all tenants who have connected QuickBooks.
- **Initializes Tenancy** for each tenant to ensure strict data isolation.
- Triggers the sync for Invoices, Payments, Expenses, and Assets.
- Runs automatically every 30 minutes via the Laravel Scheduler.

---

## 4. Database Schema Changes
We added `qbo_id` to several tables to track sync status and prevent duplicates:
- `tenant_invoices`: Stores the QBO Invoice ID.
- `tenant_payments`: Stores the QBO Payment ID.
- `tenant_expenses`: Stores the QBO Purchase ID (now also indexed by `tenant_id`).
- `tenant_equipments`: Stores the QBO Item ID.

---

## 5. Security Summary
- **Encrypted Tokens**: Tokens are stored in the database and handled via HTTPS.
- **Automatic Refresh**: The system detects when a token is about to expire (within 5 minutes) and automatically uses the `refresh_token` to get a new one without user intervention.
- **Strict Scoping**: Every synchronization task is strictly scoped to the tenant ID, making it impossible for data from Tenant A to end up in Tenant B's QuickBooks.
