<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\WriterBalance;

class OrderLedger
{
    // Caller holds the order row lock; writer rows are always locked in ID order.
    public static function pay(Order $order): void
    {
        if (Sale::where('order_id', $order->id)->exists()) {
            return;
        }
        $items = $order->items()->with('book')->get()->sortBy(fn ($item) => $item->book->writer_id);
        foreach ($items as $item) {
            $percentage = (float) SystemSetting::value('royalty_percentage', 70);
            $royalty = round((float) $item->price * $item->quantity * $percentage / 100, 2);
            WriterBalance::firstOrCreate(['writer_id' => $item->book->writer_id]);
            $balance = WriterBalance::where('writer_id', $item->book->writer_id)->lockForUpdate()->firstOrFail();
            Sale::create([
                'book_id' => $item->book_id, 'writer_id' => $item->book->writer_id,
                'order_id' => $order->id, 'quantity' => $item->quantity,
                'book_price' => $item->price, 'royalty_percentage' => $percentage,
                'royalty_amount' => $royalty,
            ]);
            $balance->increment('available_balance', $royalty);
            AppNotification::send($item->book->writer_id, 'sale.created', 'Buku terjual', "{$item->book->title} terjual {$item->quantity} item.", '/dashboard?tab=royalty');
        }
    }

    public static function refund(Order $order): void
    {
        $sales = Sale::where('order_id', $order->id)->get();
        foreach ($sales->groupBy('writer_id')->sortKeys() as $writerId => $rows) {
            $balance = WriterBalance::where('writer_id', $writerId)->lockForUpdate()->firstOrFail();
            $royalty = round((float) $rows->sum('royalty_amount'), 2);
            abort_if((float) $balance->available_balance < $royalty, 422, 'Royalti sudah dialokasikan untuk penarikan; refund belum dapat dicatat.');
            $balance->decrement('available_balance', $royalty);
        }
        Sale::where('order_id', $order->id)->delete();
    }
}
