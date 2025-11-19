# Aviation Scholarships Manager
A custom WordPress plugin for managing, importing, and displaying aviation-related scholarships.

This plugin provides:

- Custom Post Type: **Scholarship**
- Taxonomies for **Categories** and **License Types**
- ACF-powered structured fields (created programmatically)
- Google Sheets importer (CSV URL pull, webhook push, manual upload)
- Automatic hourly sync via cron (optional)
- WP-CLI import command
- Admin settings page for all importer features
- Clean backend UI with sortable columns, filters, and structured data

---

## Features

### 🎯 Custom Post Type: `scholarship`
Each scholarship becomes a WordPress post with structured metadata:

- Deadline (Y-m-d)
- Number of Awards
- Maximum Amount
- College Program?
- Category (taxonomy)
- License Types (taxonomy)
- Location
- Eligibility (Every / Female / Minority)
- External application link
- Raw JSON row data
- Source ID (used for upsert)

### 🗂 Programmatic ACF Field Groups
All ACF fields are registered via `acf_add_local_field_group()` for stability and version control.

No UI configuration required.

---

## Import System

The plugin supports **three import modes**.

### 1. Manual Import (Admin)

Admin page:  
**Dashboard → Scholarships → Import Settings**

Options include:

- Upload CSV file
- Import from Google Sheets CSV URL
- Trigger full import manually
- Configure sheet URL + webhook secret

All imports produce a summary and update the internal import logs.

---

### 2. Automatic Hourly Sync (Cron)

Enable under:

**Scholarships → Import Settings → Auto-Sync**

When enabled, WP-Cron runs every hour:

```php
$importer->run_auto_sync();
