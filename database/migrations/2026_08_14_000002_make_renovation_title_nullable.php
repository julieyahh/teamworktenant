<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Title field has been removed from the tenant renovation form — the
     * type plus the description already say what the request is, and asking a
     * tenant to invent a headline for "fix the cabinet door" is friction for
     * nothing.
     *
     * The COLUMN stays. Dropping it would throw away the titles tenants have
     * already written on existing requests, and those are the only record of
     * what some of those requests were about. It just becomes nullable, and
     * nothing writes to it any more.
     */
    public function up(): void
    {
        // Requires doctrine/dbal on older Laravel; Laravel 11+ handles this natively.
        Schema::table('renovation_requests', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Backfill anything null before making it required again, otherwise
        // the column change fails on existing rows.
        \Illuminate\Support\Facades\DB::table('renovation_requests')
            ->whereNull('title')
            ->update(['title' => \Illuminate\Support\Facades\DB::raw("COALESCE(renovation_type, 'Renovation Request')")]);

        Schema::table('renovation_requests', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
