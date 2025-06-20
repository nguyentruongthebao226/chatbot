<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Embedder;
use App\Services\QdrantService;
use App\Models\ChatLog;
use OpenAI;


class ChatController extends Controller
{
    public function ask(Request $request)
    {
        $question = $request->input('question');

        // 1. Tạo embedding từ câu hỏi
        $embedding = Embedder::embed($question);

        // 2. Tìm top 3 đoạn gần nhất từ Qdrant
        $results = QdrantService::search('doc_chunks', $embedding, 3);

        // 3. Lấy các đoạn văn tìm được làm context
        $context = collect($results)->pluck('payload.text')->implode("\n---\n");

        // 4. Gọi GPT để trả lời dựa trên context
        $openai = OpenAI::client(env('OPENAI_API_KEY'));
        $chatResponse = $openai->chat()->create([
            'model' => 'gpt-4', // hoặc gpt-3.5-turbo
            'messages' => [
                ['role' => 'system', 'content' => "Bạn là trợ lý AI, trả lời dựa trên tài liệu công ty sau:\n\n$context"],
                ['role' => 'user', 'content' => $question],
            ],
        ]);

        $answer = $chatResponse->choices[0]->message->content;

        // 5. Lưu lại để dùng lần sau
        ChatLog::create([
            'question' => $question,
            'answer' => $answer,
            'embedding' => json_encode($embedding)
        ]);

        return response()->json(['answer' => $answer]);
    }
}
