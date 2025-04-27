<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Dealer;

class ProductDealerController extends Controller
{
    public function getProducts()
    {
        $products = Product::all(); // Fetch all products
        return response()->json(['products' => $products], 200);
    }

    // Get Dealer List API
    public function getDealers()
    {
        $dealers = Dealer::all(); // Fetch all dealers
        return response()->json([
            'status' => 200,
            'message' => 'Dealers Fetch successfully.',
            'dealers' => $dealers
        ], 200);
    }

    public function getProductsByType(Request $request)
    {
        // Fetch products matching the type
        $products = Product::where('type', $request->type)->get();

        // Format response
        $formattedProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name . ' - ' . $product->code .' - '. $product->point
            ];
        });

        if ($formattedProducts->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => 'No products found for the given type.',
                'product' => []
            ], 404);
        }

        // Return JSON response
        return response()->json([
            'status' => 200,
            'message' => 'Product Fetch successfully.',
            'product' => $formattedProducts
        ], 200);
    }
}
