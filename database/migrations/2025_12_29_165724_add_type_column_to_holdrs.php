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
        Schema::table('holders', function (Blueprint $table) {
            $table->enum('type',['MERCHANT_USER', 'SELLER_USER'])
                ->nullable(false)->default('MERCHANT_USER')
                ->after('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holders', function (Blueprint $table) {

        });
    }
};
