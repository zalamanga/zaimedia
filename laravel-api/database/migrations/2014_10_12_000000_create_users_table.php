<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('full_name', 100);
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('customer')->index();
            $table->string('status', 20)->default('active');
            $table->string('phone', 20)->nullable();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            foreach (['website', 'facebook', 'twitter', 'instagram', 'linkedin'] as $field) {
                $table->string($field, 100)->nullable();
            }
            $table->rememberToken();
            $table->timestamps();
            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
