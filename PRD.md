# Product Requirements Document (PRD) — ZISP (filled)

This PRD summarizes the ZISP Mikrotik automated onboarding and management system using information present in the repository.

## 1. Overview
- App name: ZISP
- One-line summary: Automated Mikrotik onboarding, monitoring and multi-tenant device management platform.
- Primary goal: Eliminate manual Mikrotik provisioning and provide operators with a single-dashboard to onboard, monitor, and manage devices at scale.
- Target market / customers: ISPs, wireless ISPs, network operators, managed service providers, and network engineers who use Mikrotik devices.
- Platforms: Web application (Laravel backend, Vue 3 frontend). API endpoints for device sync and integrations.

## 2. Background & Motivation
- Current context: Many operators manually provision Mikrotik devices, which is slow, error-prone and hard to scale. ZISP automates registration via downloadable scripts and a secure token-based sync endpoint.
- Business objectives:
  - OBJ1: Reduce onboarding time to near-zero per device and lower support costs.
  - OBJ2: Enable subscription-based revenue through tenant management and premium features.

## 3. Scope (MVP)
Core capabilities that must be in the initial release (MUST):
- One-click device registration and script generation (downloadable RouterOS script).
- Device sync endpoint with 64-char token-based authentication and secure reporting.
- Dashboard: device list, device detail, live status, active sessions, basic controls (reboot, reprovision).
- Scheduler and background jobs: periodic status checks and automated recovery.
- Multi-tenant isolation (stancl/tenancy integration) and role-based access.

High value but lower priority (SHOULD):
- Payments & billing integrations (Paystack, Flutterwave, Mpesa) to enable subscription purchases.
- Automated reporting and export (CSV/Excel) of device data.

Nice-to-have (NICE):
- Advanced analytics and trend dashboards; mobile app clients.

Out of scope for MVP:
- Native mobile apps and offline-first device management.

## 4. Users & Personas
- Primary persona: ISP Operator — goal: rapidly deploy and manage many Mikrotik devices with minimal manual effort; pain points: manual script deployment, device misconfiguration.
- Secondary persona: Support Engineer — goal: diagnose and remediate device issues quickly; tasks: view logs, reprovision, regenerate tokens.
- Tertiary persona: Super Admin — goal: manage tenants, pricing plans, and global system settings.

## 5. Key User Journeys
- Onboard a device (Operator)
  - Step 1: Login to dashboard and click "Add Device"
  - Step 2: Enter device metadata and create device
  - Step 3: Download generated RouterOS script and run on Mikrotik
  - Step 4: Device calls sync endpoint and appears as Connected in dashboard

- Recover a device (Support)
  - Step 1: Detect offline device in dashboard
  - Step 2: Trigger reprovision or reboot from device detail
  - Step 3: Verify device reconnects via status checks

## 6. Detailed Requirements
- Feature: One-Click Registration
  - Description: Generate a RouterOS script and unique device token for new devices.
  - Acceptance criteria:
    - AC1: User can create device and download script in < 60s
    - AC2: Devices using the script authenticate with 64-char token and appear in dashboard within 1 minute
  - Success metric: 95% of devices appear connected within 5 minutes of running script

- Feature: Real-time Dashboard
  - Description: Show devices, status, active sessions, and controls.
  - Acceptance criteria:
    - AC1: Dashboard lists devices and last-seen time
    - AC2: Actions (reboot, reprovision) return success/failure status

## 7. Non-Functional Requirements
- Performance: Typical operations — create device <100ms, generate script <50ms, sync endpoint <100ms (per repository stats).
- Availability: Target 99.9% for core API endpoints.
- Scalability: Support thousands of devices per tenant via background jobs and batched checks (system documented to handle large batches).
- Security & privacy: Token-based device auth, encrypted credentials, HTTPS-ready; follow secure storage for sensitive keys.
- Accessibility: Dashboard should follow standard accessible patterns (target WCAG AA for core pages later).

## 8. Data & Integrations
- Key data: tenants, devices (mikrotiks), active sessions, device tokens, scheduled jobs, logs.
- Integrations / 3rd-party services:
  - SMS: africastalking
  - Payments: Paystack, Flutterwave, Mpesa, Intasend
  - RouterOS API: evilfreelancer/routeros-api-php
  - Analytics/exports: maatwebsite/excel

## 9. Technical Architecture (high level)
- Suggested stack (repo): Frontend: Vue 3; Backend: Laravel 12 (PHP 8.2); DB: MySQL/Postgres; Queue: Redis/Database; Tenancy: stancl/tenancy; Auth: Laravel Sanctum for API tokens.
- API design: RESTful routes for dashboard; public device sync endpoints under `api/mikrotiks/*` with token auth.
- Data retention & backups: regular DB backups and logs stored in `storage/` with retention policy to be defined.

## 10. Privacy & Compliance
- Consider data residency and local telecom regulations for payment gateways and C2B callbacks (Mpesa, Momo). Ensure PCI scope avoidance by using payment providers' hosted flows where possible.

## 11. Success Metrics & KPIs
- Acquisition: new tenant signups per month — target: 10+ for first 6 months.
- Activation: % devices successfully onboarding within 24 hours — target: 90%.
- Engagement: devices reporting heartbeats every 5 minutes — target: 95% uptime per device.
- Retention: tenant retention at 30 days — target: 80%.
- Revenue: monthly recurring revenue (MRR) — target to be defined by pricing.

## 12. Roadmap & Milestones
- Phase 0 (Discovery): finalize requirements, run small pilot — 1–2 weeks
- Phase 1 (MVP): deliver MUST features (registration, sync, dashboard, scheduler) — 3–6 weeks
- Phase 2 (Beta): payment integration, export, additional tests — 2–4 weeks
- Phase 3 (v1): polish UI/UX, scalability hardening, public launch — 3–6 weeks

## 13. Risks & Mitigations
- Risk: Compromised device tokens — Mitigation: short token lifetimes, easy token rotation, logging and revocation UI.
- Risk: RouterOS compatibility differences — Mitigation: ship conservative scripts, maintain device compatibility matrix and testing guide.
- Risk: Payment gateway integration failures in certain markets — Mitigation: support multiple gateways and fallbacks.

## 14. QA & Acceptance Criteria for Launch
- Functional QA: All MUST feature acceptance criteria passed, device onboarding and reprovision flows validated.
- Non-functional QA: Performance checks (per-repo benchmarks), load testing of scheduler, security scan completed.
- Beta feedback threshold: critical bugs <= 2; NPS target to be defined.

## 15. Open Questions
- What pricing tiers and limits (devices per tenant) should we ship for MVP?
- Which payment gateways are required at launch for your primary markets?
- Who will manage operations and support after launch?

---
Appendix: Next steps
- Confirm pricing, billing gateways and launch timeline.
- Prioritize any additional MUST features and identify owners.
- Run a short pilot with 5–10 devices and validate onboarding metrics.

