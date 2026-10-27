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
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('location')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->string('logo')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->boolean('active')->default(true);
            $table->string('taxpayer_number')->nullable();
            $table->string('number_trade_register')->nullable();
            $table->bigInteger('license_id')->nullable();
            $table->uuid('owner')->nullable();
            $table->json('network')->nullable(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
