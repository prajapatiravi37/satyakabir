<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class OrderController extends Controller
{
    public function placeOrder(Request $request)
    {
        $user = Auth::user();
        $orders = [];

        $hasPreviousOrders = Order::where('user_id', $user->id)->exists();

         $eligibleForBonus = false;

        foreach ($request->products as $productData) {
            $product = Product::find($productData['product_id']);
            $redeem_points = $productData['quantity'] * $product->point;

            if (!$hasPreviousOrders && $productData['quantity'] >= 50) {
                $eligibleForBonus = true;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'dealer_id' => $request->dealer_id,
                'quantity' => $productData['quantity'],
                'redeem_points' => $redeem_points,
                'order_status' => 'Pending',
                'order_date' => now(),
            ]);

            // Load dealer only (product name is from $product directly)
            $order->load('dealer');

            $orders[] = [
                'id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name ?? 'N/A',
                'dealer_id' => $order->dealer_id,
                'dealer_name' => $order->dealer->name ?? 'N/A',
                'quantity' => $order->quantity,
                'redeem_points' => $order->redeem_points,
                'order_status' => $order->order_status,
                'order_date' => $order->order_date->format('Y-m-d H:i:s'),
            ];
        }
        // Add bonus points if eligible
        if (!$hasPreviousOrders && $eligibleForBonus) {
            DB::table('user_bonus_points')->insert([
                'user_id' => $user->id,
                'redeem_points' => 2100,
                'created_at' => now(),
                'updated_at' => now()
            ]);
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
            $orderData = [
                'id' => $order->id,
                'orderPlacedDate' => \Carbon\Carbon::parse($order->order_date)->format('Y-m-d'),
                'materialName' => $order->product->type ?? 'N/A',  // Assuming `type` is the material name
                'productName' => $order->product->name ?? 'N/A',
                'productCode' => $order->product->code ?? 'N/A',
                'dealerName' => $order->dealer->name ?? 'N/A',
                'quantity' => $order->quantity,
                'status' => $order->order_status,
            ];

            // Add cancellation details if order is cancelled
            if ($order->order_status === 'Cancelled') {
                $orderData['cancellationReason'] = $order->cancellation_reason ?? null;
                $orderData['cancelledDate'] = $order->updated_at ? $order->updated_at->format('Y-m-d H:i:s') : null;
            }

            return $orderData;
        });

        return response()->json([
            'status' => 200,
            'orders' => $data
        ]);
    }

    public function getPointSummary(Request $request)
{
    $user = Auth::user();
    $summary = [];

    // 1. Bonus Points (from user_bonus_points)
    $bonusPoints = DB::table('user_bonus_points')
        ->where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

    foreach ($bonusPoints as $bonus) {
        $points = (int)$bonus->redeem_points; // Convert string to int
        $isNegative = $points < 0;
        
        $summary[] = [
            'id' => 'bonus-' . $bonus->id, // unique ID
            'product_name' => null,
            'points' => $points, // Can be negative for cancelled orders
            'point_status' => $bonus->redeem_point_status,
            'type' => $isNegative ? 'redeem' : 'gain',
            'requestedDate' => date('Y-m-d', strtotime($bonus->created_at)),
            'redemptionAmount' => abs($points), // Always show positive amount
            'description' => $isNegative ? 'Order Cancellation - Points Deducted' : 'Bonus point',
            'status' => 'completed',
        ];
    }

    // 2. Orders (1 record per order-product)
    $orders = Order::with('product')
        ->where('user_id', $user->id)
        ->get();

    foreach ($orders as $order) {
        // For cancelled orders, show negative points
        $points = $order->order_status === 'Cancelled' ? -$order->redeem_points : $order->redeem_points;
        $type = $order->order_status === 'Cancelled' ? 'redeem' : ($order->redeem_point_status == 1 ? 'redeem' : 'gain');
        
        $summary[] = [
            'id' => $order->id,
            'product_name' => $order->product->name ?? null,
            'points' => $points,
            'type' => $type,
            'requestedDate' => $order->order_date,
            'adminConfirm' => $order->admin_confirm,
            'redeemPointStatus' => $order->redeem_point_status,
            'redemptionAmount' => abs($order->redeem_points), // Always show positive amount
            'description' => $order->order_status === 'Cancelled' 
                ? 'Order ID #' . $order->id . ' - ' . ($order->product->name ?? 'Product') . ' (Cancelled)'
                : 'Order ID #' . $order->id . ' - ' . ($order->product->name ?? 'Product'),
            'status' => $order->order_status === 'Cancelled' ? 'cancelled' : 'completed',
            'orderStatus' => $order->order_status
        ];
    }

    return response()->json($summary);
}

    // Cancel Order API for Architects
    public function cancelOrder(Request $request, $orderId)
    {
        $user = Auth::user();
        
        // Validate the request
        $request->validate([
            'cancellation_reason' => 'required|string|max:500'
        ]);

        // Find the order
        $order = Order::find($orderId);
        
        if (!$order) {
            return response()->json([
                'status' => 404,
                'message' => 'Order not found.'
            ], 404);
        }

        // Check if order can be cancelled (not already cancelled or completed)
        if ($order->order_status === 'Cancelled') {
            return response()->json([
                'status' => 400,
                'message' => 'Order is already cancelled.'
            ], 400);
        }

        if ($order->order_status === 'Delivered') {
            return response()->json([
                'status' => 400,
                'message' => 'Cannot cancel a delivered order.'
            ], 400);
        }

        // Check if this order was part of the first order and had quantity >= 50
        $isFirstOrder = false;
        $wasEligibleForBonus = false;
        
        // Get the user's first order date (including cancelled orders to check if this was first batch)
        $firstOrder = Order::where('user_id', $order->user_id)
            ->orderBy('order_date', 'asc')
            ->orderBy('id', 'asc')
            ->first();
        
        // Check if this order is from the first order batch (same order_date as first order)
        if ($firstOrder && $firstOrder->order_date->format('Y-m-d H:i:s') === $order->order_date->format('Y-m-d H:i:s')) {
            $isFirstOrder = true;
            // Check if this order had quantity >= 50
            if ($order->quantity >= 50) {
                $wasEligibleForBonus = true;
            }
        }

        // If cancelling an order that made user eligible for bonus, check remaining orders
        if ($isFirstOrder && $wasEligibleForBonus) {
            // Get all orders from the same order_date (excluding the one being cancelled)
            $remainingOrders = Order::where('user_id', $order->user_id)
                ->where('order_date', $order->order_date)
                ->where('id', '!=', $order->id)
                ->where('order_status', '!=', 'Cancelled')
                ->get();
            
            // Check if any remaining order has quantity >= 50
            $hasEligibleOrder = $remainingOrders->contains(function ($remainingOrder) {
                return $remainingOrder->quantity >= 50;
            });
            
            // If no remaining order has quantity >= 50, remove bonus points
            if (!$hasEligibleOrder) {
                // Check if bonus points exist and remove them
                $bonusPoints = DB::table('user_bonus_points')
                    ->where('user_id', $order->user_id)
                    ->where('redeem_points', 2100)
                    ->where('redeem_point_status', '0')
                    ->first();
                
                if ($bonusPoints) {
                    // Add negative entry to remove bonus points
                    DB::table('user_bonus_points')->insert([
                        'user_id' => $order->user_id,
                        'redeem_points' => -2100, // Negative value to remove bonus
                        'redeem_point_status' => '0', // 0 = Not redeem
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        // For any cancelled order, set redeem_points to 0 in the order table
        $order->redeem_points = 0;
        $order->order_status = 'Cancelled';
        $order->admin_confirm = '0'; // Reset admin confirmation
        $order->cancellation_reason = $request->cancellation_reason;
        $order->save();

        // Load related data for response
        $order->load(['product', 'dealer', 'user']);

        return response()->json([
            'status' => 200,
            'message' => 'Order cancelled successfully.',
            'order' => [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'user_name' => $order->user->name ?? 'N/A',
                'product_id' => $order->product_id,
                'product_name' => $order->product->name ?? 'N/A',
                'dealer_id' => $order->dealer_id,
                'dealer_name' => $order->dealer->name ?? 'N/A',
                'quantity' => $order->quantity,
                'redeem_points' => $order->redeem_points,
                'order_status' => $order->order_status,
                'admin_confirm' => $order->admin_confirm,
                'order_date' => $order->order_date->format('Y-m-d H:i:s'),
                'cancelled_at' => now()->format('Y-m-d H:i:s'),
                'cancelled_by' => $user->name,
                'cancellation_reason' => $order->cancellation_reason
            ]
        ]);
    }

    // Get all orders for architects (admin view)
    public function getAllOrdersForArchitect()
    {
        $user = Auth::user();
        
        // Check if user is an architect (admin)
        if ($user->userRole !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Access denied. Only architects can view all orders.'
            ], 403);
        }

        $orders = Order::with(['product', 'dealer', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'user_name' => $order->user->name ?? 'N/A',
                'user_email' => $order->user->email ?? 'N/A',
                'user_mobile' => $order->user->mobile_no ?? 'N/A',
                'product_id' => $order->product_id,
                'product_name' => $order->product->name ?? 'N/A',
                'product_type' => $order->product->type ?? 'N/A',
                'dealer_id' => $order->dealer_id,
                'dealer_name' => $order->dealer->name ?? 'N/A',
                'quantity' => $order->quantity,
                'redeem_points' => $order->redeem_points,
                'order_status' => $order->order_status,
                'admin_confirm' => $order->admin_confirm,
                'redeem_point_status' => $order->redeem_point_status,
                'order_date' => $order->order_date->format('Y-m-d H:i:s'),
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $order->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status' => 200,
            'message' => 'All orders fetched successfully.',
            'orders' => $data,
            'total_orders' => $data->count()
        ]);
    }

}
