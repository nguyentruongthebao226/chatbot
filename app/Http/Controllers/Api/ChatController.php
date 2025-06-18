<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function ask(Request $request)
    {
        $question = $request->input('message');
        return response()->json([
            'answer' => "Bạn đã hỏi: $question"
        ]);
    }
}
