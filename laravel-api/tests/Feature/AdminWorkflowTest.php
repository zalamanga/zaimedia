<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\WriterRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $marker;

    private array $users = [];

    private array $books = [];

    private array $categories = [];

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->marker = 'test_'.str_replace('.', '', uniqid('', true));
        Storage::fake('uploads');
        Storage::fake('private');
        config(['zaimedia.checkout_demo_mode' => true, 'zaimedia.checkout_auto_paid' => true]);
    }

    public function test_book_archive_restore_audit_and_csv_export_flow(): void
    {
        [$admin, $writer, $category, $book] = $this->fixtureBook();

        Sanctum::actingAs($writer);
        $this->deleteJson("/api/writer/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Book archived');

        $this->getJson("/api/books/{$book->id}")
            ->assertNotFound();

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/books?status=archived&per_page=5&q='.urlencode($book->title))
            ->assertOk()
            ->assertJsonFragment(['id' => $book->id, 'title' => $book->title]);

        Sanctum::actingAs($writer);
        $this->patchJson("/api/writer/books/{$book->id}/restore")
            ->assertOk()
            ->assertJsonPath('id', $book->id);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/audit-logs?action=book.restore')
            ->assertOk()
            ->assertJsonFragment(['action' => 'book.restore']);

        $csv = $this->get('/api/admin/exports/audit-logs')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('book.restore', $csv);
        $this->assertStringContainsString((string) $book->id, $csv);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'deleted_at' => null,
        ]);
    }

    public function test_customer_can_be_approved_as_writer_and_cannot_buy_own_book(): void
    {
        [$admin, $writer, $category, $otherBook] = $this->fixtureBook([
            'title' => $this->marker.' Other Writer Book',
        ]);
        $customer = $this->user('customer');

        Sanctum::actingAs($customer);
        $this->postJson('/api/writer-request', [
            'pen_name' => 'Codex Pen',
            'bio' => 'Saya ingin menulis buku edukasi digital untuk pembaca Zaimedia.',
            'reason' => 'Saya punya beberapa naskah dan ingin menerbitkannya lewat marketplace ini.',
            'portfolio_url' => 'https://example.test/portfolio',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        Sanctum::actingAs($admin);
        $requestId = WriterRequest::where('user_id', $customer->id)->value('id');
        $this->patchJson("/api/admin/writer-requests/{$requestId}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $customer->refresh();
        $this->assertSame('writer', $customer->role);

        $ownBook = Book::create([
            'writer_id' => $customer->id,
            'title' => $this->marker.' Own Writer Book',
            'author' => 'Codex Test',
            'publisher' => 'Zaimedia Test',
            'year' => 2026,
            'pages' => 80,
            'price' => 40000,
            'isbn' => substr($this->marker.'own', 0, 20),
            'category_id' => $category->id,
            'short_description' => 'Own book.',
            'long_description' => 'Own book.',
            'status' => 'approved',
            'views' => 0,
        ]);
        $this->books[] = $ownBook->id;

        Sanctum::actingAs($customer);
        $orderNumber = $this->postJson('/api/orders', $this->orderPayload($otherBook->id))
            ->assertCreated()
            ->assertJsonPath('payment_status', 'paid')
            ->json('order_number');

        $this->getJson("/api/orders/by-number/{$orderNumber}")
            ->assertOk()
            ->assertJsonPath('order_number', $orderNumber);

        $this->postJson('/api/orders', $this->orderPayload($ownBook->id))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Writer tidak bisa membeli buku sendiri.');
    }

    public function test_user_profile_update_persists_social_and_profile_fields(): void
    {
        $customer = $this->user('customer');

        Sanctum::actingAs($customer);
        $this->patchJson('/api/users/me', [
            'full_name' => 'Codex Profile',
            'phone' => '08123456789',
            'bio' => 'Bio yang tersimpan dari dashboard.',
            'address' => 'Jl. Dashboard',
            'city' => 'Bogor',
            'postal_code' => '16820',
            'date_of_birth' => '1995-05-20',
            'website' => 'https://example.test',
            'facebook' => 'codex.fb',
            'twitter' => 'codex_tw',
            'instagram' => 'codex_ig',
            'linkedin' => 'codex_in',
        ])
            ->assertOk()
            ->assertJsonPath('website', 'https://example.test')
            ->assertJsonPath('instagram', 'codex_ig')
            ->assertJsonPath('linkedin', 'codex_in');

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'full_name' => 'Codex Profile',
            'website' => 'https://example.test',
            'facebook' => 'codex.fb',
            'twitter' => 'codex_tw',
            'instagram' => 'codex_ig',
            'linkedin' => 'codex_in',
        ]);
        $this->assertSame('1995-05-20', $customer->fresh()->date_of_birth->format('Y-m-d'));
    }

    private function fixtureBook(array $bookOverrides = []): array
    {
        $admin = $this->user('admin');
        $writer = $this->user('writer');
        $category = Category::create([
            'name' => $this->marker,
            'slug' => $this->marker,
            'description' => 'Temporary category for feature tests.',
            'created_at' => now(),
        ]);

        $this->categories[] = $category->id;

        $book = Book::create(array_merge([
            'writer_id' => $writer->id,
            'title' => $this->marker.' Book',
            'author' => 'Codex Test',
            'publisher' => 'Zaimedia Test',
            'year' => 2026,
            'pages' => 120,
            'price' => 50000,
            'isbn' => substr($this->marker, 0, 20),
            'category_id' => $category->id,
            'cover_image' => null,
            'pdf_file' => null,
            'short_description' => 'Temporary book for feature tests.',
            'long_description' => 'Temporary book for feature tests.',
            'status' => 'approved',
            'views' => 0,
        ], $bookOverrides));

        $this->books[] = $book->id;

        return [$admin, $writer, $category, $book];
    }

    private function user(string $role): User
    {
        $user = User::create([
            'username' => "{$this->marker}_{$role}",
            'email' => "{$this->marker}_{$role}@example.test",
            'password' => Hash::make('secret123'),
            'full_name' => "Codex {$role}",
            'phone' => '0800000000',
            'role' => $role,
            'status' => 'active',
        ]);

        $this->users[] = $user->id;

        return $user;
    }

    private function orderPayload(int $bookId): array
    {
        return [
            'operation_key' => (string) Str::uuid(),
            'items' => [
                ['book_id' => $bookId, 'quantity' => 1],
            ],
            'shipping_name' => 'Codex Buyer',
            'shipping_phone' => '0800000000',
            'shipping_address' => 'Jl. Test',
            'shipping_city' => 'Bogor',
            'shipping_postal_code' => '16820',
            'shipping_method' => 'regular',
            'payment_method' => 'bank_transfer',
        ];
    }
}
