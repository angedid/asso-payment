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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->uuid('holder_id')->index();
            $table->foreign('holder_id')->references('id')->on('holders')->onDelete('cascade');
            $table->uuid('payment_service_provider_id')->index();
            $table->foreign('payment_service_provider_id')->references('id')->on('payment_service_providers')->onDelete('cascade');
            $table->uuid('account_used')->index();
            $table->double('amount')->nullable(false);
            $table->uuid('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
            $table->text('order_number')->nullable(false);
            $table->timestamp('start_date')->useCurrent();
            $table->timestamp('end_date')->nullable();
            $table->string('status')->nullable(false);
            $table->text('details')->nullable(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
