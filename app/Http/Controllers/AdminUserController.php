<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\User;
use App\Models\Dealer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AdminUserController extends Controller
{
    /**
     * Get admin dashboard totals
     * Returns total orders, pending orders, architects, and dealers in a single API
     */
    public function getDashboardTotals()
    {
        // Check if user is admin
        $user = Auth::user();
        if ($user->userRole !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            // Get all totals in one go
            $totalOrders = Order::count();
            $totalPendingOrders = Order::where('order_status', 'Pending')->count();
            $totalArchitects = User::where('userRole', 'normal')->count();
            $totalDealers = Dealer::count();

            return response()->json([
                'status' => 200,
                'message' => 'Dashboard totals retrieved successfully',
                'data' => [
                    'totalOrders' => $totalOrders,
                    'totalPendingOrders' => $totalPendingOrders,
                    'totalArchitects' => $totalArchitects,
                    'totalDealers' => $totalDealers
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving dashboard totals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all orders for admin with user name, product name with code, and dealer name
     */
    public function getAllOrders()
    {
        // Check if user is admin
        $user = Auth::user();
        if ($user->userRole !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            // Get all orders with relationships
            $orders = Order::with(['user', 'product', 'dealer'])
                ->orderBy('created_at', 'desc')
                ->get();

            $ordersData = $orders->map(function ($order) {
                // Handle order_date formatting - it might be a string or Carbon object
                $orderDate = 'N/A';
                if ($order->order_date) {
                    if (is_string($order->order_date)) {
                        try {
                            $orderDate = Carbon::parse($order->order_date)->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            $orderDate = $order->order_date; // Use as is if parsing fails
                        }
                    } else {
                        $orderDate = $order->order_date->format('Y-m-d H:i:s');
                    }
                }

                return [
                    'id' => $order->id,
                    'architectName' => $order->user->name ?? 'N/A',
                    'architectEmail' => $order->user->email ?? 'N/A',
                    'productName' => $order->product->name ?? 'N/A',
                    'productCode' => $order->product->code ?? 'N/A',
                    'productType' => $order->product->type ?? 'N/A',
                    'dealerName' => $order->dealer->name ?? 'N/A',
                    'dealerMobile' => $order->dealer->mobile ?? 'N/A',
                    'quantity' => $order->quantity,
                    'redeemPoints' => $order->redeem_points,
                    'orderStatus' => $order->order_status,
                    'adminConfirm' => $order->admin_confirm,
                    'redeemPointStatus' => $order->redeem_point_status,
                    'orderDate' => $orderDate,
                    'createdAt' => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : 'N/A',
                ];
            });

            return response()->json([
                'status' => 200,
                'message' => 'All orders retrieved successfully',
                'data' => $ordersData,
                'totalCount' => $orders->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get admin profile
     */
    public function getAdminProfile()
    {
        // Check if user is admin
        $user = Auth::user();
        if ($user->userRole !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Admin profile fetched successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'mobile_no' => $user->mobile_no ?? '',
                'firmName' => $user->firmName ?? '',
                'officeAddress' => $user->officeAddress ?? '',
                'image' => $user->image ? url('storage/' . $user->image) : null,
                'userRole' => $user->userRole ?? '',
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Update admin profile with image upload
     */
    public function updateAdminProfile(Request $request)
    {
        // Check if user is admin
        $user = Auth::user();
        if ($user->userRole !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            // Validate input
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'mobile_no' => 'nullable|string|max:15',
                'firmName' => 'nullable|string|max:255',
                'officeAddress' => 'nullable|string|max:500',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048' // 2MB max
            ]);

            // Update user details
            $user->name = $request->name;
            $user->email = $request->email;
            $user->mobile_no = $request->mobile_no;
            $user->firmName = $request->firmName;
            $user->officeAddress = $request->officeAddress;

            // Handle profile image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }
                
                // Store new image
                $imagePath = $request->file('image')->store('admin_profile_images', 'public');
                $user->image = $imagePath;
            }

            $user->save();

            return response()->json([
                'status' => 200,
                'message' => 'Admin profile updated successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile_no' => $user->mobile_no,
                    'firmName' => $user->firmName,
                    'officeAddress' => $user->officeAddress,
                    'image' => $user->image ? url('storage/' . $user->image) : null,
                    'userRole' => $user->userRole,
                    'updated_at' => $user->updated_at,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating admin profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm order by admin
     */
    public function confirmOrder(Request $request, $orderId)
    {
        // Check if user is admin
        $user = Auth::user();
        if ($user->userRole !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            // Find the order
            $order = Order::find($orderId);
            
            if (!$order) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Order not found.'
                ], 404);
            }

            // Check if order is already confirmed
            if ($order->admin_confirm == 1) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Order is already confirmed.'
                ], 400);
            }

            // Check if order is cancelled
            if ($order->order_status === 'Cancelled') {
                return response()->json([
                    'status' => 400,
                    'message' => 'Cannot confirm a cancelled order.'
                ], 400);
            }

            // Update order confirmation and status
            $order->admin_confirm = '1';
            $order->order_status = 'Confirm';
            $order->save();

            // Load related data for response
            $order->load(['user', 'product', 'dealer']);

            return response()->json([
                'status' => 200,
                'message' => 'Order confirmed successfully.',
                'data' => [
                    'id' => $order->id,
                    'architectName' => $order->user->name ?? 'N/A',
                    'architectEmail' => $order->user->email ?? 'N/A',
                    'productName' => $order->product->name ?? 'N/A',
                    'productCode' => $order->product->code ?? 'N/A',
                    'dealerName' => $order->dealer->name ?? 'N/A',
                    'quantity' => $order->quantity,
                    'redeemPoints' => $order->redeem_points,
                    'orderStatus' => $order->order_status,
                    'adminConfirm' => $order->admin_confirm,
                    'orderDate' => $order->order_date ? $order->order_date->format('Y-m-d H:i:s') : 'N/A',
                    'confirmedAt' => now()->format('Y-m-d H:i:s'),
                    'confirmedBy' => $user->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error confirming order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark order as delivered by admin
     */
    public function markDelivered(Request $request, $orderId)
    {
        // Check if user is admin
        $user = Auth::user();
        if ($user->userRole !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            // Find the order
            $order = Order::find($orderId);
            
            if (!$order) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Order not found.'
                ], 404);
            }

            // Check if order is already delivered
            if ($order->order_status === 'Delivered') {
                return response()->json([
                    'status' => 400,
                    'message' => 'Order is already marked as delivered.'
                ], 400);
            }

            // Check if order is cancelled
            if ($order->order_status === 'Cancelled') {
                return response()->json([
                    'status' => 400,
                    'message' => 'Cannot mark a cancelled order as delivered.'
                ], 400);
            }

            // Update order confirmation and status
            $order->admin_confirm = '1';
            $order->order_status = 'Delivered';
            $order->save();

            // Load related data for response
            $order->load(['user', 'product', 'dealer']);

            return response()->json([
                'status' => 200,
                'message' => 'Order marked as delivered successfully.',
                'data' => [
                    'id' => $order->id,
                    'architectName' => $order->user->name ?? 'N/A',
                    'architectEmail' => $order->user->email ?? 'N/A',
                    'productName' => $order->product->name ?? 'N/A',
                    'productCode' => $order->product->code ?? 'N/A',
                    'dealerName' => $order->dealer->name ?? 'N/A',
                    'quantity' => $order->quantity,
                    'redeemPoints' => $order->redeem_points,
                    'orderStatus' => $order->order_status,
                    'adminConfirm' => $order->admin_confirm,
                    'orderDate' => $order->order_date ? $order->order_date->format('Y-m-d H:i:s') : 'N/A',
                    'deliveredAt' => now()->format('Y-m-d H:i:s'),
                    'deliveredBy' => $user->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error marking order as delivered.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel order by admin
     */
    public function cancelOrder(Request $request, $orderId)
    {
        // Check if user is admin
        $user = Auth::user();
        if ($user->userRole !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
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

            // Check if order is already cancelled
            if ($order->order_status === 'Cancelled') {
                return response()->json([
                    'status' => 400,
                    'message' => 'Order is already cancelled.'
                ], 400);
            }

            // Check if order is already delivered
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
            $order->load(['user', 'product', 'dealer']);

            return response()->json([
                'status' => 200,
                'message' => 'Order cancelled successfully.',
                'data' => [
                    'id' => $order->id,
                    'architectName' => $order->user->name ?? 'N/A',
                    'architectEmail' => $order->user->email ?? 'N/A',
                    'productName' => $order->product->name ?? 'N/A',
                    'productCode' => $order->product->code ?? 'N/A',
                    'dealerName' => $order->dealer->name ?? 'N/A',
                    'quantity' => $order->quantity,
                    'redeemPoints' => $order->redeem_points,
                    'orderStatus' => $order->order_status,
                    'adminConfirm' => $order->admin_confirm,
                    'orderDate' => $order->order_date ? $order->order_date->format('Y-m-d H:i:s') : 'N/A',
                    'cancelledAt' => now()->format('Y-m-d H:i:s'),
                    'cancelledBy' => $user->name,
                    'cancellationReason' => $order->cancellation_reason
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error cancelling order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
