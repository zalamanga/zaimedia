<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function sales(Request $request): StreamedResponse
    {
        $rows = Sale::with(['book.writer:id,full_name,email', 'order:id,order_number'])
            ->latest('sale_date')
            ->get();

        return $this->csv('sales-report.csv', [
            'sale_id',
            'order_number',
            'book_title',
            'writer',
            'quantity',
            'book_price',
            'royalty_percentage',
            'royalty_amount',
            'sale_date',
        ], $rows->map(fn ($sale) => [
            $sale->id,
            $sale->order?->order_number,
            $sale->book?->title,
            $sale->book?->writer?->full_name,
            $sale->quantity,
            $sale->book_price,
            $sale->royalty_percentage,
            $sale->royalty_amount,
            optional($sale->sale_date)->toDateTimeString(),
        ]));
    }

    public function orders(Request $request): StreamedResponse
    {
        $rows = Order::with('customer:id,full_name,email')->latest()->get();

        return $this->csv('orders-report.csv', [
            'order_id',
            'order_number',
            'customer',
            'payment_status',
            'order_status',
            'subtotal',
            'tax',
            'total',
            'payment_method',
            'created_at',
        ], $rows->map(fn ($order) => [
            $order->id,
            $order->order_number,
            $order->customer?->full_name,
            $order->payment_status,
            $order->order_status,
            $order->subtotal,
            $order->tax,
            $order->total,
            $order->payment_method,
            optional($order->created_at)->toDateTimeString(),
        ]));
    }

    public function withdrawals(Request $request): StreamedResponse
    {
        $rows = Withdrawal::with('writer:id,full_name,email')->latest()->get();

        return $this->csv('withdrawals-report.csv', [
            'withdrawal_id',
            'withdrawal_number',
            'writer',
            'amount',
            'bank_name',
            'account_number',
            'account_name',
            'status',
            'processed_at',
            'created_at',
        ], $rows->map(fn ($withdrawal) => [
            $withdrawal->id,
            $withdrawal->withdrawal_number ?: $withdrawal->withdrawal_code,
            $withdrawal->writer?->full_name,
            $withdrawal->amount,
            $withdrawal->bank_name,
            $withdrawal->account_number,
            $withdrawal->account_name,
            $withdrawal->status,
            optional($withdrawal->processed_at)->toDateTimeString(),
            optional($withdrawal->created_at)->toDateTimeString(),
        ]));
    }

    public function auditLogs(Request $request): StreamedResponse
    {
        $rows = AdminAuditLog::with('actor:id,full_name,email,role')
            ->latest('created_at')
            ->get();

        return $this->csv('audit-logs-report.csv', [
            'log_id',
            'actor',
            'actor_email',
            'action',
            'target_type',
            'target_id',
            'metadata',
            'ip_address',
            'created_at',
        ], $rows->map(fn ($log) => [
            $log->id,
            $log->actor?->full_name,
            $log->actor?->email,
            $log->action,
            class_basename($log->target_type),
            $log->target_id,
            json_encode($log->metadata, JSON_UNESCAPED_UNICODE),
            $log->ip_address,
            optional($log->created_at)->toDateTimeString(),
        ]));
    }

    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(function ($value) {
                    $text = (string) ($value ?? '');

                    return preg_match('/^[=+@\t\r-]/', $text) ? "'".$text : $text;
                }, $row));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
