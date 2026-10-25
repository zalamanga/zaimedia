<?php

require __DIR__.'/../app/Support/CheckoutIntent.php';

use App\Support\CheckoutIntent;

$vectors = json_decode(file_get_contents(__DIR__.'/../../tests/checkout-intent-vectors.json'), true, 512, JSON_THROW_ON_ERROR);
$checks = 0;
foreach ($vectors as $vector) {
    $actual = CheckoutIntent::normalize($vector['input']);
    if ($actual !== $vector['expected'] || CheckoutIntent::hash($actual) !== $vector['sha256']) {
        throw new RuntimeException('Canonical intent mismatch: '.$vector['name']);
    }
    $checks += 2;
}
$base = $vectors[0]['input'];
foreach ([
    ['items' => []],
    ['items' => [['book_id' => '2']]],
    ['items' => [['book_id' => 9007199254740992]]],
    ['items' => [['book_id' => 2.5]]],
    ['items' => [['book_id' => INF]]],
    ['items' => [['book_id' => 2, 'quantity' => NAN]]],
    ['items' => [['book_id' => 2, 'quantity' => 1.5]]],
    ['items' => [['book_id' => 2, 'quantity' => 11]]],
    ['shipping_name' => "\xFF"],
    ['shipping_name' => str_repeat('😀', 101)],
    ['shipping_method' => '0'],
    ['payment_method' => '0'],
] as $change) {
    try {
        CheckoutIntent::normalize(array_replace($base, $change));
    } catch (InvalidArgumentException) {
        $checks++;
        continue;
    }
    throw new RuntimeException('Invalid intent was accepted');
}
echo "Checkout intent: {$checks} checks PASS\n";
