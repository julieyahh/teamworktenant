<?php

/*
|--------------------------------------------------------------------------
| Lease rules (added v19)
|--------------------------------------------------------------------------
| Renewal starts with the tenant. They ask from their Lease Management page;
| until they do, the manager's Renew action is locked and the nightly
| `leases:renew` command leaves the lease alone.
|
|   renewal_request_window_days
|       how many days before the lease end date the tenant may ask to
|       renew. 60 = from two months before. After the end date they can
|       always ask.
*/

return [
    'renewal_request_window_days' => (int) env('RENEWAL_REQUEST_WINDOW_DAYS', 60),
];
