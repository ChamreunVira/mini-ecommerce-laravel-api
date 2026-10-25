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
            $table->id();
            $table->string('transaction_id');
            $table->string('qr_string');
            $table->string('md5');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['khqr', 'cod'])->default('khqr');
            $table->enum('status', ['pending', 'failed', 'paid', 'refunded']);
            $table->decimal('amount', 10, 2);
            $table->timestamps();
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
