<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stomp_counters', function (Blueprint $table) {
            $table->id();
            $table->string('counter_name')->unique();
            $table->bigInteger('value')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });

        // Initialize all counters
        $counters = [
            'connections_total',
            'connections_active',
            'messages_published',
            'messages_received',
            'messages_acked',
            'messages_nacked',
            'transactions_begun',
            'transactions_committed',
            'transactions_aborted',
            'errors_connection',
            'errors_publish',
            'errors_subscribe',
            'errors_broker_unavailable',
            'errors_broker_timeout',
        ];

        foreach ($counters as $counter) {
            DB::table('stomp_counters')->insert([
                'counter_name' => $counter,
                'value' => 0,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stomp_counters');
    }
};
