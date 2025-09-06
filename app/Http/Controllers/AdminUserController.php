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
}
