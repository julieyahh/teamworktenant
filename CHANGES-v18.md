# v18 — Login spinner · ID camera · Data privacy · Due-date dashboard · Payments as lists

## Installing over your current project

Source only — `vendor/`, `node_modules/` and `storage/` are not in the zip.

```bash
# from your existing project root
cp -r /path/to/tenants-v18/{app,config,database,docs,resources,routes,tests} .
cp /path/to/tenants-v18/CHANGES-v18.md .
php artisan migrate            # 1 new migration, additive
php artisan config:clear && php artisan view:clear
php artisan test               # expect 113 passing
```

**Before going live, open `config/privacy.php`** and confirm the contact details
and retention periods. The notice shows those numbers to tenants, so they have
to be true. They can also be set in `.env`:

```
PRIVACY_CONTACT_NAME="Pinikitan Rental Management Office"
PRIVACY_CONTACT_EMAIL=office@yourdomain.com
PRIVACY_CONTACT_PHONE=09XXXXXXXXX
PRIVACY_RETENTION_YEARS=5
PRIVACY_REJECTED_RETENTION_MONTHS=6
PRIVACY_NOTICE_VERSION=2026-09
```

**The camera needs HTTPS.** Browsers only allow the camera on `https://` or
`localhost`. On `http://192.168.x.x` it cannot open, so the app falls back to
"Take / choose a photo", which opens the camera app on phones. Put the live site
on HTTPS and the in-page camera works everywhere.

---

## 1. Login — "Please wait" spinner
`resources/views/auth/login.blade.php`

- Pressing **Sign In** shows a full-screen spinner with "Please wait…". The
  button itself also switches to a spinner.
- The spinner only appears after the browser's own field checks pass, so it
  never covers a form that still needs fixing.
- It clears if the user comes back with the Back button.
- After 8 seconds it says "taking longer than usual". After 30 seconds it
  gives the page back, so the page can never get stuck.
- The form opts out of the global double-submit guard
  (`data-allow-multi-submit`) and locks itself instead, so the two don't fight.

## 2. Registration — camera captures the Valid ID
`resources/views/partials/id-camera.blade.php` (new), `landing.blade.php`, `auth/register.blade.php`

- The Valid ID field is now a **"Capture your Valid ID → Open Camera"** box.
- The camera opens (rear camera on phones) with an ID-card guide frame. The
  user can capture, review, retake, and then "Use this photo".
- If the device has more than one camera, a switch button appears.
- The photo goes into the **same `valid_id` file input** as a JPG, resized to
  2000px max and kept under 5MB. The server, validation and storage are
  unchanged.
- Nothing is recorded. The camera stream is only a live preview and stops
  when the box closes, the photo is taken, or the tab is hidden.
- The box says why when the camera can't open (not HTTPS, permission blocked,
  no camera, camera busy) and offers the file/camera-app fallback.
- The 1×1 picture is unchanged.

## 3. Registration — Data Privacy (RA 10173)
`partials/data-privacy-notice.blade.php` (new), `config/privacy.php` (new), migration, `RegisterController`, `User`

- New **Data Privacy Notice** covering what is collected, why, the camera,
  who can see it, how long it is kept, the data subject's rights, the NPC,
  contact details and consent.
- It opens from the registration forms and from the footer. The footer's old
  dead "Privacy Policy" link now opens it.
- Required **consent checkbox** on both registration forms. The notice's
  "I have read this and I agree" button ticks it.
- **Enforced server-side.** `data_privacy` is `accepted`. `terms` is now also
  `accepted`: it was only ever checked in the browser before.
- Consent is recorded on the user in two new columns:
  - `privacy_consent_at`
  - `privacy_consent_version`

  Existing users stay NULL, meaning they registered before the notice existed.

## 4. Manager dashboard — due-date monitoring, fits one screen
`app/Services/DueDateMonitor.php` (new), `DashboardController`, `manager/dashboard.blade.php`

**Due Date Monitor**
- One list of everything with a due date:
  - **Rent**: what is owed, including the penalty.
  - **Utility bills**: what is *left* on the bill, not the original total.
  - **Lease end dates**.
- Covers anything overdue plus anything due in the next 30 days.
- Tabs: All / Overdue / Today / 7 days / 8–30 days / Lease ends.
- Search by tenant or room.
- It opens on **Overdue** when there is anything overdue.
- Each row links to that lease's Collections page, or to the bill.

**Rules**
- A lease that owes nothing is never shown as overdue.
- A paid-up lease shows its next due date in green.
- Terminated leases are ignored.
- `DueDateMonitor` is **read-only**: it never writes a balance or a date.

**Summary tiles**
- Six tiles: Total, Occupied %, Vacant, Pending apps, **Overdue ₱** and
  **Due in 7 days ₱**.

**Layout**
- The pending and approved tenant tables became one **Tenants** card with
  tabs.
- The status donut and breakdown became one compact card.
- **Fits the screen with no page scroll** at 1920×1080, 1440×900 and 1366×768.
  The lists scroll inside their own panels.
- Below 1200px wide, or 700px tall, it falls back to a normal scrolling page.

## 5. Tenant Payments — lists, not tables
`resources/views/tenant/payments.blade.php`

All six tables on the page are now list rows. Each row has an icon, a title,
details underneath, the amount on the right, and its status or action. They
stack cleanly on phones.

The six lists:
- Payment History
- Rent By Month
- What You Owe
- Advance Rent By Room
- Proof of Utility Billing (modal)
- Active Lease Details (modal)

Part-paid bills and months show a progress bar. Same data and the same rules
as before.

Also fixed: the lease-details modal called `$lease->due_date->format()`
without a null check, so it crashed for a lease with no due date.

## Tests
`tests/Feature/V18ChangesTest.php` has 11 new tests. The suite is at
**113 passing** (103 before).

They cover:
- the monitor's buckets, including the paid-up, stale-date, terminated,
  30-day-window and partial-bill cases;
- the dashboard with and without anything due;
- the payments page containing no `<table>`;
- the login spinner;
- registration rejected without privacy consent or without terms;
- consent being recorded and the captured ID being stored;
- both registration pages rendering the camera box and the consent checkbox.

The browser side was also checked in headless Chrome with a fake camera:

- capture → JPG in the form;
- "I agree" ticks consent;
- the login overlay;
- the dashboard's no-scroll height at three PC sizes plus a phone size.
