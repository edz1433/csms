# CPSU Common Supply Management System (CSMS)

A supply/inventory management system for **Central Philippines State University** — receiving (deliveries), releasing (RIS), stock tracking, RCA/account-title expense attribution, and payment status tracking.

**Stack:** Laravel 12 · Blade · Tailwind CSS · Alpine.js · DataTables (yajra, server-side) · SweetAlert2 · Chart.js · Tom Select — **no frontend framework** (all views are Blade, progressively enhanced).

---

## Requirements

- PHP 8.2+
- MySQL (XAMPP) — database `cpsu_csms`
- Composer

## Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Environment (already configured for XAMPP MySQL: db cpsu_csms, user root)
#    Confirm APP_KEY is set; if not:
php artisan key:generate

# 3. Create the database (once)
#    In phpMyAdmin or CLI: CREATE DATABASE cpsu_csms;

# 4. Migrate + seed reference data and demo users
php artisan migrate --seed

# 5. Serve
php artisan serve
#    -> http://127.0.0.1:8000   (or via XAMPP: http://localhost/csms/public)
```

> Front-end libraries load from CDN — **no `npm` build step required**.
> Drop the official seal at `public/images/cpsu-logo.png` (falls back gracefully if absent).

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@cpsu.edu.ph` | `password` |
| Supply Staff | `supply@cpsu.edu.ph` | `password` |
| Accounting Staff | `accounting@cpsu.edu.ph` | `password` |

## Roles & access

- **Administrator** — full access to everything, incl. Item CRUD and User Management.
- **Supply Staff** — page access controlled per-user (Receiving, Releasing, Setup, etc.).
- **Accounting Staff** — **view-only everywhere**, with one write exception: marking
  released line items **Paid/Unpaid**.

Page access is a per-user checkbox list (`config/access.php` is the source of truth),
enforced by the `CheckPageAccess` middleware and reflected in the sidebar. Accounting
writes are blocked by `DenyWriteForAccountingStaff` except the payment-toggle endpoint.

## Modules

| Module | Notes |
|---|---|
| **Dashboard** | KPI cards (animated), lowest stock, recent releases |
| **Items / Stocks** | Master list + per-item stock card (running balance); item CRUD is admin-only |
| **Receiving** | PO-based delivery entry, multi-line, increments on-hand under row lock |
| **Releasing** | RIS entry with stock guard, RCA snapshot, `{YEAR}-{MONTH}-{LOCATION_CODE}-{SEQ}` numbering, payment toggle |
| **Setup** | Locations (campus/office), Units, Fund Clusters, Account Titles, Suppliers |
| **Reports** | Releases summary (charts), stock card, payment status — CSV + PDF export |
| **User Management** | Accounts, roles, per-page access, password reset (admin-only) |

## Key data-integrity guarantees

- Every stock-changing transaction (Receiving/Releasing) runs in a **DB transaction**
  with `lockForUpdate()` on the item row — no lost updates or oversells under concurrency.
- Releases re-check stock authoritatively server-side and **roll back entirely** if any
  line is short (never a partial release).
- `release_items.rca_code` is a **snapshot** taken at release time — historical records
  never change if an Account Title's RCA code is later edited.
- RIS sequence is per-location via a locked counter table (`location_release_counters`).

---

_Built as a phased enhancement per the CPSU CSMS build spec._
