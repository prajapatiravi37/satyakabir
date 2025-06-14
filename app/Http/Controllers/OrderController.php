<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function placeOrder(Request $request)
    {
    

        $user = Auth::user();
        $orders = [];

        foreach ($request->products as $productData) {
            $product = Product::find($productData['product_id']);
            $redeem_points = $productData['quantity'] * $product->point;

            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'dealer_id' => $request->dealer_id,
                'quantity' => $productData['quantity'],
                'redeem_points' => $redeem_points,
                'order_status' => 'Pending',
                'order_date' => now(),
            ]);

            $orders[] = $order;
        }

        return response()->json([
            'status' => 200,
            'message' => 'Orders placed successfully.',
            'orders' => $orders
        ]);
    }
    public function orderHistory()
    {
        $user = Auth::user();

        $orders = Order::with(['product', 'dealer'])
            ->where('user_id', $user->id)
            ->get();

        $data = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'orderPlacedDate' => \Carbon\Carbon::parse($order->order_date)->format('Y-m-d'),
                'materialName' => $order->product->type ?? 'N/A',  // Assuming `type` is the material name
                'productName' => $order->product->name ?? 'N/A',
                'productCode' => $order->product->code ?? 'N/A',
                'dealerName' => $order->dealer->name ?? 'N/A',
                'quantity' => $order->quantity,
                'status' => $order->order_status,
            ];
        });

        return response()->json([
            'status' => 200,
            'orders' => $data
        ]);
    }







}
