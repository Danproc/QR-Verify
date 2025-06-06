# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

There are no specific build, lint, or test commands for this WordPress plugin. Development workflow involves:

- **Plugin Testing**: Test in a WordPress environment by copying to `/wp-content/plugins/` and activating
- **Database Updates**: Run `vqr_create_tables()` after schema changes or use plugin deactivation/reactivation
- **Frontend Testing**: Access `/app/` URLs after plugin activation (requires rewrite rule flush)
- **User Role Setup**: Use `vqr_force_setup_roles()` function manually if user roles need reset

## Architecture Overview

### Plugin Structure
This is a WordPress plugin transitioning from a traditional QR code generation tool to a SaaS platform for cannabis product verification. The architecture has two distinct layers:

1. **Legacy Admin System** (`includes/admin-page-modern.php`): WordPress admin interface for traditional QR code management
2. **Frontend SaaS System** (`frontend/` + `includes/frontend-*.php`): Custom dashboard at `/app/` URLs for subscription users

### SaaS Architecture

**Frontend Routing System** (`includes/frontend-router.php`):
- Custom rewrite rules map `/app/` URLs to internal pages
- Blocks admin access for non-admin users, redirecting to `/app/`
- Routes like `/app/strains`, `/app/generate`, `/app/dashboard`

**User Role System** (`includes/user-roles.php`):
- Custom roles: `qr_customer_free`, `qr_customer_starter`, `qr_customer_pro`, `qr_customer_enterprise`
- Subscription-based capabilities and quota limits
- Users get `read: false` to prevent admin dashboard access

**Quota Management**:
- Monthly QR generation limits stored in user meta (`vqr_current_usage`, `vqr_monthly_quota`)
- Quota resets automatically on 1st of each month
- Tracks "generation quota" (total created per month), not "active quota"
- Deleting QR codes does NOT restore quota (by design)
- Plan-based quotas: Free (50), Starter (300), Pro (2500), Enterprise (unlimited/-1)

### Strain Management System

**Custom Post Type** (`includes/strain-post-type.php`):
- Cannabis strain/product data with custom fields
- User ownership isolation via `post_author`

**Ownership Layer** (`includes/strain-ownership.php`):
- Users can only see/edit their own strains
- Functions: `vqr_create_user_strain()`, `vqr_get_user_strains()`, `vqr_user_can_manage_strain()`
- Capabilities: All QR customer roles can create/manage strains

**Frontend Interface** (`frontend/pages/strains.php`):
- Full CRUD interface with modal forms
- File uploads for product images
- AJAX form submission with progress feedback

### Database Schema

**Core Tables**:
- `wp_vqr_codes`: Generated QR codes with scan tracking and user ownership
- `wp_vqr_email_verification`: Email verification tokens for registration
- `wp_vqr_tos_acceptance`: Terms of Service acceptance tracking with IP/timestamp
- `wp_vqr_security_scans`: Advanced analytics with geolocation and security scoring
- `wp_vqr_security_alerts`: Security incident tracking with severity levels
- `wp_vqr_sticker_orders` & `wp_vqr_sticker_order_items`: Physical sticker ordering

**WordPress Integration**:
- Uses WordPress users, roles, and capabilities system
- Custom post types for strain data
- User meta for subscription and quota data
- Database version tracking with automatic schema updates

### Frontend Template System

**Base Template** (`frontend/templates/base.php`):
- Provides SaaS dashboard layout with sidebar navigation
- Mobile-first responsive design with "Verify 420" branding
- CSS framework with `vqr-` prefixed classes

**Page System** (`frontend/pages/`):
- `dashboard.php`: User analytics and overview
- `strains.php`: Strain management interface
- `generate.php`: QR code generation with quota checking

**Assets** (`frontend/assets/`):
- `app.js`: AJAX form handling, mobile menu, notifications
- CSS files provide dark theme with glass morphism effects

### Key Integrations

**QR Code Generation**:
- PHPQRCode library for QR image generation
- TCPDF library for PDF sticker sheet generation
- Logo overlay and batch code burning capabilities

**File Handling**:
- WordPress media library integration for uploads
- ZIP download generation for bulk QR exports
- PDF generation with cut contours for printing

**Security**:
- WordPress nonces for CSRF protection
- Capability-based access control
- Input sanitization and validation

## Important Technical Notes

### User Permission System
The plugin uses a failsafe system in `includes/strain-ajax.php` that automatically adds strain capabilities to QR customer roles if they're missing. This ensures users can always create strains even if the initial role setup failed.

### Frontend vs Admin Separation
Users with QR customer roles are completely blocked from WordPress admin and redirected to `/app/` URLs. Only users with `manage_options` capability can access the traditional admin interface.

### File Upload Handling
Strain forms use `FormData` for file uploads with proper AJAX handling. The JavaScript in `app.js` includes fallback logic for submit button detection across different form structures.

### CSS Architecture
The frontend uses a custom CSS framework with `vqr-` prefixes. Design follows Apple/Mac aesthetic with dark themes, glass morphism, and mobile-first responsive layouts optimized for 420px width.

### Session Management
The plugin starts PHP sessions on frontend pages for tracking purposes but only when not in admin areas.

### Development Patterns
- All database operations use WordPress functions (`$wpdb`, `get_user_meta`, etc.)
- AJAX handlers follow WordPress conventions with `wp_ajax_*` actions
- Frontend routing integrates with WordPress rewrite system
- File uploads use WordPress media handling functions
- User isolation enforced through user-specific database queries
- Capability-based access control with feature gating per subscription tier
- Template system uses output buffering to inject content into base layout