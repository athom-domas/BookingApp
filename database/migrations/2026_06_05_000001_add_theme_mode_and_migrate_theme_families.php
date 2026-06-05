<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->string('theme_mode', 10)->default('light')->after('theme');
        });

        $mapping = [
            'dark'      => ['theme' => 'luxury',    'mode' => 'dark'],
            'light'     => ['theme' => 'luxury',    'mode' => 'light'],
            'rose'      => ['theme' => 'rosa',      'mode' => 'dark'],
            'emerald'   => ['theme' => 'verde',     'mode' => 'dark'],
            'midnight'  => ['theme' => 'notte',     'mode' => 'dark'],
            'minimal'   => ['theme' => 'minimal',   'mode' => 'light'],
            'cipria'    => ['theme' => 'rosa',      'mode' => 'light'],
            'antracite' => ['theme' => 'antracite', 'mode' => 'dark'],
            'salvia'    => ['theme' => 'verde',     'mode' => 'light'],
            'viola'     => ['theme' => 'viola',     'mode' => 'dark'],
        ];

        foreach ($mapping as $oldTheme => $new) {
            DB::table('salon_profiles')
                ->where('theme', $oldTheme)
                ->update(['theme' => $new['theme'], 'theme_mode' => $new['mode']]);
        }
    }

    public function down(): void
    {
        $reverseMap = [
            ['theme' => 'luxury',    'mode' => 'dark',  'old' => 'dark'],
            ['theme' => 'luxury',    'mode' => 'light', 'old' => 'light'],
            ['theme' => 'rosa',      'mode' => 'dark',  'old' => 'rose'],
            ['theme' => 'rosa',      'mode' => 'light', 'old' => 'cipria'],
            ['theme' => 'verde',     'mode' => 'dark',  'old' => 'emerald'],
            ['theme' => 'verde',     'mode' => 'light', 'old' => 'salvia'],
            ['theme' => 'notte',     'mode' => 'dark',  'old' => 'midnight'],
            ['theme' => 'notte',     'mode' => 'light', 'old' => 'midnight'],
            ['theme' => 'minimal',   'mode' => 'light', 'old' => 'minimal'],
            ['theme' => 'minimal',   'mode' => 'dark',  'old' => 'minimal'],
            ['theme' => 'antracite', 'mode' => 'dark',  'old' => 'antracite'],
            ['theme' => 'antracite', 'mode' => 'light', 'old' => 'antracite'],
            ['theme' => 'viola',     'mode' => 'dark',  'old' => 'viola'],
            ['theme' => 'viola',     'mode' => 'light', 'old' => 'viola'],
        ];

        foreach ($reverseMap as $row) {
            DB::table('salon_profiles')
                ->where('theme', $row['theme'])
                ->where('theme_mode', $row['mode'])
                ->update(['theme' => $row['old']]);
        }

        Schema::table('salon_profiles', function (Blueprint $table) {
            $table->dropColumn('theme_mode');
        });
    }
};
