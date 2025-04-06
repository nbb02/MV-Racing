<?php

namespace App\Http\Controllers;

use App\Models\Addresses;
use App\Models\Carts;
use App\Models\OrderItems;
use Illuminate\Http\Request;
use App\Models\Orders;
use App\Models\Products;
use App\Models\ProofOfPayments;
use App\Models\Trackings;
use Illuminate\Support\Str;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role === 'admin') {
            $orders = Orders::with(['items', 'payments', 'trackings'])->get();
            return response()->json($orders);
        }

        $user_id = $request->user()->id;
        $orders = Orders::with(['items', 'payments', 'trackings'])->where('user_id', $user_id)->get();
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string|in:Gcash,Cash on Delivery',
            'carts' => 'required|array',
            'carts.*' => 'required',
            'address_id' => 'required',
        ]);

        $validated['user_id'] = $request->user()->id;

        $cart_items = Carts::where('user_id', $validated['user_id'])->whereIn('id', $validated['carts'])->get();


        if (count($cart_items) !== count($validated['carts'])) {
            return response()->json(['message' => 'Some cart items are invalid, cart item records not equal.'], 409);
        }

        $address = Addresses::findOrFail($validated['address_id']);
        if ($address->user_id !== $validated['user_id']) {
            return response()->json(['message' => 'Address does not belong to user'], 409);
        }

        $product_ids = array_unique(array_column($cart_items->toArray(), 'product_id'));
        $products = Products::with(['stocks'])->whereIn('id', $product_ids)->get();

        if (count($product_ids) !== count($products)) {
            return response()->json(['message' => 'Some products are invalid', 'data' => [
                'product_ids' => $product_ids,
                'products' => $products
            ]], 409);
        };

        $total = 0;
        $items = [];
        foreach ($cart_items as $item) {
            $product = $products->firstWhere('id', $item['product_id']);

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            $product_stock = $product->stocks;
            foreach (['designs', 'types', 'colors', 'motor_types'] as $field) {
                if (isset($products[$field])) {
                    $product_stock = $product_stock->where($field, $item[$field]);
                }
            }
            $_product_stock = $product_stock->first();
            $product_stock = $_product_stock->stocks ?? 0;
            if ($product_stock < $item['quantity']) {
                return response()->json(['message' => "Product $product->name is out of stock", 'data' => [
                    'product_stock' => $_product_stock,
                    'quantity' => $item['quantity']
                ]], 409);
            }

            $_product_stock->stocks -= $item['quantity'];
            $_product_stock->save();

            $order_item_total =  $item['quantity'] * $product['price'];
            $total += $order_item_total;

            $items[] = [
                'product_id' => $product['id'],
                'quantity' => $item['quantity'],
                'price' => $product['price'],
                'total' => $order_item_total,
                'designs' => $item['designs'],
                'types' => $item['types'],
                'colors' => $item['colors'],
                'motor_types' => $item['motor_types'],
            ];
        }

        $order = Orders::create(array_merge($validated, [
            'status' => 'for approval',
            'total' => $total
        ]));

        foreach ($items as $item) {
            OrderItems::create(array_merge($item, [
                'order_id' => $order->id
            ]));
        }

        foreach ($cart_items as $cart) {
            $cart->delete();
        }

        $order->items = $items;
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:accept,decline',
            'reason' => 'required_if:status,decline|string',
        ]);

        $order = Orders::findOrFail($id);

        if ($order->status !== 'for approval') {
            return response()->json(['message' => 'Order is not for approval'], 409);
        }

        $order->status = $request->status === 'accept' ? (
            $order->payment_method === 'Cash on Delivery' ? 'to ship' : 'to pay'
        ) : 'declined';
        if ($request->status === 'decline') {
            $order->reason = $request->reason;
        }
        $order->save();

        return response()->json($order);
    }

    public function show($id)
    {
        $order = Orders::findOrFail($id);

        $order->load(['trackings', 'payments', 'items']);

        return response()->json($order, 200);
    }

    public function sendProofOfPayment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'proof_of_payments' => 'required|array',
            'proof_of_payments.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $order = Orders::findOrFail($validated['order_id']);

        if ($order->status !== 'to pay') {
            return response()->json(['message' => 'Order is not ready to be paid'], 409);
        }

        foreach ($request->file('proof_of_payments') as $file) {

            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('proof_of_payments', $filename, ['disk' => 'public']);
            $path = 'proof_of_payments' . '/' . $filename;

            ProofOfPayments::create([
                'order_id' => $order->id,
                'image' => $path
            ]);
        }

        $order->load(['payments']);
        $payments = $order->payments;
        foreach ($payments as $payment) {
            $payment->image = env('APP_URL') . '/images/' . $payment->image;
        }
        $order->payments = $payments;

        return response()->json($order);
    }

    public function acceptPayment($id)
    {
        $order = Orders::findOrFail($id);

        $order->load(['payments']);

        if ($order->payments->count() === 0) {
            return response()->json(['message' => 'No payment found'], 409);
        }

        if ($order->status !== 'to pay') {
            return response()->json(['message' => 'Order is not ready to be paid'], 409);
        }

        $order->status = 'to ship';
        $order->save();

        return response()->json([
            'message' => 'Payment accepted successfully',
            'order' => $order
        ]);
    }

    public function ship(Request $request, $id)
    {
        $request->validate([
            'tracking_no' => 'required',
            'delivery_partner' => 'required',
        ]);

        $order = Orders::findOrFail($id);

        if ($order->status !== 'to ship') {
            return response()->json(['message' => 'Order is not ready to be shipped or is already shipped'], 409);
        }

        $order->tracking_no = $request->tracking_no;
        $order->delivery_partner = $request->delivery_partner;
        $order->status = 'shipped';

        $order->save();

        Trackings::create([
            'order_id' => $order->id,
            'title' => 'Order shipped',
            'description' => 'Your order has been shipped',
        ]);

        return response()->json([
            'message' => 'Order shipped successfully',
            'order' => $order->load(['trackings'])
        ]);
    }

    public function addTracking(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required',
            'description' => 'required',
        ]);

        $order = Orders::findOrFail($id);

        if ($order->status !== 'shipped') {
            return response()->json(['message' => 'Order is not shipped'], 409);
        }

        Trackings::create([
            'order_id' => $order->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
        ]);

        return response()->json([
            'message' => 'Tracking updated successfully',
            'order' => $order->load(['trackings'])
        ]);
    }

    public function cancel($id)
    {
        $order = Orders::findOrFail($id);

        if ($order->status === 'cancelled' || $order->status !== 'to ship' || $order->status !== 'to pay') {
            return response()->json(['message' => 'Order is already cancelled'], 409);
        }

        $order->status = 'cancelled';
        $order->save();

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order
        ]);
    }

    public function delivered(Request $request, $id)
    {
        $request->validate([
            'receiver' => 'required',
        ]);

        $order = Orders::findOrFail($id);

        if ($order->status === 'delivered') {
            return response()->json(['message' => 'Order is already delivered'], 409);
        }

        $order->status = 'delivered';
        $order->save();

        Trackings::create([
            'order_id' => $order->id,
            'title' => 'Order delivered',
            'description' => "
                Package has been Delivered.

                Please check the item if complete and in good condition.

                Receive by $request->receiver",
        ]);

        return response()->json([
            'message' => 'Order delivered successfully',
            'order' => $order->load(['trackings'])
        ]);
    }

    public function receive($id)
    {
        $order = Orders::findOrFail($id);

        if ($order->status === 'received') {
            return response()->json(['message' => 'Order is already received'], 409);
        }

        if ($order->status !== 'delivered') {
            return response()->json(['message' => 'Order is not delivered'], 409);
        }

        $order->status = 'received';
        $order->save();

        Trackings::create([
            'order_id' => $order->id,
            'title' => 'Order received',
            'description' => "Package has been Received by the customer;",
        ]);

        return response()->json([
            'message' => 'Order received successfully',
            'order' => $order->load(['trackings'])
        ]);
    }
}
