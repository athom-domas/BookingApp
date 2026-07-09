<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('min_plan')->nullable();
            $table->timestamps();
        });

        DB::table('plan_features')->insert([
            ['key' => 'whatsapp_notifications', 'label' => 'Notifiche WhatsApp',     'description' => 'Promemoria appuntamenti via WhatsApp (Meta API)',    'min_plan' => 'plus',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_ai',            'label' => 'Assistente AI WhatsApp', 'description' => 'Bot AI per prenotazioni e cancellazioni via chat',  'min_plan' => 'plus',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'google_calendar',        'label' => 'Google Calendar',        'description' => 'Sincronizzazione appuntamenti con Google Calendar', 'min_plan' => 'base',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'online_payments',        'label' => 'Pagamenti online',       'description' => 'Pagamenti via Stripe Connect',                      'min_plan' => 'base',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'loyalty_program',        'label' => 'Programma fedeltà',      'description' => 'Punti fedeltà e sconti per clienti ricorrenti',     'min_plan' => 'base',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'waitlist',               'label' => 'Lista d\'attesa',        'description' => 'Gestione lista d\'attesa per slot esauriti',        'min_plan' => 'base',  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
