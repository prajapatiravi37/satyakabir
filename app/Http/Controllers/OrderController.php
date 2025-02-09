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
        // Validate request
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'dealer_id' => 'required|exists:dealers,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $product = Product::find($request->product_id);

        // Calculate redeem points (quantity * product points)
        $redeem_points = $request->quantity * $product->point;

        // Insert order
        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'dealer_id' => $request->dealer_id,
            'quantity' => $request->quantity,
            'redeem_points' => $redeem_points,
            'order_status' => 'Pending',
            'order_date' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Order placed successfully.',
            'order' => $order
        ]);
    }
}
