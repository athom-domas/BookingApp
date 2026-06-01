<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table): void {
            $table->text('email_greeting')->nullable()->after('cancellation_policy');
            $table->text('email_footer_note')->nullable()->after('email_greeting');
            $table->string('email_accent_color', 7)->nullable()->after('email_footer_note');
        });
    }

    public function down(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table): void {
            $table->dropColumn(['email_greeting', 'email_footer_note', 'email_accent_color']);
        });
    }
};
