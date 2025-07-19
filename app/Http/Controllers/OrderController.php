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

    public function getPointSummary(Request $request)
{
    $user = Auth::user();
    $summary = [];

    // 1. Bonus Points (from user_bonus_points)
    $bonusPoints = DB::table('user_bonus_points')
        ->where('user_id', $user->id)
        ->get();

    foreach ($bonusPoints as $bonus) {
        $summary[] = [
            'id' => 'bonus-' . $bonus->id, // unique ID
            'product_name' => null,
            'points' => $bonus->redeem_points,
            'point_status' => $bonus->redeem_point_status,
            'type' => 'gain',
            'requestedDate' => date('Y-m-d', strtotime($bonus->created_at)),
            'redemptionAmount' => $bonus->redeem_points,
            'description' => 'Bonus point',
            'status' => 'completed',
        ];
    }

    // 2. Orders (1 record per order-product)
    $orders = Order::with('product')
        ->where('user_id', $user->id)
        ->get();

    foreach ($orders as $order) {
        $summary[] = [
            'id' => $order->id,
            'product_name' => $order->product->name ?? null,
            'points' => $order->redeem_points,
            'type' => $order->redeem_point_status == 1 ? 'redeem' : 'gain',
            'requestedDate' => $order->order_date,
            'adminConfirm' => $order->admin_confirm,
            'redeemPointStatus' => $order->redeem_point_status,
            'redemptionAmount' => $order->redeem_points,
            'description' => 'Order ID #' . $order->id . ' - ' . ($order->product->name ?? 'Product'),
            'status' => 'completed'
        ];
    }

    return response()->json($summary);
}







}
