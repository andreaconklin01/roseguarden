# Dëftesë PRO — audit and rebuild report

Source reviewed: `dft/dft.php` (2,886 lines, v6.4.9) and `dftlayt/dftlayt.php`
(394 lines, v1.2.1).

Rebuilt as a single plugin, `deftese-pro/` (v7.1.0). The two originals were
separate plugins; the layout manager is now built in, so one install provides
the whole feature set. It stands down automatically if an old standalone layout
plugin is still active, so an upgrade never produces a duplicate menu.

The brief was to rebuild and restyle **without changing what the plugin does**.
Everything below is either a defect fix that preserves observable behaviour, or a
structural/visual change that leaves behaviour alone. The three places where a
finding was deliberately *not* fixed, because fixing it would change behaviour,
are listed in [Left alone on purpose](#left-alone-on-purpose).

---

## 1. Security findings

### 1.1 Stored XSS in the grade table — **fixed**

`renderGradesTable()` wrote subject text straight into `innerHTML`:

```js
tr.innerHTML = `<td class="${rowClass}" colspan="6">${controlsHtml}<span …>${g.subject || ''}</span></td>`;
```

`g.subject` comes from `grades_json` in the database. A subject containing
`<img src=x onerror=…>` executes whenever the row is re-rendered — on import, on
add/remove/reorder, and on previous/next navigation. Because administrators can
open any user's certificates, this is a **cross-user** stored XSS, not just
self-XSS.

Fixed by building the table with `document.createElement` and `textContent`
(`assets/js/editor.js`). Posted grade sheets are additionally reduced to a known
key set server-side (`Ajax::sanitize_grades()`), so unexpected fields never
round-trip.

### 1.2 Silent ownership transfer on save — **fixed**

`deftese_ajax_save()` set `$fields['created_by'] = get_current_user_id()` and
then included that column in **both** the insert and the update paths. An
administrator opening a teacher's certificate and pressing Save reassigned the
record to themselves, which silently removed it from the teacher's list.

`created_by` is now written only when a row is created (`Ajax::save()`), and the
importer preserves the existing owner when overwriting (`Csv_Importer::save()`).

### 1.3 Redirect loop locking the whole site — **fixed**

`deftese_global_frontend_gate()` redirected every non-administrator front-end
request to `deftese_get_frontend_url()`, which falls back to `home_url('/')` when
no page contains the shortcode. Home is not a manager page, so it redirected
again: an infinite loop that takes the site down for all visitors until the
plugin is disabled over FTP. `deftese_redirect_wp_login()` fed the same loop.

`Access::frontend_gate()` now skips when no manager page exists and when the
request is already at the target URL. The gate itself is unchanged in intent —
the site is still private — and is now filterable via `deftese_gate_frontend`.

### 1.4 Password reset and registration blocked — **fixed**

`deftese_redirect_wp_login()` only exempted `action=logout`, so
`wp-login.php?action=lostpassword`, `action=rp` (the emailed reset link),
`action=register` and every POST to the login form were redirected away. Anyone
who forgot their password could not recover it.

`Access::redirect_wp_login()` now exempts any request carrying `action`, `key` or
`checkemail`, and all POSTs.

### 1.5 Timing side channel in TOTP verification — **fixed**

`deftese_totp_verify()` returned as soon as a step matched, so the response time
revealed which step in the ±1 window was correct. `Totp::verify()` checks every
step before returning.

### 1.6 `wp_login` never fired — **fixed**

The front-end login set the auth cookie by hand without firing `wp_login`, so
session managers, audit logs and security plugins never saw these sign-ins.
`Auth::complete_login()` fires it.

### 1.7 Unvalidated redirect target — **fixed**

`$_POST['deftese_redirect']` was passed to `wp_safe_redirect()` after only
`esc_url_raw()`. `wp_safe_redirect` does block off-host targets, so this was not
exploitable, but the failure mode was a silent bounce to `/wp-admin`. The value
now goes through `wp_validate_redirect()` with the manager URL as fallback.

### 1.8 Nested `$wpdb->prepare()` — **fixed**

The bulk delete interpolated an already-prepared fragment into a second
`prepare()` call:

```php
$user_where = $wpdb->prepare( "created_by = %d", $current_user_id );
$wpdb->query( $wpdb->prepare( "DELETE … WHERE id IN ({$placeholders}) AND ($user_where)", $ids ) );
```

Not injectable as written — the fragment was already a literal — but WordPress
6.2+ warns about placeholder mismatches in nested prepares, and any future edit
that put user input in `$user_where` would be a live SQL injection. All queries
are now built in `Repository` with a single `prepare()` and an explicit argument
list.

### 1.9 Broken `$wpdb` format array — **fixed**

```php
$formats = array_fill(0, count($fields), '%s');
$formats[ count($fields) - 1 ] = '%s';
$formats[ count($fields) - 2 ] = '%d';
```

This positions `%d` by counting backwards from the end of the array. It happens
to be correct today, and silently corrupts the type of whichever column lands in
that slot the moment a field is added or reordered. `Repository::formats()`
derives the specifiers from the actual value types.

### 1.10 Invalid colour written into the stylesheet — **fixed**

`sanitize_hex_color()` returns `null` for malformed input, which the layout
plugin stored and the editor then emitted as `--dp-color-main: ;`. That single
empty declaration is enough to leave the certificate title unstyled.
`Layout::sanitize()` falls back to the default, and re-validates on read as well
as on write, so a value written by an older version cannot reach the page
unchecked.

---

## 2. Correctness and performance findings

### 2.1 Previous/next loaded the entire archive — **fixed**

Both the editor and `deftese_ajax_get_cert` ran `SELECT id FROM … ` with no
limit, pulled every visible id into PHP, and called `array_search`. On a
municipality-scale table that is tens of thousands of rows fetched to find two.
`Repository::neighbours()` asks the database for exactly the two neighbouring
rows.

### 2.2 The overview fetched every grade sheet — **fixed**

`deftese_overview_get_rows()` selected `c.*`, including the `LONGTEXT`
`grades_json` column, for every certificate on the site — to render a summary
table that never displays it. `Repository::overview_rows()` selects only the ten
columns the screen shows.

### 2.3 Two schema migrations on every request — **fixed**

`deftese_check_db_version()` (on `plugins_loaded`) ran `dbDelta` plus an `ALTER
TABLE`, and `deftese_force_db_upgrade_check()` (on `init`) ran `dbDelta` plus
three `SHOW COLUMNS` and up to three more `ALTER TABLE`s. If either option failed
to persist, that fired on **every page load**. `Schema::maybe_upgrade()` is a
single guarded check; both option names are still written, so downgrading works.

### 2.4 Cached manager URL never invalidated — **fixed**

The URL was cached in a transient for an hour with no invalidation, so moving the
shortcode to another page left every redirect pointing at the old one. The cache
is now flushed on `save_post`, `deleted_post` and `switch_theme`. The lookup also
prefers `post_type = 'page'` before falling back to a site-wide scan.

### 2.5 Missing index on the ownership column — **fixed**

Every list query filters on `created_by`, and the per-user summary groups by it,
but the table had no index on it. Added `idx_created_by`.

### 2.6 Sort order dropped when paginating — **fixed**

The pagination base URL only carried `d_orderby`/`d_order` when they were already
in the query string, so clicking through pages after sorting reverted to the
default order on the second page.

### 2.7 Failed deletes reported as success — **fixed**

The single-row delete printed "U fshi me sukses" without checking whether a row
was actually removed, so a non-owner attempting a delete saw a success message
and an unchanged list. The result is now checked and reported honestly.

### 2.8 Dropped import batches counted as successes — **fixed**

The uploader's `catch` block logged to the console and moved on, leaving those
files out of both the imported and the error totals: uploading 100 files could
report "80 imported, 0 errors". Failed batches now count as errors.

### 2.9 No uninstaller — **fixed**

Deleting the plugin left the certificates table, four options, a transient and
three user-meta keys behind. `uninstall.php` cleans up, but **only** when
`DEFTESE_REMOVE_DATA` is defined as true in `wp-config.php` — deleting a plugin
must never destroy a school's records by accident.

---

## 3. Structure

The original was one 2,886-line file mixing schema, access control, routing,
SQL, HTML, CSS and JavaScript, with every function wrapped in
`if ( ! function_exists( … ) )` — the signature of repeated copy-paste merges.

| Concern | Now lives in |
| --- | --- |
| Bootstrap, autoloading | `deftese-pro.php`, `includes/class-plugin.php` |
| Schema and migration | `class-schema.php` |
| Field definitions, default sheet | `class-fields.php` |
| **All** SQL | `class-repository.php` |
| Capabilities, ownership, site gate | `class-access.php` |
| TOTP | `class-totp.php` |
| Login and 2FA flow | `class-auth.php` |
| CSV coordinates | `class-csv-map.php` |
| CSV parsing and import | `class-csv-importer.php` |
| Layout schema, sanitising, CSS variables | `class-layout.php` |
| Layout settings screen | `class-layout-screen.php` |
| Asset registration | `class-assets.php` |
| AJAX endpoints | `class-ajax.php` |
| Screens | `class-admin.php`, `class-frontend.php`, `class-editor.php`, `class-list-view.php`, `class-overview.php` |
| Markup | `includes/views/*.php` (12 templates) |

Other structural fixes:

- **The CSV map existed twice.** The row and column numbers (`cat_rows = [25, 29,
  32, 34, 38, 42, 44, 46, 53]`, the 16 scalar coordinates, the extraction
  regexes) were written out by hand in PHP *and* in JavaScript — roughly 200
  duplicated lines that had to be edited in lockstep. The map is now declared
  once in `Csv_Map` and handed to the browser as data. A differential test
  confirms both importers produce identical output (see §5).
- **`df()` in the global namespace.** A two-character global function was
  declared from inside a rendering function. Replaced by
  `DeftesePro\field()`.
- **No i18n.** `Text Domain: deftese-pro` was declared and never used; every
  string was hardcoded. 203 strings are now wrapped and
  `languages/deftese-pro.pot` ships with the plugin.

---

## 4. Redesign

The interface was rebuilt from scratch; the printed certificate was not.

**Chrome (redesigned).** Every rule was previously an inline `style` attribute —
hundreds of them, impossible to restyle or cache. There is now a token layer
(`assets/css/tokens.css`) defining one palette, type scale, spacing scale, radii
and elevation set, and a component layer (`app.css`, `auth.css`) built entirely
from those tokens. Re-theming the whole interface means editing one file.

Also: a dark theme that follows the system setting; focus-visible rings on every
control; `prefers-reduced-motion` respected; icon buttons carry text labels that
collapse to icons only under 782px; emoji replaced with inline SVG; the overview
accordion is now `<details>/<summary>` (keyboard-accessible, no JavaScript); the
confirm dialog is a real `role="dialog"` that closes on Escape and backdrop
click; `aria-sort` on sortable headers; visually-hidden labels on the checkboxes
and search field; `aria-pressed` on the edit and fullscreen toggles.

**The A4 sheet (preserved).** The certificate is a legal document, so its
geometry was ported rule-for-rule into `assets/css/certificate.css`: same class
names, same millimetre and pixel values, same `--dp-*` variables, same print
block. Inline styles became classes with identical declarations. The `!important`
flags the original used to survive theme CSS were kept on exactly the
declarations that carried them.

One regression was caught during review and fixed before commit: an early draft
of the print stylesheet set `display: none` on `.dp-app`, which is an **ancestor**
of the certificate on the front end. The original hides the page with
`visibility`, and `display: none` on an ancestor cannot be undone by a descendant
rule — it would have printed a blank page. `app.css` now sets no `display` rules
in print at all.

---

## 5. Verification

No WordPress instance was available, so behaviour was verified by differential
testing against code extracted verbatim from v6.4.9.

| Check | Result |
| --- | --- |
| PHP syntax, all 33 files | pass |
| JavaScript parse, all 5 files | pass |
| CSV import: 17 scalar fields, original vs rebuilt | **identical** |
| CSV import: 31 grade rows, original vs rebuilt | **identical** |
| CSV import: PHP vs browser implementation | **identical** |
| Layout save path, original vs rebuilt (32 keys x 3 forms) | **identical** except the colour fix in §1.10 |
| Default grade sheet, original vs rebuilt | **identical** (31 rows) |
| Layout CSS variables — defaults | **identical** (31 variables) |
| Layout CSS variables — legacy aliases | **identical** |
| Layout CSS variables — custom values | **identical** |
| Autoloader resolves all 18 classes | pass |
| All 12 referenced views exist | pass |

The CSV fixture deliberately exercises the awkward cases: mangled diacritics
(`Shk?lqyesh?m` → `Shkëlqyeshëm`), the `Shumë mirë` placeholder round-trip, bare
and parenthesised point values, decimal formatting, `FALSE` sentinels, category
rows carrying stray values that must be blanked, the column-0 subject fallback,
and registry normalisation (`123 / 2024` → `123/2024`).

**Not covered by these tests**, and worth a pass on a staging site: the rendered
A4 sheet against a printed reference copy, the 2FA enrolment round-trip against a
real authenticator app, and the redirect gate with and without a manager page.

---

## 6. Compatibility

The rebuild is a drop-in replacement. Unchanged: the table name and every column,
`DEFTESE_VERSION` / `DEFTESE_TABLE` / `DEFTESE_CAP`, the `[deftese_manager]`
shortcode, all six AJAX action names, all nonce action names, all admin page
slugs (`deftese-manager`, `deftese-new`, `deftese-overview`, `deftese-layout`),
every query argument, the `deftese_layout_settings` option and its keys including
the three legacy aliases, the three 2FA user-meta keys, and the `--dp-*` CSS
variables and certificate class names.

Existing data needs no migration.

The one deliberate packaging change is that the layout manager is no longer a
separate plugin. Its option, nonce action, page slug, capability and every
setting key are unchanged, so the screen behaves exactly as before and existing
settings are picked up as they are. `Layout_Screen::is_externally_provided()`
detects a still-active standalone layout plugin — either the 6.x companion or
the 2.0 rebuild — and skips registering the built-in screen, so the two can
coexist during an upgrade without a duplicate submenu.

New extension points: `deftese_capability`, `deftese_gate_frontend`,
`deftese_2fa_issuer`, `deftese_csv_category_rows`, `deftese_csv_bold_rows`,
`deftese_layout_capability`, and the `deftese_loaded` action.

---

## Left alone on purpose

Three findings were **not** fixed, because fixing them would change behaviour.

1. **The layout screen is reachable with `edit_posts`.** These are site-wide
   print settings: anyone who can edit a certificate can change the margins of
   every certificate the site produces. `manage_options` would be the right
   default, but tightening it could lock out staff who use it today. The
   capability is now filterable —
   `add_filter( 'deftese_layout_capability', fn() => 'manage_options' );` — and
   the default is unchanged.

2. **`deftese_format_decimals()` discards a value of `0`.** The guard is
   `if ( empty( $str ) || $str === 'FALSE' )`, and `empty('0')` is `true` in PHP,
   so a legitimate score of zero is silently dropped. This is almost certainly
   unintended, but changing it would alter what prints on existing certificates,
   so the behaviour is preserved exactly. Worth a decision from whoever owns the
   grading rules.

3. **The site-wide front-end gate.** Redirecting every visitor to the manager
   page is drastic for a WordPress site, but it is clearly the intended
   deployment model. The loop and lockout bugs were fixed; the policy was not
   touched.
