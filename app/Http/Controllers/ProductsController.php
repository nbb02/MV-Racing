<?php

namespace App\Http\Controllers;

use App\Models\ProductImages;
use App\Models\Products;
use App\Models\ProductStocks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductsController extends Controller
{
    public function index()
    {
        $products = Products::with(['images', 'stocks'])->get();

        foreach ($products as $product) {
            foreach ($product->images as $image) {
                $image->image = $this->formatImage($image->image);
            }
        }

        return response()->json($products);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->user()->role != 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'category' => 'required|string',
                'price' => 'required|numeric',
                'designs' => 'nullable|json',
                'types' => 'nullable|json',
                'colors' => 'nullable|json',
                'motor_types' => 'nullable|json',
                'images' => 'required',
                'images.*' => 'file|mimes:jpeg,png,jpg|max:2048',
                'stocks' => 'required|json',
                'stocks.*.stocks' => 'required|numeric',
            ]);

            $typeCategories = [];
            foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
                if (isset($validatedData[$field])) {
                    $typeCategories[] = $field;
                }
                $validatedData[$field] = isset($validatedData[$field]) ? json_decode($validatedData[$field], true) : null;
            }

            if (empty($typeCategories)) {
                return response()->json(['message' => 'At least one category is required'], 422);
            }

            $product = Products::create($validatedData);

            if ($request->file('images')) {
                foreach ($request->file('images') as $file) {
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $file->storeAs('products', $filename, ['disk' => 'public']);
                    $savedPaths[] = 'products' . '/' . $filename;

                    ProductImages::create([
                        'product_id' => $product->id,
                        'image' => $savedPaths[0],
                    ]);
                }
            }

            $stocks = json_decode($validatedData['stocks']);

            foreach ($stocks as $stock) {
                foreach ($typeCategories as $typeCategory) {
                    if (isset($product[$typeCategory])) {
                        if (!isset($stock->$typeCategory) || !in_array($stock->$typeCategory, $product[$typeCategory])) {
                            return response()->json(['message' => 'Invalid stock data'], 422);
                        }
                    }
                }

                ProductStocks::create([
                    'product_id' => $product->id,
                    'stocks' => $stock->stocks,
                    'types' => $stock->types ?? null,
                    'colors' => $stock->colors ?? null,
                    'designs' => $stock->designs ?? null,
                    'motor_types' => $stock->motor_types ?? null,
                ]);
            }

            DB::commit();
            return response()->json($product->load('images'), 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            app('log')->error('Error in store method', [
                'error' => $th->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $product = Products::findOrFail($id);

        foreach ($product->images as $image) {
            $image->image = $this->formatImage($image->image);
        }

        $product->load('stocks');

        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        if ($request->user()->role != 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'string|max:255',
            'description' => 'string',
            'category' => 'string',
            'price' => 'numeric',
            'designs' => 'nullable|json',
            'types' => 'nullable|json',
            'colors' => 'nullable|json',
            'motor_types' => 'nullable|json',
        ]);

        foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
            if ($validatedData[$field]) {
                $validatedData[$field] = isset($validatedData[$field]) ? json_decode($validatedData[$field], true) : null;
            }
        }

        $product = Products::findOrFail($id);
        $product->update($validatedData);
        $product->load('images');

        foreach ($product->images as $image) {
            $image->image = $this->formatImage($image->image);
        }

        return response()->json($product);
    }

    public function stocks(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|numeric',
            'designs' => 'nullable|string',
            'types' => 'nullable|string',
            'colors' => 'nullable|string',
            'motor_types' => 'nullable|string',
            'stocks' => 'required|numeric'
        ]);

        $product = Products::findOrFail($validated['product_id']);

        $stock = ProductStocks::where('product_id', $validated['product_id']);

        foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
            if ($product[$field]) {
                if ($validated[$field]) {
                    if (!in_array($validated[$field], $product[$field])) {
                        return response()->json([
                            'message' => "$field not valid"
                        ], 409);
                    }

                    $stock->where($field, $validated[$field]);
                } else {
                    return response()->json([
                        'message' => "$field not valid"
                    ], 409);
                }
            }
        }

        $stock = $stock->first();

        if (!$stock) {
            $newStock = ProductStocks::create([
                'product_id' => $validated['product_id'],
                'stocks' => $validated['stocks'],
                'types' => $validated['types'] ?? null,
                'colors' => $validated['colors'] ?? null,
                'designs' => $validated['designs'] ?? null,
                'motor_types' => $validated['motor_types'] ?? null,
            ]);

            return response()->json(['message' => 'Stock created successfull', 'data' => $newStock], 201);
        }

        $stock->stocks = $request->stocks;

        return response()->json(['message' => 'Stock updated successfully', 'data' => $stock], 200);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role != 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product = Products::findOrFail($id);
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function addImages(Request $request, $productId)
    {
        if ($request->user()->role != 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'images.*' => 'required|image|max:2048',
        ]);

        $product = Products::findOrFail($productId);

        $uploadedImages = [];
        foreach ($request->file('images') as $image) {
            $imagePath = $image->store('products', 'public');

            $productImage = ProductImages::create([
                'product_id' => $product->id,
                'image' => $imagePath,
            ]);

            $uploadedImages[] = $productImage;
        }

        foreach ($uploadedImages as $image) {
            $image->image = $this->formatImage($image->image);
        }

        return response()->json($uploadedImages, 201);
    }

    public function deleteImage(Request $request, $imageId)
    {
        if ($request->user()->role != 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $productImage = ProductImages::findOrFail($imageId);
        Storage::disk('public')->delete($productImage->image);
        $productImage->delete();

        return response()->json(['message' => 'Image deleted successfully']);
    }

    private function formatImage($string)
    {
        return env('APP_URL') . '/public/uploads/' . $string;
    }

    public function addSelection(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|numeric',
            'type' => 'required|in:types,designs,colors,motor_types',
            'name' => 'required',
        ]);

        $product = Products::findOrFail($validated['product_id']);

        $product[$validated['type']] = array_merge($product[$validated['type']], [
            $validated['name']
        ]);

        $product->save();

        return response()->json([
            'message' => 'Selection added successfully',
            'data' => $product
        ]);
    }

    public function deleteSelection(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required',
            'type' => 'required|in:types,designs,colors,motor_types',
            'name' => 'required',
        ]);

        $product = Products::findOrFail($validated['product_id']);

        $product[$validated['type']] = array_filter($product[$validated['type']], function ($item) use ($validated) {
            return $item !== $validated['name'];
        });

        $product->save();

        $stocks = ProductStocks::where('product_id', $validated['product_id'])
            ->where($validated['type'], $validated['name'])->get();

        foreach ($stocks as $stock) {
            $stock->delete();
        }

        return response()->json([
            'message' => 'Selection added successfully',
            'data' => $product
        ]);
    }
}
