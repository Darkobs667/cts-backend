<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InviteCodeController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $request->validate(['quantity' => 'integer|min:1|max:100']);
        $quantity = $request->input('quantity', 1);
        $admin = auth('api')->user();

        $codes = [];
        for ($i = 0; $i < $quantity; $i++) {
            $codes[] = InviteCode::create([
                'code'       => strtoupper(Str::random(8)),
                'used'       => false,
                'created_by' => $admin->id,
            ]);
        }

        return response()->json(['success' => true, 'data' => $codes], 201);
    }

    public function index(): JsonResponse
    {
        $codes = InviteCode::with('usedByUser:id,first_name,last_name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $codes]);
    }

    public function destroy($id): JsonResponse
    {
        $code = InviteCode::findOrFail($id);
        if ($code->used) {
            return response()->json(['message' => 'Impossible de supprimer un code déjà utilisé.'], 400);
        }
        $code->delete();
        return response()->json(['message' => 'Code supprimé.']);
    }
}
