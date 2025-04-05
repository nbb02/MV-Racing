<?php

namespace App\Http\Controllers;

use App\Models\QR;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QRController extends Controller
{
    public function index()
    {
        $qrs = QR::all();

        foreach ($qrs as $qr) {
            $qr->image = env('APP_URL') . '/images/' . $qr->image;
        }

        return response()->json($qrs);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'required|string'
        ]);

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('qr', $filename, ['disk' => 'public']);
        $path = 'qr' . '/' . $filename;

        $qrs = Qr::all();

        foreach ($qrs as $qr) {
            if (Storage::disk('public')->exists($qr->image)) {
                Storage::disk('public')->delete($qr->image);
            }
            $qr->delete();
        }

        $qr = QR::create([
            'description' => $request->description,
            'image' => $path,
        ]);

        $qr->image = env('APP_URL') . '/images/' . $qr->image;

        return response()->json($qr, 201);
    }

    public function destroy($id)
    {
        $qr = QR::find($id);

        if (!$qr) {
            return response()->json(['message' => 'QR not found'], 404);
        }

        if (Storage::disk('public')->exists($qr->image)) {
            Storage::disk('public')->delete($qr->image);
        }

        $qr->delete();

        return response()->json([
            'message' => 'qr delete successfully'
        ]);
    }
}
