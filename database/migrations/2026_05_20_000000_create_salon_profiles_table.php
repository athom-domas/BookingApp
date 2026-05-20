<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Il mio salone');
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->default('#1d4ed8');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_profiles');
    }
};
