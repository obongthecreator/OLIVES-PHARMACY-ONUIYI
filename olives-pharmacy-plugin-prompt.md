# Refined Prompt — Olives Pharmacy WordPress Plugin

> Paste everything below this line into Claude Opus 4.7 as a single message.

---

## 1. Role & Mission

You are a senior WordPress plugin engineer and UI designer. Build a complete, production-ready WordPress plugin called **"Olives Pharmacy — Inventory & Sales System"** for a Nigerian retail pharmacy. The plugin must run on a stock WordPress 6.x site, work for both logged-in staff (frontend) and admins (wp-admin), and look like a premium SaaS product — not a typical WordPress admin screen.

**Domain context (do not skip):**
- Country: Nigeria. Currency: Naira, symbol ₦, formatted with commas, no decimals (e.g., `₦5,000`, `₦1,250,000`).
- Time zone: West Africa Time (WAT, UTC+1, no daylight saving).
- Regulatory field: **NAFDAC Number** is the Nigerian drug registration number — treat it as a normal text field, alphanumeric, max 20 chars.
- Common payment behaviors: split payments are very common (Cash + Transfer, Cash + Card, all three together) — model this as a first-class feature, not an afterthought.
- "MoMo" / mobile money is *not* required; only Cash, Card, Transfer and their combinations.

---

## 2. Final Deliverable

- **One single PHP file** containing the entire plugin (procedural + classes inside the same file is fine), structured so it can be saved as `olives-pharmacy.php`, zipped, and uploaded via *Plugins → Add New → Upload Plugin*.
- The plugin must activate cleanly with **zero PHP notices, warnings, or deprecations** on PHP 8.1+ and WordPress 6.4+.
- Inline all CSS/JS that is not loaded from a CDN. No external file dependencies inside the zip.
- Add a top-of-file plugin header block with: Plugin Name, Description, Version (1.0.0), Author (Olives Pharmacy), License (GPL-2.0+), Text Domain (`olives-pharmacy`), Requires at least, Requires PHP.
- Include `if ( ! defined( 'ABSPATH' ) ) exit;` at the top.

---

## 3. Brand & Design System

| Token | Value |
|---|---|
| Primary green | `#16a34a` (Tailwind `green-600`) |
| Accent red | `#dc2626` (Tailwind `red-600`) |
| Surface | `#ffffff` |
| Soft background | `#f8fafc` (slate-50) |
| Text primary | `#0f172a` (slate-900) |
| Text muted | `#64748b` (slate-500) |
| Border | `#e2e8f0` (slate-200) |
| Pill radius | `9999px` for the header; `20px` (`rounded-[20px]`) for cards/buttons unless noted |
| Shadow | Soft, layered: `shadow-[0_4px_24px_rgba(15,23,42,0.06)]` |
| Font | Inter via Google Fonts; fallback `system-ui, sans-serif` |

**Asset loading:**
- **Tailwind CSS** via Play CDN: `https://cdn.tailwindcss.com` — extend the config inline to register `olives-green` and `olives-red` colors.
- **Iconify** for icons, restricted to the **Solar Linear** icon set: `https://code.iconify.design/3/3.1.1/iconify.min.js`. Use `<iconify-icon icon="solar:..."></iconify-icon>` consistently. Do not mix icon sets.
- **Chart.js** for analytics: `https://cdn.jsdelivr.net/npm/chart.js`.
- Enqueue these on **both** the frontend (only when an Olives shortcode is present on the page) and on the plugin's admin screens. Use `wp_enqueue_script` / `wp_enqueue_style` with proper handles and version pinning. Do not hard-code `<script>` tags into shortcode output.

**Visual language:**
- Generous whitespace, soft shadows, rounded corners everywhere (no sharp 0px corners).
- Glassmorphism only on the Home page (backdrop-blur, semi-transparent white, gradient backdrop).
- Other pages use a clean white-card-on-soft-gray layout — *not* glassmorphism, to keep data legible.
- Empty states must be designed (illustration via Iconify + helpful copy + primary action), not blank tables.
- Loading states use skeleton shimmers, not spinners.
- All interactive elements have visible hover/focus states with smooth transitions (`transition-all duration-200`).

---

## 4. Global Header Bar (renders on EVERY plugin page, frontend + admin)

A **pill-shaped header** with `border-radius: 20px` on all sides, white background, soft shadow, sitting at the top of the content area with margin around it.

Contents, left → right:
1. **Left:** Olives Pharmacy wordmark — green leaf icon (`solar:leaf-linear`) + "Olives Pharmacy" in semi-bold.
2. **Center:** Current date in long format, e.g. `Saturday, 25 April 2026`. Computed in WAT.
3. **Right (grouped):**
   - Live digital clock, format `HH:MM:SS`, monospace, updates every second via `setInterval`. Must reflect WAT regardless of the visitor's browser locale — compute as `new Date(Date.now() + (3600 * 1000) + (new Date().getTimezoneOffset() * 60 * 1000))` or equivalent. Append a small `WAT` chip beside it.
   - Staff greeting: `Welcome back, {Display Name}`. Pull from `wp_get_current_user()->display_name`; if empty, fall back to `user_login`. If logged out on the frontend, show `Welcome, Guest` and hide sensitive actions.

The header is rendered by a single PHP helper (e.g. `olives_render_header()`) that every shortcode calls first. Do not duplicate the markup six times.

---

## 5. Database Schema

On activation, create four tables using `dbDelta()`. Use the WordPress prefix (`$wpdb->prefix . 'olives_...'`). Use `utf8mb4_unicode_ci`. Add explicit indexes.

### `{prefix}olives_products`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(191) NOT NULL | indexed |
| nafdac_number | VARCHAR(50) NULL | indexed |
| batch_number | VARCHAR(50) NULL | |
| expiry_date | DATE NULL | indexed |
| quantity | INT NOT NULL DEFAULT 0 | |
| cost_price | DECIMAL(12,2) NOT NULL DEFAULT 0 | |
| selling_price | DECIMAL(12,2) NOT NULL DEFAULT 0 | |
| reorder_level | INT NOT NULL DEFAULT 5 | |
| created_at | DATETIME NOT NULL | |
| updated_at | DATETIME NOT NULL | |

### `{prefix}olives_sales`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| receipt_no | VARCHAR(30) NOT NULL UNIQUE | format `OP-YYYYMMDD-XXXX` |
| total_amount | DECIMAL(12,2) NOT NULL | |
| payment_method | VARCHAR(40) NOT NULL | enum-like: `cash`, `card`, `transfer`, `cash_transfer`, `cash_card`, `transfer_card`, `cash_transfer_card` |
| cash_amount | DECIMAL(12,2) NOT NULL DEFAULT 0 | |
| card_amount | DECIMAL(12,2) NOT NULL DEFAULT 0 | |
| transfer_amount | DECIMAL(12,2) NOT NULL DEFAULT 0 | |
| staff_id | BIGINT UNSIGNED NOT NULL | WP user id |
| staff_name | VARCHAR(191) NOT NULL | snapshot at sale time |
| created_at | DATETIME NOT NULL | indexed |

### `{prefix}olives_sale_items`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| sale_id | BIGINT UNSIGNED NOT NULL | FK indexed |
| product_id | BIGINT UNSIGNED NOT NULL | FK indexed |
| product_name | VARCHAR(191) NOT NULL | snapshot |
| unit_price | DECIMAL(12,2) NOT NULL | snapshot, editable at point of sale |
| quantity | INT NOT NULL | |
| line_total | DECIMAL(12,2) NOT NULL | |

### `{prefix}olives_stock_history`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| product_id | BIGINT UNSIGNED NOT NULL | indexed |
| product_name | VARCHAR(191) NOT NULL | snapshot |
| change_type | VARCHAR(30) NOT NULL | `added`, `restock`, `sale`, `adjustment`, `edit` |
| quantity_change | INT NOT NULL | signed (negative for sales) |
| quantity_after | INT NOT NULL | running stock after change |
| note | TEXT NULL | |
| staff_id | BIGINT UNSIGNED NULL | |
| created_at | DATETIME NOT NULL | indexed |

Store the schema version in an option (`olives_db_version`) so future upgrades can run `dbDelta` again safely.

---

## 6. Lifecycle Hooks

- `register_activation_hook`: create tables, seed page records (see §7), insert 5 demo products *only if* the products table is empty so first-run looks alive, store db version.
- `register_deactivation_hook`: do **not** delete data; only flush rewrite rules.
- `register_uninstall_hook` (separate static function or `uninstall.php`-equivalent inside the same file via `register_uninstall_hook(__FILE__, [Class, 'uninstall'])`): drop all four tables, delete the auto-created pages, delete plugin options.

---

## 7. Page Auto-Creation & Shortcodes

On activation, programmatically insert these WordPress pages if a page with the same slug doesn't already exist. Each page's content is just its shortcode.

| Title | Slug | Shortcode |
|---|---|---|
| Olives — Home | `olives` | `[olives_home]` |
| Olives — Dashboard | `olives-dashboard` | `[olives_dashboard]` |
| Olives — Stock | `olives-stock` | `[olives_stock]` |
| Olives — Sales | `olives-sales` | `[olives_sales]` |
| Olives — Financial | `olives-financial` | `[olives_financial]` |
| Olives — Analytics | `olives-analytics` | `[olives_analytics]` |

Persist the created page IDs in an option array (`olives_pages`) so navigation links between pages can be built dynamically with `get_permalink()` rather than hard-coded slugs.

Also register a top-level **wp-admin menu** "Olives Pharmacy" with the same six pages as submenus, each rendering the same shortcode output inside an admin wrapper. Capability required: `manage_options` for admin views; frontend shortcodes require the user to be logged in (show a polite "Please log in" card otherwise) — except the Home page, which is public-facing.

---

## 8. Page Specifications

Every page begins with the global header (§4) and a sub-navigation pill bar linking to the other five pages with active state highlighting.

### 8.1 Home (`[olives_home]`)
- Full-bleed gradient background: diagonal green → red → white wash, with subtle animated blob shapes (CSS only).
- Centered logo lockup: large leaf icon + "OLIVES PHARMACY" wordmark + tagline "Trusted Care, Every Day".
- Five glassmorphism cards in a responsive grid (1 col mobile, 2-3 cols tablet, 5 cols desktop). Each card = icon + title + 1-line description + arrow. Cards link to the five other pages.
  1. Dashboard — `solar:widget-2-linear`
  2. Stock — `solar:box-linear`
  3. Sales — `solar:cart-large-2-linear`
  4. Financial — `solar:wallet-money-linear`
  5. Analytics — `solar:chart-2-linear`

### 8.2 Dashboard (`[olives_dashboard]`)
- 4 stat cards in a row (stack on mobile), each with icon, label, value, and a small green/red delta vs. yesterday where computable:
  1. **Total Products** — count of rows in `olives_products`.
  2. **Total Stock** — `SUM(quantity)`.
  3. **Low Stock Alerts** — count where `quantity <= reorder_level`. Card border turns red if > 0.
  4. **Today's Sales** — `SUM(total_amount)` for sales where `DATE(created_at) = today (WAT)`.
- 3 quick-action cards linking to Stock / Sales / Analytics.
- Recent activity feed: last 8 rows from a UNION of latest sales and latest stock history, newest first, with relative time ("3 mins ago").

### 8.3 Stock / Inventory (`[olives_stock]`)
- Toolbar: live search input (filters table client-side as the user types), "Add Product" button (opens modal), "Export CSV" button.
- Product table columns: **Name | NAFDAC Number | Batch | Expiry | Quantity | Cost Price (₦) | Selling Price (₦) | Reorder Level | Actions (Edit / Delete)**.
- Rows where `quantity <= reorder_level` get a soft red background and a red "Low" pill badge.
- Rows where `expiry_date` is within 30 days get an amber "Expiring soon" pill; expired rows get a red "Expired" pill.
- Add/Edit modal: glass-card style, all fields from §5 product schema, client + server validation. Saves via admin-ajax.
- Below the table: **Stock Movement History** — last 10 rows from `olives_stock_history` (product, change type pill, qty change with +/-, qty after, staff, time). End with a right-aligned **"View Full History"** button that opens a full-screen modal with all rows, paginated.

### 8.4 Sales Terminal (`[olives_sales]`) — most important page

**Layout:** two columns. Left = product picker (60% on desktop, full width on mobile, collapses above the cart). Right = smart sales table + payment panel. On mobile, stack with cart fixed at the bottom.

**Left — Product Picker:**
- Search bar at top with `solar:magnifer-linear` icon.
- Grid or list of product cards: name, available qty, selling price (₦ formatted). Click/tap adds 1 unit to the cart. Out-of-stock products are dimmed and not selectable.

**Right — Smart Sales Table:**
- Columns: **Item | Price (₦) | Quantity | Total (₦) | ✕**.
- Price field is a text input prefixed with ₦, accepting digits + commas. On `blur` it reformats with thousands commas.
- Quantity is a numeric stepper with `−` and `+` buttons; min 1, max = product's available stock.
- Total cell auto-recalculates on any price or quantity change, reformatted with commas.
- ✕ button removes the line.
- Below the table:
  - **Subtotal**, **Grand Total** (bold, large, green).
  - **Payment Method** dropdown with the seven options:
    `Cash`, `Card`, `Transfer`, `Cash + Transfer`, `Cash + Card`, `Transfer + Card`, `Cash + Transfer + Card`.
  - When a single method is picked, no extra inputs shown; that method's stored amount = grand total.
  - When a combination is picked, render labeled inputs for each contributing method. **Default behavior:**
    - **Transfer** field is pre-filled with the grand total when Transfer is part of the combination; otherwise the *first* method in the combination is pre-filled.
    - As the user edits any field, the *last* (rightmost) field auto-recalculates so the sum always equals the grand total. If the user edits the auto-balancing field directly, the previous field becomes the new auto-balancer.
    - A small inline message shows "Balanced ✓" in green when sum matches, or "Off by ₦X" in red when it doesn't. The Complete Sale button is disabled until balanced.
  - **COMPLETE SALE** button — full width, large, green, with `solar:check-circle-linear` icon. Disabled if cart is empty or payment is unbalanced.

**On successful sale (AJAX):**
1. Server validates stock availability for each line, deducts quantities, writes one row to `olives_sales`, N rows to `olives_sale_items`, N rows to `olives_stock_history` (change_type = `sale`, signed negative).
2. Returns the receipt number and full receipt payload.
3. Frontend shows a success toast, then renders a **printable receipt** in a modal:
   - Header: "OLIVES PHARMACY", address line, phone line.
   - Receipt no, date/time (WAT), staff name.
   - Itemized lines, subtotal, payment method breakdown, grand total.
   - Footer: "Thank you for choosing Olives Pharmacy".
   - Two buttons: **Print** (opens a print-friendly version using a 58mm-wide CSS layout suitable for ESC/POS thermal printers — monospaced font, 32-character-wide rows, no colors, no images) and **New Sale** (resets the page).
   - Use `window.print()` against a hidden iframe whose body contains only the receipt HTML so the rest of the page isn't printed.
   - The printable layout must be raster-friendly (no fancy backgrounds, dashed line separators using `--------------------------------`, right-aligned amounts, totals in bold via `<b>`). This is what makes it ESC/POS-compatible when printed via the Bluetooth printer's companion browser.

### 8.5 Financial Summary (`[olives_financial]`)
- Date filter pill bar: **Today | This Week | This Month | This Year | Custom**. Custom opens a date-range picker (two `<input type="date">` fields). Selected pill is filled green.
- 3 KPI cards: **Total Revenue**, **Total Transactions**, **Average Sale** — all reflecting the selected period.
- Transaction History table: Receipt No, Date/Time, Items count, Payment Method (as a colored pill), Total, Staff. Clicking a row opens the receipt in a modal.
- "View Full History" button opens a paginated full-screen view.

### 8.6 Analytics (`[olives_analytics]`)
- Revenue Trend Line Chart (Chart.js) — last 30 days by default, with a toggle for 7 / 30 / 90 days. Smooth line, green fill gradient, no gridlines clutter.
- Top Selling Products: top 5 by units sold in the selected window, as a horizontal bar list with product name, qty sold, revenue.
- Key Insights cards (auto-generated copy from data): e.g., "Best day: Saturday — ₦142,000 avg", "Most profitable product: ...", "Stockouts this month: N".
- **Activity / System Log** section at the bottom: latest 15 entries combining sales, stock changes, product edits. Each row: icon, action sentence, staff, relative time. End with **"View Full Activity Log"** button → full-screen paginated modal.

---

## 9. AJAX / REST Endpoints

Use `admin-ajax.php` actions (both `wp_ajax_` and `wp_ajax_nopriv_` only where safe) **OR** the REST API under namespace `olives/v1`. Pick one approach and be consistent. Required endpoints:

- `products_list`, `products_save` (create + update), `products_delete`
- `sales_create`, `sales_list`, `sales_get` (returns receipt payload)
- `stock_history_list`
- `dashboard_stats`
- `financial_summary` (accepts `range` + optional `from`/`to`)
- `analytics_data` (accepts `days`)
- `activity_log`

**Every endpoint must:**
- Verify a nonce (`olives_nonce`, localized to JS via `wp_localize_script`).
- Check capability (`manage_options` for write actions, `read` for read actions on logged-in users).
- Sanitize every input (`sanitize_text_field`, `absint`, `floatval`, `wp_kses_post` as appropriate).
- Use `$wpdb->prepare()` for every query — no string interpolation into SQL.
- Return JSON via `wp_send_json_success` / `wp_send_json_error` with proper HTTP semantics.

---

## 10. Security & WordPress Standards

- No direct file access (`ABSPATH` guard).
- Escape all output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` for rich content.
- Translate every user-facing string with `__()` / `_e()` using text domain `olives-pharmacy`.
- Follow WordPress PHP coding standards: snake_case functions, prefixed with `olives_`, classes prefixed `Olives_`.
- No `eval`, no `extract`, no `$_REQUEST` without validation.
- Avoid jQuery; use vanilla JS or a small inline helper. Tailwind Play CDN is the only big external CSS payload.

---

## 11. Responsiveness & Accessibility

- Mobile-first; everything must be usable on a 360px-wide phone (Sales Terminal especially — pharmacy staff often use phones).
- Tap targets ≥ 44×44px.
- Keyboard navigable: tab order makes sense, focus rings visible.
- ARIA labels on icon-only buttons.
- Color is never the only signal (Low / Expired pills also have text + icon).
- `prefers-reduced-motion` respected.

---

## 12. Acceptance Checklist (the plugin is "done" only when all of these are true)

- [ ] Single PHP file, zero PHP warnings on PHP 8.1+ / WP 6.4+.
- [ ] Activation creates 4 tables + 6 pages + admin menu, idempotently.
- [ ] Uninstall cleanly removes tables, pages, and options.
- [ ] Pill-shaped header with live WAT clock appears on every plugin page.
- [ ] All 6 shortcodes render their pages on both frontend and admin.
- [ ] Add / edit / delete a product works end-to-end and writes to stock history.
- [ ] A complete sales flow: pick products → edit price/qty → choose `Cash + Transfer + Card` → balance auto-adjusts → submit → receipt prints in 58mm format.
- [ ] Sale correctly decrements stock and produces a uniquely-numbered receipt.
- [ ] Low-stock and expiry pills appear correctly.
- [ ] Financial filters (Today / Week / Month / Year / Custom) all return correct totals.
- [ ] Analytics chart renders with real data, top products list is correct, activity log paginates.
- [ ] All amounts display as ₦ with commas, no decimals.
- [ ] Every AJAX/REST call is nonce-protected, capability-checked, and uses prepared statements.
- [ ] No layout breaks at 360px width.

---

## 13. Output Instructions

Return the entire plugin as **one fenced PHP code block**. Do not split it. Do not omit "boilerplate". Do not insert `// ... (rest of code) ...` placeholders — paste every line. After the code block, add a short "Installation" section (3 numbered steps: save as `olives-pharmacy.php`, zip the file, upload via Plugins → Add New → Upload Plugin) and a one-paragraph note on how to seed/test the demo data.

Begin.
