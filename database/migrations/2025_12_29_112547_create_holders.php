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
        Schema::create('holders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index()->nullable(false);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->uuid('account_id')->index()->nullable();
            $table->string('full_name')->nullable(false);
            $table->string('username')->unique()->nullable(false);
            $table->string('email')->nullable();
            $table->json('phone_numbers')->nullable();
            $table->boolean('active')->nullable();
            $table->enum('type',['MERCHANT_ADMINISTRATOR', 'MERCHANT_USER', 'SELLER_USER'])->nullable(false)->default('MERCHANT_USER');
            //$table->enum('type', ['MERCHANT_USER', 'SELLER_USER'])->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holders');
    }
};
