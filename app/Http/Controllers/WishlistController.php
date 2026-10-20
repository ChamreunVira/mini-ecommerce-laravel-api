<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $wishlist = Wishlist::all()->load(['user', 'products']);
        return ApiResponse::success($wishlist);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $wishlist = $request->user()->wishlist()->firstOrCreate([]);

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id'
        ]);

        $wishlist = Wishlist::create($data);

        return ApiResponse::success($wishlist);
    }

    /**
     * Display the specified resource.
     */
    public function show(Wishlist $wishlist)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Wishlist $wishlist)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Wishlist $wishlist)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Wishlist $wishlist)
    {
        //
    }
}
