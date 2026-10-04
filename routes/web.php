<?php

/*
|--------------------------------------------------------------------------
| Web Routes — index
|--------------------------------------------------------------------------
| This file is deliberately just a table of contents. Every actual route lives
| in one of the files below, grouped by who can reach it. If you are hunting a
| bug, start by deciding which of these four files owns the URL.
|
|   routes/web/public.php   No login required — the landing page.
|   routes/web/auth.php     Login, register, forgot/reset password.
|   routes/web/manager.php  Everything behind ['auth', 'manager'].
|   routes/web/tenant.php   Everything behind ['auth'] for tenants.
|   routes/web/dev.php      Throwaway debug endpoints. Safe to delete.
|
| Two rules that have caused silent bugs here before:
|
|  1. If the same METHOD + URI is registered twice, the LAST one wins and the
|     first is dead code. Duplicates are easy to miss across 300 lines, which
|     is why the file is split.
|  2. If the same route NAME is used twice, `php artisan route:cache` fails.
|
| Run `php artisan app:audit` after editing to catch both, plus routes that
| point at controller methods or views that don't exist.
*/

require __DIR__ . '/web/public.php';
require __DIR__ . '/web/auth.php';
require __DIR__ . '/web/manager.php';
require __DIR__ . '/web/tenant.php';
require __DIR__ . '/web/dev.php';
