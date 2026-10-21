<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->nullable()->unique();
            $table->string('author', 100);
            $table->string('publisher', 100)->default('');
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('pages')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->string('isbn', 20)->nullable();
            $table->string('cover_image')->nullable();
            $table->string('pdf_file')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('long_description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'deleted_at']);
            $table->index(['category_id', 'status']);
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('order_number', 60)->unique();
            foreach (['subtotal', 'shipping_cost', 'tax', 'total'] as $field) {
                $table->decimal($field, 14, 2)->default(0);
            }
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('payment_status', 20)->default('pending');
            $table->string('order_status', 20)->default('pending');
            $table->string('shipping_name', 100);
            $table->string('shipping_phone', 20);
            $table->text('shipping_address');
            $table->string('shipping_city', 50);
            $table->string('shipping_postal_code', 10);
            $table->string('shipping_method', 20)->default('regular');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'payment_status']);
            $table->index('order_status');
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 12, 2);
            $table->decimal('subtotal', 14, 2);
            $table->unique(['order_id', 'book_id']);
        });
        Schema::create('writer_balance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id')->unique()->constrained('users')->restrictOnDelete();
            foreach (['available_balance', 'pending_balance', 'total_withdrawn'] as $field) {
                $table->decimal($field, 14, 2)->default(0);
            }
            $table->timestamp('updated_at')->useCurrent();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('writer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('book_price', 12, 2);
            $table->decimal('royalty_percentage', 5, 2);
            $table->decimal('royalty_amount', 14, 2);
            $table->timestamp('sale_date')->useCurrent();
            $table->unique(['order_id', 'book_id']);
            $table->index(['writer_id', 'sale_date']);
        });
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id')->constrained('users')->restrictOnDelete();
            $table->string('withdrawal_number', 60)->unique();
            $table->string('withdrawal_code', 60)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('bank_name', 50);
            $table->string('account_number', 50);
            $table->string('account_name', 100);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['writer_id', 'status']);
        });
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 100)->nullable();
            $table->text('content');
            $table->boolean('is_verified')->default(false);
            $table->string('status', 20)->default('visible');
            $table->text('moderation_reason')->nullable();
            $table->timestamps();
            $table->unique(['book_id', 'customer_id']);
            $table->index(['book_id', 'status']);
        });
        Schema::create('writer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pen_name', 100);
            $table->text('bio');
            $table->text('reason');
            $table->string('portfolio_url')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('excerpt', 300)->nullable();
            $table->longText('content');
            $table->string('cover_image')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title', 160);
            $table->text('message');
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'book_id']);
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->timestamps();
        });
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('target_type', 100);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
            $table->index(['action', 'created_at']);
        });
        Schema::create('uploaded_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['uploaded_files', 'admin_audit_logs', 'system_settings', 'wishlists', 'notifications', 'articles', 'writer_requests', 'reviews', 'withdrawals', 'sales', 'writer_balance', 'order_items', 'orders', 'books', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
