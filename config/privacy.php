<?php

/*
|--------------------------------------------------------------------------
| Data Privacy (RA 10173 — Data Privacy Act of 2012)
|--------------------------------------------------------------------------
| Everything the Data Privacy Notice on the registration form says that is a
| business decision rather than a legal fact lives here, so the notice can
| never promise something the office is not actually doing.
|
| CONFIRM THESE BEFORE GOING LIVE. The defaults are placeholders.
|
| Bump `version` whenever the notice text changes in a way a tenant would
| care about. Each user row records the version they agreed to
| (users.privacy_consent_version), so you can tell who agreed to what.
*/

return [

    'version' => env('PRIVACY_NOTICE_VERSION', '2026-09'),

    // Who a tenant contacts to access, correct or delete their data.
    'contact_name'  => env('PRIVACY_CONTACT_NAME', 'Pinikitan Rental Management Office'),
    'contact_email' => env('PRIVACY_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),
    'contact_phone' => env('PRIVACY_CONTACT_PHONE'),

    // How long records are kept after a tenancy ends (lease, payments, IDs).
    'retention_years_after_lease' => (int) env('PRIVACY_RETENTION_YEARS', 5),

    // How long a REJECTED or withdrawn application's documents are kept.
    'rejected_retention_months' => (int) env('PRIVACY_REJECTED_RETENTION_MONTHS', 6),

];
