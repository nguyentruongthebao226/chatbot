<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Embedder;
use App\Services\QdrantService;
use App\Models\ChatMessage;
use OpenAI;

class ChatController_bk extends Controller
{
    public function ask(Request $request)
    {
        $question = $request->input('question');

        // [1] Sinh vector embedding từ câu hỏi hiện tại
        $embedding = Embedder::embed($question);
        logger("Embedding vector created", $embedding);

        // [2] Kiểm tra nếu câu hỏi tương tự đã từng được hỏi (để trả lời giống)
        $minDistance = 1.0;
        $closestAnswer = null;
        foreach (ChatMessage::all() as $log) {
            $pastVector = json_decode($log->embedding, true);
            $distance = $this->cosineDistance($embedding, $pastVector);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestAnswer = $log->answer;
            }
        }

        // Nếu câu hỏi tương tự với câu cũ (độ lệch nhỏ hơn 0.04) thì trả lời như cũ
        if ($minDistance < 0.04) {
            return response()->json([
                'answer' => $closestAnswer,
                'matched_from' => 'log_cache'
            ]);
        }

        // [3] Tìm top 5 đoạn tài liệu gần nhất từ Qdrant
        $results = QdrantService::search('doc_chunks', $embedding, 5);
        logger("Qdrant top 5 results", $results);

        // [4] Lấy phần text từ các đoạn trả về
        $contextChunks = collect($results)->pluck('payload.text')->filter()->values();

        // Nếu không tìm được đoạn nào phù hợp → trả lời lỗi
        if ($contextChunks->isEmpty()) {
            return response()->json([
                'answer' => 'Xin lỗi, tôi không tìm thấy câu trả lời phù hợp trong tài liệu.',
                'source' => 'no_context_found'
            ]);
        }

        // [5] Chuẩn bị context từ các đoạn tài liệu
        $context = $contextChunks->implode("\n---\n");
        logger("Context sent to GPT", ['context' => $context]);

        // [6] Gọi OpenAI GPT chỉ dựa trên context tài liệu
        $openai = OpenAI::client(env('OPENAI_API_KEY'));
        $chatResponse = $openai->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Bạn là một trợ lý AI chỉ được phép trả lời dựa trên các đoạn tài liệu nội bộ bên dưới. Nếu không tìm thấy thông tin phù hợp, bạn phải trả lời đúng một câu: '[StatusCode=404] Xin lỗi, tôi không tìm thấy câu trả lời phù hợp trong tài liệu.' Tuyệt đối không tự bịa thông tin hoặc sử dụng kiến thức bên ngoài.\n\nTài liệu:\n$context"
                ],
                ['role' => 'user', 'content' => $question],
            ],
        ]);

        $answer = $chatResponse->choices[0]->message->content;

        // [7] Lưu lại log câu hỏi để dùng sau
        ChatMessage::create([
            'question' => $question,
            'answer' => $answer,
            'embedding' => json_encode($embedding)
        ]);

        return response()->json([
            'answer' => $answer,
            'source' => 'gpt'
        ]);
    }

    /**
     * Tính khoảng cách cosine giữa 2 vector
     */
    private function cosineDistance(array $a, array $b): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ($a as $i => $val) {
            $dotProduct += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0 || $normB == 0) return 1.0;

        return 1 - ($dotProduct / (sqrt($normA) * sqrt($normB)));
    }
}
