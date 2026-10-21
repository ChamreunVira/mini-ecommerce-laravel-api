<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $address = Address::where("user_id", $user->id)->get();
        return ApiResponse::success($address);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'recipient_name' => 'required|string|min:3',
            'phone' => 'required|string|min:8',
            'address_line' => 'required|string|min:6',
            'city' => 'required|string',
            'is_default' => 'required|boolean'
        ]);

        $address = Address::create([
            'user_id' => $user->id,
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'is_default' => $data['is_default']
        ]);

        return ApiResponse::success(new AddressResource($address));
    }

    /**
     * Display the specified resource.
     */
    public function show(Address $address)
    {
        return ApiResponse::success(new AddressResource($address));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Address $address)
    {
        $user = $request->user();
        $data = $request->validate([
            'recipient_name' => 'required|string|min:3',
            'phone' => 'required|string|min:8',
            'address_line' => 'required|string|min:6',
            'city' => 'required|string',
            'is_default' => 'sometimes|boolean'
        ]);

        $address->update([
            'user_id' => $user->id,
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'is_default' => $data['is_default']
        ]);

        return ApiResponse::success(new AddressResource($address));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address)
    {
        $address->delete();
        return ApiResponse::success("", "Address deleted successfully.");
    }
}
