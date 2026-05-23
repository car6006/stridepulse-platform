<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            if (! Schema::hasColumn('athletes', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('athletes', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('athletes', 'display_name')) {
                $table->string('display_name')->nullable()->after('last_name');
            }

            if (! Schema::hasColumn('athletes', 'mobile_phone')) {
                $table->string('mobile_phone')->nullable()->after('display_name');
            }

            if (! Schema::hasColumn('athletes', 'mobile_phone_e164')) {
                $table->string('mobile_phone_e164')->nullable()->after('mobile_phone');
            }

            if (! Schema::hasColumn('athletes', 'whatsapp_phone')) {
                $table->string('whatsapp_phone')->nullable()->after('mobile_phone_e164');
            }

            if (! Schema::hasColumn('athletes', 'whatsapp_phone_e164')) {
                $table->string('whatsapp_phone_e164')->nullable()->after('whatsapp_phone');
            }

            if (! Schema::hasColumn('athletes', 'email')) {
                $table->string('email')->nullable()->after('whatsapp_phone_e164');
            }

            if (! Schema::hasColumn('athletes', 'onboarding_status')) {
                $table->string('onboarding_status')->nullable()->after('email');
            }

            if (! Schema::hasColumn('athletes', 'subscription_status')) {
                $table->string('subscription_status')->nullable()->after('onboarding_status');
            }

            if (! Schema::hasColumn('athletes', 'consent_popia_at')) {
                $table->timestamp('consent_popia_at')->nullable()->after('subscription_status');
            }

            if (! Schema::hasColumn('athletes', 'consent_terms_at')) {
                $table->timestamp('consent_terms_at')->nullable()->after('consent_popia_at');
            }
        });

        Schema::table('athletes', function (Blueprint $table) {
            $table->unique('mobile_phone_e164', 'athletes_mobile_phone_e164_unique');
            $table->unique('whatsapp_phone_e164', 'athletes_whatsapp_phone_e164_unique');
        });
    }

    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropUnique('athletes_mobile_phone_e164_unique');
            $table->dropUnique('athletes_whatsapp_phone_e164_unique');

            $table->dropColumn([
                'first_name',
                'last_name',
                'display_name',
                'mobile_phone',
                'mobile_phone_e164',
                'whatsapp_phone',
                'whatsapp_phone_e164',
                'email',
                'onboarding_status',
                'subscription_status',
                'consent_popia_at',
                'consent_terms_at',
            ]);
        });
    }
};
