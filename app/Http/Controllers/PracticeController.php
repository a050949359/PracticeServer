<?php

namespace App\Http\Controllers;

use App\Http\Requests\PracticeRequest;
use Illuminate\Http\JsonResponse;

class PracticeController extends Controller
{
    public function echoText(PracticeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'message' => $validated['text'],
            'length' => mb_strlen($validated['text']),
        ]);
    }

    public function sumValues(PracticeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'a' => $validated['a'],
            'b' => $validated['b'],
            'sum' => $validated['a'] + $validated['b'],
        ]);
    }

    public function multiplyValues(PracticeRequest $request) :JsonResponse
    {
     $validated = $request->validated();
        $result=$validated['a'] * $validated['b'];
        return response()->json(['a'=>$validated['a'],'b'=>$validated['b'],'product' =>$result]);
    }
}
