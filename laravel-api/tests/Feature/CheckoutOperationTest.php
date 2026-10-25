<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CheckoutOperation;
use App\Models\Sale;
use App\Models\User;
use App\Models\WriterBalance;
use App\Support\CheckoutIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CheckoutOperationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['zaimedia.checkout_demo_mode' => false, 'zaimedia.checkout_auto_paid' => false]);
    }

    public function test_replay_returns_original_snapshot_even_after_catalog_changes(): void
    {
        [$buyer, $book] = $this->fixture();
        Sanctum::actingAs($buyer);
        $payload = $this->payload($book);
        $first = $this->postJson('/api/orders', $payload)->assertCreated()->assertHeader('Checkout-Replayed', 'false')->json();
        $book->update(['price' => 99999, 'title' => 'Changed catalog']);
        $book->delete();
        $this->postJson('/api/orders', $payload)->assertCreated()->assertHeader('Checkout-Replayed', 'true')->assertExactJson($first);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('checkout_operations', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame('pending', $first['payment_status']);
    }

    public function test_equivalent_rows_and_unicode_edges_replay_but_changed_intent_conflicts(): void
    {
        [$buyer, $book] = $this->fixture();
        Sanctum::actingAs($buyer);
        $payload = $this->payload($book);
        $payload['items'] = [['book_id' => $book->id, 'quantity' => 8], ['book_id' => $book->id, 'quantity' => 7]];
        $payload['shipping_name'] = "\u{200B}Synthetic\u{FEFF}";
        $first = $this->postJson('/api/orders', $payload)->assertCreated()->json();
        $payload['items'] = [['book_id' => $book->id, 'quantity' => 10]];
        $payload['shipping_name'] = 'Synthetic';
        $this->postJson('/api/orders', $payload)->assertCreated()->assertExactJson($first);
        $rawExponent = str_replace('"book_id":'.$book->id, '"book_id":'.$book->id.'e0', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->call('POST', '/api/orders', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $rawExponent)
            ->assertCreated()->assertExactJson($first);
        foreach (['shipping_address', 'payment_method', 'notes'] as $field) {
            $changed = array_replace($payload, [$field => $field === 'payment_method' ? 'cod' : 'Different']);
            $this->postJson('/api/orders', $changed)->assertConflict();
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_demo_paid_replay_does_not_repeat_ledger_or_notifications(): void
    {
        config(['zaimedia.checkout_demo_mode' => true, 'zaimedia.checkout_auto_paid' => true]);
        [$buyer, $book] = $this->fixture();
        Sanctum::actingAs($buyer);
        $payload = $this->payload($book);
        $first = $this->postJson('/api/orders', $payload)->assertCreated()->assertJsonPath('payment_status', 'paid')->json();
        $balance = WriterBalance::firstOrFail();
        $this->assertSame('35000.00', $balance->available_balance);
        $this->postJson('/api/orders', $payload)->assertCreated()->assertExactJson($first);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame($balance->only(['available_balance', 'pending_balance', 'total_withdrawn']), $balance->fresh()->only(['available_balance', 'pending_balance', 'total_withdrawn']));
        $this->assertSame(1, Sale::where('order_id', $first['id'])->count());
    }

    public function test_receipt_scope_never_leaks_another_account_and_missing_stays_unknown(): void
    {
        [$buyer, $book] = $this->fixture();
        $other = User::factory()->create();
        Sanctum::actingAs($buyer);
        $payload = $this->payload($book);
        $order = $this->postJson('/api/orders', $payload)->assertCreated()->json();
        $this->getJson('/api/checkout-operations/'.$payload['operation_key'])
            ->assertOk()->assertJsonPath('order.id', $order['id'])
            ->assertJsonPath('intent_hash', CheckoutIntent::hash(CheckoutIntent::normalize($payload)));
        Sanctum::actingAs($other);
        $this->getJson('/api/checkout-operations/'.$payload['operation_key'])
            ->assertNotFound()->assertJsonPath('outcome_unknown', true)->assertJsonMissingPath('order');
        $this->postJson('/api/orders', $payload)->assertCreated();
        $this->assertDatabaseCount('checkout_operations', 2);
        $this->assertDatabaseCount('orders', 2);
        $this->getJson('/api/checkout-operations/not-a-uuid')->assertUnprocessable();
    }

    public function test_rejected_first_request_leaves_no_receipt_and_can_be_corrected_with_same_key(): void
    {
        [$buyer, $book] = $this->fixture();
        Sanctum::actingAs($buyer);
        $payload = $this->payload($book);
        $invalid = array_replace($payload, ['items' => [['book_id' => $book->id, 'quantity' => 11]]]);
        $this->postJson('/api/orders', $invalid)->assertUnprocessable();
        $this->postJson('/api/orders', array_replace($payload, ['items' => [['book_id' => $book->id, 'quantity' => '']]]))->assertUnprocessable();
        $rawObject = json_encode(array_replace($payload, ['items' => (object) ['0' => ['book_id' => $book->id, 'quantity' => 1]]]), JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/orders', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $rawObject)->assertUnprocessable();
        $this->assertDatabaseCount('checkout_operations', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->postJson('/api/orders', $payload)->assertCreated();
        $this->postJson('/api/orders', array_diff_key($payload, ['operation_key' => true]))->assertUnprocessable();
    }

    public function test_failure_before_receipt_rolls_back_order_items_and_notification(): void
    {
        [$buyer, $book] = $this->fixture();
        Sanctum::actingAs($buyer);
        $payload = $this->payload($book);
        $event = 'eloquent.creating: '.CheckoutOperation::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic receipt failure'));
        $this->withoutExceptionHandling();
        try {
            try {
                $this->postJson('/api/orders', $payload);
                $this->fail('Expected controlled receipt failure');
            } catch (RuntimeException $error) {
                $this->assertSame('Synthetic receipt failure', $error->getMessage());
            }
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('checkout_operations', 0);
        $this->postJson('/api/orders', $payload)->assertCreated();
    }

    public function test_receipt_pair_is_enforced_by_database_unique_constraint(): void
    {
        [$buyer, $book] = $this->fixture();
        Sanctum::actingAs($buyer);
        $this->postJson('/api/orders', $this->payload($book))->assertCreated();
        $receipt = CheckoutOperation::firstOrFail();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('checkout_operations')->insert($receipt->only(['customer_id', 'operation_key', 'intent_hash', 'order_id']) + ['response_payload' => '{}']);
    }

    private function fixture(): array
    {
        $buyer = User::factory()->create();
        $writer = User::factory()->create(['role' => 'writer']);
        $book = Book::create(['writer_id' => $writer->id, 'title' => 'Synthetic checkout book', 'author' => 'Fixture', 'price' => 50000, 'status' => 'approved']);

        return [$buyer, $book];
    }

    private function payload(Book $book): array
    {
        return [
            'operation_key' => (string) Str::uuid(),
            'items' => [['book_id' => $book->id, 'quantity' => 1]],
            'shipping_name' => 'Synthetic', 'shipping_phone' => '000', 'shipping_address' => 'Synthetic address',
            'shipping_city' => 'Test', 'shipping_postal_code' => '00000',
        ];
    }
}
