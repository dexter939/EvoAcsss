<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stomp_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('collected_at')->useCurrent();
            
            // Connection metrics
            $table->integer('connections_total')->default(0);
            $table->integer('connections_active')->default(0);
            $table->integer('connections_idle')->default(0);
            $table->integer('connections_failed')->default(0);
            
            // Message metrics
            $table->bigInteger('messages_published')->default(0);
            $table->bigInteger('messages_received')->default(0);
            $table->bigInteger('messages_acked')->default(0);
            $table->bigInteger('messages_nacked')->default(0);
            $table->bigInteger('messages_pending_ack')->default(0);
            
            // Transaction metrics
            $table->bigInteger('transactions_begun')->default(0);
            $table->bigInteger('transactions_committed')->default(0);
            $table->bigInteger('transactions_aborted')->default(0);
            
            // Subscription metrics
            $table->integer('subscriptions_total')->default(0);
            $table->integer('subscriptions_active')->default(0);
            
            // Performance metrics
            $table->decimal('avg_publish_latency_ms', 10, 2)->nullable();
            $table->decimal('avg_ack_latency_ms', 10, 2)->nullable();
            $table->decimal('messages_per_second', 10, 2)->default(0);
            
            // Error metrics
            $table->integer('errors_connection')->default(0);
            $table->integer('errors_publish')->default(0);
            $table->integer('errors_subscribe')->default(0);
            $table->integer('errors_timeout')->default(0);
            $table->integer('errors_broker_unavailable')->default(0);
            $table->integer('errors_broker_timeout')->default(0);
            
            // Broker info (JSON for flexibility)
            $table->json('broker_stats')->nullable();
            
            $table->index('collected_at');
            $table->index(['collected_at', 'connections_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stomp_metrics');
    }
};
