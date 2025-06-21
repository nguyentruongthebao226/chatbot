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
        logger("ChatController embed embedding: ", $embedding);

        // 2. So sánh với câu hỏi cũ để lấy câu trả lời giống nếu gần giống
        $minDistance = 1.0;
        $closestAnswer = null;
        $pastLogs = ChatLog::all(); // Cân nhắc giới hạn số lượng
        foreach ($pastLogs as $log) {
            $pastVector = json_decode($log->embedding, true);
            $distance = $this->cosineDistance($embedding, $pastVector);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestAnswer = $log->answer;
            }
        }
        if ($minDistance < 0.04) {
            return response()->json(['answer nearest' => $closestAnswer]);
        }

        // 3. Tìm top 5 đoạn văn gần nhất trong Qdrant
        $results = QdrantService::search('doc_chunks', $embedding, 5);
        logger("ChatController search results: ", $results);

        // 4. Kiểm tra score. Nếu không có đoạn nào đủ độ liên quan thì báo không tìm thấy
        $relevantChunks = collect($results)->pluck('payload.text')->values();

        if ($relevantChunks->isEmpty()) {
            return response()->json(['answer fail' => '[StatusCode=404] Xin lỗi, tôi không tìm thấy câu trả lời phù hợp trong tài liệu.']);
        }

        // 5. Gộp lại làm context
        $context = $relevantChunks->implode("\n---\n");
        logger("Context for GPT: ", ['text' => $context]);

        // 6. Gọi GPT với context
        $openai = OpenAI::client(env('OPENAI_API_KEY'));
        $chatResponse = $openai->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Bạn là một trợ lý AI chỉ được phép trả lời dựa trên các đoạn tài liệu nội bộ bên dưới. Nếu không tìm thấy thông tin phù hợp, bạn phải trả lời đúng một câu: '[StatusCode=404] Xin lỗi, tôi không tìm thấy câu trả lời phù hợp trong tài liệu.' Tuyệt đối không tự bịa thông tin hoặc sử dụng kiến thức bên ngoài.
Tài liệu:
$context"
                ],
                ['role' => 'user', 'content' => $question],
            ],
        ]);

        $answer = $chatResponse->choices[0]->message->content;

        // 7. Lưu log
        ChatLog::create([
            'question' => $question,
            'answer' => $answer,
            'embedding' => json_encode($embedding)
        ]);

        return response()->json(['answer final' => $answer]);
    }

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

        if ($normA == 0 || $normB == 0) {
            return 1; // tránh chia cho 0
        }

        return 1 - ($dotProduct / (sqrt($normA) * sqrt($normB)));
    }
}
