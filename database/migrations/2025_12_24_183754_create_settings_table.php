<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $defaults = [
            ['key' => 'acs_url', 'value' => '', 'type' => 'string', 'group' => 'acs', 'description' => 'URL ACS per i dispositivi CPE'],
            ['key' => 'acs_username', 'value' => 'acs_admin', 'type' => 'string', 'group' => 'acs', 'description' => 'Username autenticazione ACS'],
            ['key' => 'acs_password', 'value' => '', 'type' => 'password', 'group' => 'acs', 'description' => 'Password autenticazione ACS'],
            ['key' => 'inform_interval', 'value' => '3600', 'type' => 'integer', 'group' => 'acs', 'description' => 'Intervallo Inform in secondi'],
            ['key' => 'tr069_auth_method', 'value' => 'digest', 'type' => 'string', 'group' => 'acs', 'description' => 'Metodo autenticazione TR-069'],
            ['key' => 'tr069_ssl_enabled', 'value' => 'true', 'type' => 'boolean', 'group' => 'acs', 'description' => 'Abilita SSL per TR-069'],
            ['key' => 'connection_request_username', 'value' => 'cpe', 'type' => 'string', 'group' => 'connection', 'description' => 'Username Connection Request'],
            ['key' => 'connection_request_password', 'value' => '', 'type' => 'password', 'group' => 'connection', 'description' => 'Password Connection Request'],
            ['key' => 'max_devices', 'value' => '100000', 'type' => 'integer', 'group' => 'system', 'description' => 'Numero massimo dispositivi'],
            ['key' => 'session_timeout', 'value' => '7200', 'type' => 'integer', 'group' => 'system', 'description' => 'Timeout sessione in secondi'],
            ['key' => 'log_level', 'value' => 'info', 'type' => 'string', 'group' => 'system', 'description' => 'Livello di logging'],
            ['key' => 'enable_debug', 'value' => 'false', 'type' => 'boolean', 'group' => 'system', 'description' => 'Abilita modalita debug'],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
