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
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable(false);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->uuid('holder_id')->nullable(false);
            $table->foreign('holder_id')->references('id')->on('holders')->onDelete('cascade');
            $table->string('card_number')->nullable(false);
            $table->string('holder_name')->nullable(false);
            $table->date('expiration_date')->nullable(false);
            $table->string('issuer_name')->nullable(false);
            $table->boolean('active')->nullable(false)->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
