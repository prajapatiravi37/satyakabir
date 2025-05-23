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
        // Validate top-level dealer and products
        $request->validate([
            'dealer_id' => 'required|exists:dealers,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

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

}
