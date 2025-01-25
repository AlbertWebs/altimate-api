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
        Schema::create('orders_product', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tax', 191)->nullable();
            $table->string('total', 191)->nullable();
            $table->integer('product_id')->nullable();
            $table->integer('orders_id')->nullable();
            $table->integer('qty')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders_product');
    }
};
