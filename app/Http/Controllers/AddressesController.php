<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Addresses;

class AddressesController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Addresses::where('user_id', $request->user()->id)->get();
        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'recipient' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'region_city_district' => 'required|string|max:255',
            'street_building' => 'required|string|max:255',
            'unit_floor' => 'nullable|string|max:255',
            'additional_info' => 'nullable|string|max:255',
        ]);

        $validatedData['user_id'] = $request->user()->id;

        $address = Addresses::create($validatedData);
        return response()->json($address, 201);
    }

    public function show($id)
    {
        $address = Addresses::findOrFail($id);
        return response()->json($address);
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'recipient' => 'string|max:255',
            'phone' => 'string|max:20',
            'region_city_district' => 'string|max:255',
            'street_building' => 'string|max:255',
            'unit_floor' => 'nullable|string|max:255',
            'additional_info' => 'string|max:255',
        ]);

        $address = Addresses::findOrFail($id);
        $address->update($validatedData);
        return response()->json($address);
    }

    public function destroy($id)
    {
        $address = Addresses::findOrFail($id);
        $address->delete();
        return response()->json(['message' => 'Address deleted successfully']);
    }
}
