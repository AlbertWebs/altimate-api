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
        Schema::create('lnmo_api_response', function (Blueprint $table) {
            $table->integer('lnmoID', true);
            $table->integer('user_id')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->string('Amount', 20)->nullable();
            $table->string('MpesaReceiptNumber', 20)->nullable();
            $table->string('CheckoutRequestID', 100);
            $table->string('MerchantRequestID', 100);
            $table->string('TransactionDate', 20)->nullable();
            $table->string('PhoneNumber', 15)->nullable();
            $table->dateTime('updateTime')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lnmo_api_response');
    }
};
