<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\UserBankDetail;
use App\Models\AdminCompanyDetail;

class UserController extends Controller
{
    // Get User Profile API
    public function getUserProfile()
    {
        $user = Auth::user();

        return response()->json([
            'status' => 200,
            'message' => 'User profile fetched successfully.',
            'user' => [
                'id' => $user->id,
                'fullName' => $user->name ?? '',
                'email' => $user->email ?? '',
                'mobileNumber' => $user->mobile_no ?? '',
                'firmName' => $user->firmName ?? '',
                'officeAddress' => $user->officeAddress ?? '',
                // 'image' => $user->image ? url('storage/' . $user->image) : url('storage/default.png'),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    // Update User Profile API
    public function updateUserProfile(Request $request)
    {
        $user = Auth::user();

        // Validate input
        $request->validate([
            'fullName' => 'required|string|max:255',
            'mobileNumber' => 'nullable|string|max:15',
            'firmName' => 'nullable|string|max:15',
            'officeAddress' => 'nullable|string|max:15',
            // 'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048' // Image validation
        ]);

        // Update user details
        $user->name = $request->fullName;
        $user->mobile_no = $request->mobileNumber;
        $user->firmName = $request->firmName;
        $user->officeAddress = $request->officeAddress;

        // Handle profile image upload
        // if ($request->hasFile('image')) {
        //     // Delete old image if exists
        //     if ($user->image) {
        //         Storage::delete($user->image);
        //     }
        //     $imagePath = $request->file('image')->store('profile_images', 'public');
        //     $user->image = $imagePath;
        // }

        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'User profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'fullName' => $user->name,
                'mobileNumber' => $user->mobile_no,
                'firmName' => $user->firmName,
                'officeAddress' => $user->officeAddress,
                // 'image' => $user->image ? url('storage/' . $user->image) : url('storage/default.png'),
            ]
        ]);
    }

    // Change Password API
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        // Validate input
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Check old password
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'status' => 400,
                'message' => 'Old password is incorrect.'
            ], 400);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'Password changed successfully.'
        ]);
    }

    public function addBankDetails(Request $request)
    {
        $request->validate([
            'account_no' => 'required|string|max:20|unique:user_bank_details,account_no',
            'ifsc_code' => 'required|string|max:11',
            'bank_name' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        // Check if bank details already exist
        if ($user->bankDetails) {
            return response()->json([
                'status' => 400,
                'message' => 'Bank details already exist. Please update instead.'
            ], 400);
        }

        $bankDetail = UserBankDetail::create([
            'user_id' => $user->id,
            'account_no' => $request->account_no,
            'ifsc_code' => $request->ifsc_code,
            'bank_name' => $request->bank_name,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Bank details added successfully.',
            'bank_details' => $bankDetail
        ]);
    }

    // Get Bank Details API
    public function getBankDetails()
    {
        $user = Auth::user();
        $bankDetail = $user->bankDetails;

        if (!$bankDetail) {
            return response()->json([
                'status' => 404,
                'message' => 'No bank details found.'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Bank details fetched successfully.',
            'bank_details' => $bankDetail
        ]);
    }

    // Update Bank Details API
    public function updateBankDetails(Request $request)
    {
        $request->validate([
            'account_no' => 'required|string|max:20',
            'ifsc_code' => 'required|string|max:11',
            'bank_name' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $bankDetail = $user->bankDetails;

        if (!$bankDetail) {
            return response()->json([
                'status' => 404,
                'message' => 'No bank details found. Please add first.'
            ], 404);
        }

        $bankDetail->update([
            'account_no' => $request->account_no,
            'ifsc_code' => $request->ifsc_code,
            'bank_name' => $request->bank_name,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Bank details updated successfully.',
            'bank_details' => $bankDetail
        ]);
    }
    // Get Company Details API
    public function getCompanyDetails()
    {
        $data = AdminCompanyDetail::first();

        if (!$data) {
            return response()->json([
                'status' => 404,
                'message' => 'No bank details found.'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Company details fetched successfully.',
            'bank_details' => $data
        ]);
    }





}
