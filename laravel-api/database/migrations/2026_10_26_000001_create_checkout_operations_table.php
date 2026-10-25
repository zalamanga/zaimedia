<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key');
            $table->char('intent_hash', 64);
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->json('response_payload');
            $table->timestamps();
            $table->unique(['customer_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_operations');
    }
};
