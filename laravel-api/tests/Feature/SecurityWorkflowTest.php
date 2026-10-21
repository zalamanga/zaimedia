<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WriterBalance;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        Storage::fake('private');
        config(['zaimedia.checkout_demo_mode' => false, 'zaimedia.checkout_auto_paid' => false]);
    }

    public function test_registration_never_accepts_privileged_role_and_enforces_password_length(): void
    {
        $payload = ['username' => 'reader', 'email' => 'reader@example.test', 'full_name' => 'Reader', 'password' => 'short', 'role' => 'super_admin'];
        $this->postJson('/api/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/register', [...$payload, 'password' => 'test-password-123'])
            ->assertCreated()->assertJsonPath('user.role', 'customer')->assertJsonMissingPath('user.password');
        $this->getJson('/api/admin/users')->assertUnauthorized();
        Sanctum::actingAs(User::first());
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_password_change_revokes_existing_tokens(): void
    {
        $user = $this->user();
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/users/me/change-password', [
            'current_password' => 'password', 'new_password' => 'new-test-password',
        ])->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(password_verify('new-test-password', $user->fresh()->password));
    }

    public function test_upload_references_reject_traversal_and_other_writers_files(): void
    {
        $writer = $this->user('writer');
        $book = $this->book($writer);
        Sanctum::actingAs($writer);
        $name = $this->postJson('/api/uploads/pdf', ['pdf' => UploadedFile::fake()->create('sample.pdf', 1, 'application/pdf')])
            ->assertOk()->assertJsonMissingPath('url')->json('file');
        Storage::disk('private')->assertExists('books/pdf/'.$name);
        Storage::disk('uploads')->assertMissing('books/pdf/'.$name);
        $this->putJson('/api/writer/books/'.$book->id, ['pdf_file' => '../../../../.env'])
            ->assertUnprocessable()->assertJsonValidationErrors('pdf_file');
        $this->putJson('/api/writer/books/'.$book->id, ['pdf_file' => $name])->assertOk();
        $this->putJson('/api/writer/books/'.$book->id, ['pdf_file' => ''])->assertOk()->assertJsonPath('has_pdf', true);
        Sanctum::actingAs($other = $this->user('writer'));
        $this->putJson('/api/writer/books/'.$this->book($other)->id, ['pdf_file' => $name])
            ->assertUnprocessable()->assertJsonValidationErrors('pdf_file');
        $this->putJson('/api/writer/books/'.$book->id, ['title' => 'Hijack'])->assertForbidden();
        Storage::disk('private')->assertExists('books/pdf/'.$name);
    }

    public function test_replacement_only_removes_an_unreferenced_registered_upload(): void
    {
        $writer = $this->user('writer');
        $book = $this->book($writer);
        Sanctum::actingAs($writer);
        $old = $this->postJson('/api/uploads/pdf', ['pdf' => UploadedFile::fake()->create('old.pdf', 1, 'application/pdf')])->json('file');
        $new = $this->postJson('/api/uploads/pdf', ['pdf' => UploadedFile::fake()->create('new.pdf', 1, 'application/pdf')])->json('file');
        $this->putJson('/api/writer/books/'.$book->id, ['pdf_file' => $old])->assertOk();
        $otherBook = $this->book($writer, ['pdf_file' => $old]);
        $this->putJson('/api/writer/books/'.$book->id, ['pdf_file' => $new])->assertOk();
        Storage::disk('private')->assertExists('books/pdf/'.$old);
        $this->putJson('/api/writer/books/'.$otherBook->id, ['pdf_file' => $new])->assertOk();
        Storage::disk('private')->assertMissing('books/pdf/'.$old);
        Storage::disk('private')->assertExists('books/pdf/'.$new);
    }

    public function test_valid_png_and_gif_covers_are_stored_with_safe_extensions_and_original_bytes(): void
    {
        Sanctum::actingAs($this->user('writer'));
        $images = [
            'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNQaEj4DwADxAIAttaXgAAAAABJRU5ErkJggg==',
            'gif' => 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        ];
        foreach ($images as $extension => $base64) {
            $bytes = base64_decode($base64, true);
            $response = $this->postJson('/api/uploads/cover', [
                'cover' => UploadedFile::fake()->createWithContent('cover.'.$extension, $bytes),
            ])->assertOk();
            $name = $response->json('file');
            $this->assertMatchesRegularExpression('/^[a-f0-9-]+\\.'.$extension.'$/', $name);
            $this->assertStringEndsWith('/uploads/books/covers/'.$name, $response->json('url'));
            $this->assertSame($bytes, Storage::disk('uploads')->get('books/covers/'.$name));
            Storage::disk('private')->assertMissing('books/covers/'.$name);
        }
    }

    public function test_catalog_hides_pdf_and_download_requires_paid_ownership(): void
    {
        $writer = $this->user('writer');
        $book = $this->book($writer, ['pdf_file' => 'synthetic.pdf']);
        Storage::disk('private')->put('books/pdf/synthetic.pdf', '%PDF-1.4 synthetic test');
        $this->getJson('/api/books')->assertOk()->assertJsonMissingPath('data.0.pdf_file')->assertJsonMissingPath('data.0.pdf_url')->assertJsonPath('data.0.has_pdf', true);
        $this->getJson('/api/library/'.$book->id.'/download')->assertUnauthorized();
        Sanctum::actingAs($buyer = $this->user());
        $order = $this->order($buyer, $book);
        $this->getJson('/api/library/'.$book->id.'/download')->assertForbidden();
        $order->update(['payment_status' => 'paid']);
        $this->getJson('/api/library/'.$book->id)->assertOk()->assertJsonPath('download_url', '/api/library/'.$book->id.'/download');
        $this->get('/api/library/'.$book->id.'/download')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Sanctum::actingAs($this->user());
        $this->getJson('/api/library/'.$book->id.'/download')->assertForbidden();
        Sanctum::actingAs($writer);
        $this->get('/api/library/'.$book->id.'/download')->assertOk();
        Sanctum::actingAs($this->user('admin'));
        $this->get('/api/library/'.$book->id.'/download')->assertOk();
    }

    public function test_environment_gates_override_database_auto_paid_setting(): void
    {
        $book = $this->book($this->user('writer'));
        SystemSetting::create(['key' => 'checkout_auto_paid', 'value' => 'true', 'type' => 'boolean']);
        Sanctum::actingAs($this->user());
        $this->postJson('/api/orders', $this->payload($book))->assertCreated()->assertJsonPath('payment_status', 'pending');
        config(['zaimedia.checkout_demo_mode' => true, 'zaimedia.checkout_auto_paid' => true]);
        $this->postJson('/api/orders', $this->payload($book))->assertCreated()->assertJsonPath('payment_status', 'paid');
        config(['zaimedia.checkout_auto_paid' => false]);
        $this->postJson('/api/orders', $this->payload($book))->assertCreated()->assertJsonPath('payment_status', 'pending');
        $this->assertSame(1, Sale::count());
    }

    public function test_paid_and_refund_updates_are_atomic_and_idempotent(): void
    {
        $writer = $this->user('writer');
        $book = $this->book($writer);
        $order = $this->order($this->user(), $book);
        Sanctum::actingAs($this->user('admin'));
        $url = '/api/admin/orders/'.$order->id.'/status';
        $this->patchJson($url, ['payment_status' => 'paid'])->assertOk();
        $this->patchJson($url, ['payment_status' => 'paid'])->assertOk();
        $this->assertSame(1, Sale::count());
        $this->assertEquals(35000, WriterBalance::first()->available_balance);
        $this->patchJson($url, ['payment_status' => 'pending'])->assertUnprocessable();
        $this->patchJson($url, ['payment_status' => 'refunded'])->assertOk()->assertJsonPath('order_status', 'cancelled');
        $this->patchJson($url, ['payment_status' => 'refunded'])->assertOk();
        $this->assertEquals(0, WriterBalance::first()->available_balance);
        $this->assertSame(0, Sale::count());
        $this->patchJson($url, ['payment_status' => 'paid'])->assertUnprocessable();
    }

    public function test_refund_cannot_make_allocated_royalty_negative(): void
    {
        $writer = $this->user('writer');
        $order = $this->order($this->user(), $this->book($writer));
        Sanctum::actingAs($this->user('admin'));
        $url = '/api/admin/orders/'.$order->id.'/status';
        $this->patchJson($url, ['payment_status' => 'paid'])->assertOk();
        WriterBalance::first()->update(['available_balance' => 0, 'pending_balance' => 35000]);
        $this->patchJson($url, ['payment_status' => 'refunded'])->assertUnprocessable();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, Sale::count());
    }

    public function test_withdrawal_terminal_state_cannot_recredit_balance(): void
    {
        $writer = $this->user('writer');
        WriterBalance::create(['writer_id' => $writer->id, 'pending_balance' => 100000]);
        $withdrawal = Withdrawal::create([
            'writer_id' => $writer->id, 'withdrawal_number' => 'WD-TEST', 'amount' => 100000,
            'bank_name' => 'Synthetic', 'account_number' => '0000', 'account_name' => 'Demo', 'status' => 'pending',
        ]);
        Sanctum::actingAs($this->user('admin'));
        $url = '/api/admin/withdrawals/'.$withdrawal->id;
        $this->patchJson($url, ['status' => 'completed'])->assertOk();
        $this->patchJson($url, ['status' => 'completed'])->assertOk();
        $this->patchJson($url, ['status' => 'approved'])->assertUnprocessable();
        $this->patchJson($url, ['status' => 'rejected', 'rejection_reason' => 'No'])->assertUnprocessable();
        $this->assertEquals(0, WriterBalance::first()->pending_balance);
        $this->assertEquals(100000, WriterBalance::first()->total_withdrawn);
    }

    public function test_public_config_and_pagination_are_bounded(): void
    {
        SystemSetting::create(['key' => 'tax_rate', 'value' => '0.05', 'type' => 'number']);
        $this->getJson('/api/storefront-config')->assertExactJson(['tax_rate' => 0.05, 'checkout_demo_mode' => false]);
        $this->getJson('/api/books?per_page=999999')->assertOk()->assertJsonPath('per_page', 100);
        $this->getJson('/api/books?per_page=0')->assertOk()->assertJsonPath('per_page', 1);
        $this->getJson('/api/books?per_page[]=bad')->assertOk()->assertJsonPath('per_page', 12);
        Sanctum::actingAs($this->user('admin'));
        $this->getJson('/api/dashboard/summary')->assertOk()->assertJsonStructure(['monthly_revenue', 'top_books']);
    }

    public function test_demo_seed_is_optional_synthetic_and_requires_explicit_password(): void
    {
        $this->seed();
        $this->assertSame(0, User::count());
        config(['zaimedia.checkout_demo_mode' => true]);
        putenv('ZAIMEDIA_DEMO_PASSWORD=synthetic-test-only-password');
        try {
            $this->seed(DemoSeeder::class);
            $this->seed(DemoSeeder::class);
        } finally {
            putenv('ZAIMEDIA_DEMO_PASSWORD');
        }
        $this->assertSame(5, User::count());
        $this->assertSame(2, Book::count());
        $this->assertSame(2, Order::count());
        $this->assertSame(1, Sale::count());
        $this->getJson('/api/articles')->assertOk()->assertJsonMissingPath('data.0.author.email');
        Storage::disk('private')->assertExists('books/pdf/belajar-laravel-demo.pdf');
        Storage::disk('uploads')->assertMissing('books/pdf/belajar-laravel-demo.pdf');
    }

    public function test_admin_command_requires_explicit_credentials_and_never_changes_existing_account(): void
    {
        putenv('ZAIMEDIA_ADMIN_PASSWORD=synthetic-test-only-password');
        try {
            $this->artisan('zaimedia:create-admin', [
                '--username' => 'test_admin', '--email' => 'admin@example.test', '--name' => 'Synthetic Admin', '--no-interaction' => true,
            ])->assertSuccessful();
            $this->artisan('zaimedia:create-admin', [
                '--username' => 'test_admin', '--email' => 'admin@example.test', '--name' => 'Synthetic Admin', '--no-interaction' => true,
            ])->assertFailed();
        } finally {
            putenv('ZAIMEDIA_ADMIN_PASSWORD');
        }
        $this->assertSame(1, User::count());
        $this->assertSame('super_admin', User::first()->role);
    }

    private function user(string $role = 'customer'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function book(User $writer, array $attributes = []): Book
    {
        return Book::create($attributes + [
            'writer_id' => $writer->id, 'title' => 'Synthetic Book', 'author' => 'Demo',
            'price' => 50000, 'status' => 'approved',
        ]);
    }

    private function order(User $buyer, Book $book): Order
    {
        $order = Order::create([
            'customer_id' => $buyer->id, 'order_number' => 'TEST-'.uniqid(),
            'subtotal' => 50000, 'tax' => 5500, 'total' => 55500,
            'shipping_name' => 'Test', 'shipping_phone' => '000', 'shipping_address' => 'Synthetic',
            'shipping_city' => 'Test', 'shipping_postal_code' => '00000',
        ]);
        $order->items()->create(['book_id' => $book->id, 'quantity' => 1, 'price' => 50000, 'subtotal' => 50000]);

        return $order;
    }

    private function payload(Book $book): array
    {
        return [
            'items' => [['book_id' => $book->id, 'quantity' => 1]],
            'shipping_name' => 'Test', 'shipping_phone' => '000', 'shipping_address' => 'Synthetic',
            'shipping_city' => 'Test', 'shipping_postal_code' => '00000',
        ];
    }
}
