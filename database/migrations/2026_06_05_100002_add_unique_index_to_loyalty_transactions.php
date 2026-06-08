<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->unique(['appointment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            // Aggiunge l'indice semplice che MySQL usa per la FK prima di droppare l'unico composito.
            $table->index('appointment_id');
            $table->dropUnique(['appointment_id', 'type']);
        });
    }
};
