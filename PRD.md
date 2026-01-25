# Product Requirement Document (PRD): ZISP (ISP Management System)

## 1. Product Context
ZISP is a cloud-native, multi-tenant SaaS platform designed to automate the operations of Internet Service Providers (ISPs) and Wireless ISPs (WISPs). It serves as an end-to-end OS for ISPs, handling everything from radius authentication to automated billing, payment reconciliation, field workforce management, and network security.

## 2. System Architecture
The system employs a **Multi-Tenant** architecture using database-per-tenant isolation for maximum security and scalability.

- **Stack**: 
  - **Frontend**: Vue 3 (Composition API) + Inertia.js + TailwindCSS + Lucide Icons.
  - **Backend**: Laravel 12 (PHP 8.2+).
  - **Database**: MySQL 8.0+ (Tenant separation via `stancl/tenancy`).
  - **AAA Server**: FreeRADIUS (v3.0+) interacting with a dedicated Radius Database.
  - **Router Integration**: RouterOS API (EvilFreelancer) for direct Mikrotik control.
  - **Queue System**: Redis (Horizon) for high-volume jobs (SMS, Router Sync, content filtering).

## 3. Core Modules & Functional Requirements

### 3.1 Authentication & AAA (Radius)
The heart of the ISP operations, managing internet access.
- **Protocol**: RADIUS (Remote Authentication Dial-In User Service).
- **Supported Modes**: 
  - **PPPoE**: User/Password authentication for home routers.
  - **Hotspot**: Captive portal authentication using Vouchers or Click-to-connect.
  - **Static IP**: Queue management for leased lines.
- **Features**:
  - **NAS Management**: CRUD operations for Mikrotik/Cisco routers.
  - **Session Management**: Live view of active sessions (`radacct`).
  - **CoA (Change of Authorization)**: Immediate disconnect/speed-change.
  - **Bandwidth Enforcement**: Dynamic PCQ/Simple Queues via Mikrotik API.

### 3.2 Network Security & Compliance
Advanced network controls managed directly from the dashboard.
- **Content Filtering**:
  - **Mechanism**: Mikrotik DNS Static entries + Layer 7 Firewall Filters.
  - **Categories**: Adult, Gambling, Social Media, Gaming, Video Streaming.
  - **Implementation**: Enforced at router level per-tenant policies.
  - **DNS Hijacking**: Force-redirects user DNS to controlled servers (e.g. 1.1.1.3).
- **Network Topology**: Visual mapping of routers, switches, and APs.
- **Router Alerts**: Real-time notifications for downtime or high CPU usage.

### 3.3 Field Operations (Workforce Management)
A complete Uber-like dispatch system for technicians.
- **Installation Lifecycle**:
  - **New Request**: Created from Lead or Manual Entry.
  - **Scheduling**: Calendar view for dispatchers.
  - **Technician App**: "My Installations" view for field staff.
  - **Checklists**: Mandatory technical checklists (Signal Quality, Cable path) required to close jobs.
  - **Proof of Work**: Photo uploads (Antenna alignment, Router placement) before completion.
  - **Customer Sign-off**: Digital rating (1-5 stars) and feedback collection.
- **Inventory Integration**: Deduct equipment (CPE, Router) from stock upon installation.

### 3.4 Billing & Subscription Engine
Automated financial operations to ensure revenue assurance.
- **Models**:
  - **Prepaid**: Pay-before-service (expiry dates enforced strictly).
  - **Postpaid**: Monthly recurring invoicing.
- **Lifecycle**:
  - **Invoicing**: Auto-generation of PDF invoices.
  - **Reminders**: Automated SMS/Email/WhatsApp reminders.
  - **Suspension**: Auto-disconnect via Radius/Mikrotik API upon non-payment.
  - **Reconnection**: Auto-reconnect within seconds of payment receipt.
- **Expense Management**: Track operational costs (Tower Rent, Fiber Backhaul) to calculate true Net Profit.

### 3.5 Payment Gateway Integration
A unified abstraction layer supporting 20+ global and regional gateways.
- **Architecture**: `TenantPaymentGateway` model with `Crypt::encryptString` for credentials.
- **Supported Providers**:
  - **Global**: PayPal, Stripe.
  - **Africa/Mobile Money**: M-Pesa (Daraja), Airtel Money, Tigo Pesa, HaloPesa, Telebirr, Hormuud, Zaad, Wave, Orange Money.
  - **Aggregators**: Flutterwave, Paystack, Intasend.
- **Transactions**: Real-time validation and automated receipt generation.

### 3.6 CRM & Communication
- **Lead Management**: Kanban-style pipeline (New -> Contacted -> Survey -> Installed).
- **Ticketing System**: Internal helpdesk with priorities and staff assignment.
- **Omni-channel Messaging**: 
  - **SMS**: Two-way SMS via Africa's Talking / TalkSasa.
  - **WhatsApp**: Integration for notifications and support.
- **Self-Care Portal**: End-user dashboard to check usage, pay bills, and lodge tickets.

### 3.7 Analytics & Reporting
- **Financial Analytics**: Revenue vs Expenses, ARPU (Average Revenue Per User).
- **Traffic Analytics**: Bandwidth consumption trends.
- **Predictive Analytics**: Churn risk scoring based on payment patterns and usage drops.
- **Report Builder**: Custom SQL/Query builder for ad-hoc data extraction.

## 4. Database Schema (Key Entities)

### System Database (Landlord)
- `tenants`, `domains`: SaaS management.

### Tenant Database (Per ISP)
- **Users & Auth**: `users` (Staff), `roles`, `permissions`.
- **Customers**: `network_users` (Radius attributes, Coordinates, Package ID).
- **Network**: `tenant_mikrotiks` (Routers), `tenant_equipments` (Inventory).
- **Operations**: `tenant_installations` (Jobs), `tenant_installation_checklists`, `tenant_installation_photos`.
- **Finance**: `packages`, `invoices`, `payments`, `tenant_expenses`.
- **Communication**: `tenant_sms`, `tickets`, `leads`.
- **Settings**: `tenant_settings` (JSON stored configs for Content Filter, etc.).

### Radius Database
- Standard FreeRADIUS schema: `radcheck`, `radreply`, `radacct`, `nas`.

## 5. Deployment & DevOps
- **OS**: Ubuntu 22.04 LTS.
- **Web Server**: Nginx.
- **Queues**: Redis + Horizon (Critical for handling high-concurrency Radius events).
- **Scheduler**: Cron running `schedule:run`.
- **SSL**: Let's Encrypt Wildcard.

## 6. Non-Functional Requirements
- **Security**: Encryption at rest for API Keys. DNS-over-HTTPS support for content filtering.
- **Latency**: Radius Auth response < 300ms.
- **Offline First**: Technician app should cache job details (Future scope).
- **Compliance**: Data retention for 12 months (Traffic Logs).
