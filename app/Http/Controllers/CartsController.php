<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Carts;
use App\Models\Products;

class CartsController extends Controller
{
    public function index(Request $request)
    {
        $cartItems = Carts::with('product')->where('user_id', $request->user()->id)->get();
        return response()->json($cartItems);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'designs' => 'nullable|string',
            'types' => 'nullable|string',
            'colors' => 'nullable|string',
            'motor_types' => 'nullable|string',
        ]);

        $product = Products::findOrFail($validatedData['product_id']);
        $product->load('stocks');

        $requiredFields = [];
        foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
            if (isset($product[$field])) {

                if (!in_array($validatedData[$field], $product[$field])) {
                    return response()->json(['message' => "The $field field is not valid."], 422);
                }

                if (!isset($validatedData[$field]) || empty($validatedData[$field])) {
                    return response()->json(['message' => "The $field field is required."], 422);
                }

                $requiredFields[$field] = $validatedData[$field];
            }
        }

        $user_id = $request->user()->id;

        $exists = Carts::where('user_id', $user_id)
            ->where('product_id', $validatedData['product_id'])
            ->where('designs', $requiredFields['designs'] ?? null)
            ->where('types', $requiredFields['types'] ?? null)
            ->where('colors', $requiredFields['colors'] ?? null)
            ->where('motor_types', $requiredFields['motor_types'] ?? null)
            ->first();

        if ($exists) {
            $product_stock = $product->stocks;
            foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
                if (isset($products[$field])) {
                    $product_stock = $product_stock->where($field, $validatedData[$field]);
                }
            }
            $product_stock = $product_stock->first()->stocks ?? 0;

            if ($product_stock < $exists->quantity + 1) {
                return response()->json(['message' => "Product $product->name has insufficient stock"], 409);
            }

            $exists->quantity += 1;
            $exists->save();

            return response()->json($exists, 200);
        }

        $product_stock = $product->stocks;
        foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
            if (isset($products[$field])) {
                $product_stock = $product_stock->where($field, $validatedData[$field]);
            }
        }
        $product_stock = $product_stock->first()->stocks ?? 0;

        if ($product_stock < $validatedData['quantity']) {
            return response()->json(['message' => "Product $product->name has insufficient stock"], 409);
        }

        $cartItem = Carts::create(array_merge([
            'user_id' => $user_id,
            'product_id' => $validatedData['product_id'],
            'quantity' => $validatedData['quantity'],
        ], $requiredFields));

        return response()->json($cartItem, 201);
    }

    public function update(Request $request, $id)
    {

        $validatedData = $request->validate([
            'quantity' => 'integer|min:1',
            'designs' => 'string',
            'types' => 'string',
            'colors' => 'string',
            'motor_types' => 'string',
        ]);

        $cartItem = Carts::findOrFail($id);

        $product = Products::findOrFail($cartItem->product_id);

        $product_stock = $product->stocks;
        foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
            if (isset($products[$field])) {
                $product_stock = $product_stock->where($field, $validatedData[$field]);
            }
        }
        $product_stock = $product_stock->first()->stocks ?? 0;

        if (isset($validatedData['quantity']) && $validatedData['quantity'] > $product_stock) {
            return response()->json(['message' => 'Insufficient stock'], 400);
        }

        foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
            if (isset($product[$field]) && isset($validatedData[$field])) {

                if (!in_array($validatedData[$field], $product[$field])) {
                    return response()->json(['message' => "The $field field is not valid."], 422);
                }

                $cartItem[$field] = $validatedData[$field];
            }
        }

        if (isset($validatedData['quantity'])) {
            $cartItem->quantity = $validatedData['quantity'];
        }

        $cartItem->save();

        return response()->json($cartItem);
    }

    public function destroy($id)
    {
        $cartItem = Carts::findOrFail($id);
        $cartItem->delete();
        return response()->json(['message' => 'Cart item deleted successfully']);
    }
}
