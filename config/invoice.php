<?php

/*
|--------------------------------------------------------------------------
| Payment invoices (added v19)
|--------------------------------------------------------------------------
| What prints at the top of the invoice a tenant downloads once a payment is
| accepted. CONFIRM THESE BEFORE GOING LIVE, or set them in .env:
|
|   INVOICE_BUSINESS_NAME="Pinikitan Rental"
|   INVOICE_ADDRESS="..."
|   INVOICE_PHONE=09XXXXXXXXX
|   INVOICE_EMAIL=office@yourdomain.com
|   INVOICE_TIN=000-000-000-000
|
| The invoice is a record of a confirmed payment. It is NOT a BIR Official
| Receipt, and says so in its footer. If the office issues ORs, the OR number
| can be noted on the payment by the manager as usual.
*/

return [
    'business_name' => env('INVOICE_BUSINESS_NAME', 'Pinikitan Rental'),
    'address'       => env('INVOICE_ADDRESS'),
    'phone'         => env('INVOICE_PHONE', env('PRIVACY_CONTACT_PHONE')),
    'email'         => env('INVOICE_EMAIL', env('PRIVACY_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS'))),
    'tin'           => env('INVOICE_TIN'),

    // INV-2026-000123 — year accepted, then the payment id. Derived, never
    // stored, so it can never drift from the payment it describes.
    'prefix' => env('INVOICE_PREFIX', 'INV'),

    'footer' => env(
        'INVOICE_FOOTER',
        'This is a system-generated invoice confirming a payment received. It is not a BIR Official Receipt.'
    ),
];
