<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v18 — record Data Privacy Act (RA 10173) consent at registration.
 *
 * When the applicant ticked the box, and which version of the notice they
 * saw (config('privacy.version')). Consent you cannot show you collected is
 * consent you do not have.
 *
 * Additive only. Existing users stay NULL: they registered before the notice
 * existed and that is exactly what NULL should say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'privacy_consent_at')) {
                $table->timestamp('privacy_consent_at')->nullable()->after('terms_accepted');
            }
            if (! Schema::hasColumn('users', 'privacy_consent_version')) {
                $table->string('privacy_consent_version', 20)->nullable()->after('privacy_consent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'privacy_consent_version')) {
                $table->dropColumn('privacy_consent_version');
            }
            if (Schema::hasColumn('users', 'privacy_consent_at')) {
                $table->dropColumn('privacy_consent_at');
            }
        });
    }
};
