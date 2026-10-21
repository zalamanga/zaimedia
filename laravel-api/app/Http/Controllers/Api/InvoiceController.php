<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function show(Request $request, Order $order)
    {
        abort_if(! $request->user()->isAdmin() && $order->customer_id !== $request->user()->id, 403);

        $order->load(['customer:id,full_name,email', 'items.book:id,title,author,slug']);

        return response()->json([
            'invoice_number' => 'INV-'.$order->order_number,
            'order' => $order,
            'issued_at' => optional($order->created_at)->toDateTimeString(),
            'seller' => [
                'name' => 'Zaimedia',
                'email' => 'support@zaimedia.local',
            ],
        ]);
    }
}
