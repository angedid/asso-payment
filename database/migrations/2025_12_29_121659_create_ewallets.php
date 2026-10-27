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
        Schema::create('e_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('holder_id')->nullable(false);
            $table->foreign('holder_id')->references('id')->on('holders')->onDelete('cascade');
            $table->json('accounts')->nullable(false);
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
        Schema::dropIfExists('e_wallets');
    }
};
