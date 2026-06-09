<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_google_id_unique');
            $table->unique(['email', 'business_id'], 'users_email_business_unique');
            $table->unique(['google_id', 'business_id'], 'users_google_id_business_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_business_unique');
            $table->dropUnique('users_google_id_business_unique');
            $table->unique('email', 'users_email_unique');
            $table->unique('google_id', 'users_google_id_unique');
        });
    }
};
