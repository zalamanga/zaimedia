<?php

namespace App\Support;

use InvalidArgumentException;

final class CheckoutIntent
{
    public const MAX_ID = 9007199254740991;

    private const EDGE_SPACE = '[\x{0000}-\x{0020}\x{0085}\x{00A0}\x{1680}\x{2000}-\x{200B}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

    public static function normalize(array $input): array
    {
        $rows = $input['items'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) < 1 || count($rows) > 100) {
            throw new InvalidArgumentException('Cart harus berisi 1 sampai 100 baris buku.');
        }
        $items = [];
        foreach ($rows as $row) {
            $id = self::integer(is_array($row) ? ($row['book_id'] ?? null) : null, self::MAX_ID);
            $quantity = self::integer(is_array($row) ? ($row['quantity'] ?? 1) : null, 10);
            if ($id === null || $quantity === null) {
                throw new InvalidArgumentException('ID buku dan jumlah harus berupa bilangan bulat yang valid.');
            }
            $items[$id] = min(10, ($items[$id] ?? 0) + $quantity);
        }
        ksort($items, SORT_NUMERIC);
        $intent = ['items' => []];
        foreach ($items as $id => $quantity) {
            $intent['items'][] = ['book_id' => $id, 'quantity' => $quantity];
        }
        foreach (['shipping_name' => 100, 'shipping_phone' => 20, 'shipping_address' => 2000, 'shipping_city' => 50, 'shipping_postal_code' => 10] as $field => $max) {
            $intent[$field] = self::text($input[$field] ?? null, $max, true);
        }
        $shippingMethod = self::text($input['shipping_method'] ?? null, 20);
        $paymentMethod = self::text($input['payment_method'] ?? null, 20);
        $intent['shipping_method'] = $shippingMethod === '' ? 'regular' : $shippingMethod;
        $intent['payment_method'] = $paymentMethod === '' ? 'bank_transfer' : $paymentMethod;
        if (! in_array($intent['shipping_method'], ['regular', 'express', 'same_day'], true)
            || ! in_array($intent['payment_method'], ['bank_transfer', 'credit_card', 'e_wallet', 'cod'], true)) {
            throw new InvalidArgumentException('Metode pengiriman atau pembayaran tidak valid.');
        }
        $notes = self::text($input['notes'] ?? null, 2000);
        $intent['notes'] = $notes === '' ? null : $notes;

        return $intent;
    }

    private static function integer(mixed $value, int $max): ?int
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)
            || $value < 1 || $value > $max || floor((float) $value) !== (float) $value) {
            return null;
        }

        return (int) $value;
    }

    private static function text(mixed $value, int $max, bool $required = false): string
    {
        if ($value === null && ! $required) {
            return '';
        }
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('Data pengiriman harus berupa teks Unicode yang valid.');
        }
        $value = preg_replace('/^'.self::EDGE_SPACE.'+|'.self::EDGE_SPACE.'+$/u', '', $value);
        $length = preg_match_all('/./us', $value);
        if (($required && $value === '') || $length > $max) {
            throw new InvalidArgumentException('Data pengiriman kosong atau melebihi batas panjang.');
        }

        return $value;
    }

    public static function json(array $intent): string
    {
        return json_encode($intent, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }

    public static function hash(array $intent): string
    {
        return hash('sha256', self::json($intent));
    }
}
