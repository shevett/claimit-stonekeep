# Current Status

## Known Issues

- Community moderation is implemented and enforced (see docs/community-moderation.md) but has no cross-community pending queue, no audit trail, and no submitter notifications
- Tenant deprovisioning drops the database but never cleans up that tenant's S3 assets (`images/tenants/<prefix>/...`, `staging/tenants/<prefix>/...`) - manual cleanup required
- No UI to list or revoke a tenant's existing admins yet - only the "grant by email" path exists (`templates/tenant-edit.php`)
- Per-tenant OAuth configuration (own client ID/domain restriction) is still unbuilt - tenants currently share the main site's Google OAuth app via the signed handoff-token flow

## Technical Debt

- public/index.php is ~2100 lines
- routing is manual
- `tenant_info` view/migrations (`20260709200828_add_db_name_to_tenants`, `20260709200829_create_tenant_info_view`) are dead schema - superseded by the control-plane-first tenant lookup in `public/index.php`'s bootstrap, but not yet dropped

## Recently completed (multitenant)

- Tenant bootstrap now distinguishes unknown subdomains (redirect to the control plane) from disabled/unprovisioned/unreachable tenants (styled 503 page, `templates/tenant-unavailable.php`) - see `includes/tenants.php`'s `getControlPlaneTenantByPrefix()`
- `postal_codes` (zip/geo reference data) is shared across all tenant databases via a cross-database view instead of being duplicated per tenant at provisioning time
- Tenant image/staging uploads are now nested under `images/tenants/<prefix>/...` and `staging/tenants/<prefix>/...` in S3, per the layout agreed in `claimit-infra`'s `multitenant.md`
- A super-admin can bootstrap a tenant's first admin by email from the tenant editor (`templates/tenant-edit.php`), which also fixed a bug where `is_admin` was silently reset on every OAuth login (`src/AuthService.php`)

## Next Priorities

1. Tune community moderation: add a cross-community pending queue, audit logging, and submitter notifications (see docs/community-moderation.md § Known gaps)
2. S3 cleanup on tenant deprovision, and a UI to list/revoke tenant admins
