<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Embedder;
use App\Services\QdrantService;
use App\Models\ChatMessage;
use App\Models\DocumentChunk;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI;

class ChatController extends Controller
{
    public function ask(Request $request)
    {
        $question = $request->input('question');
        $botId = $request->input('bot_id') ?? 1; // Default nếu chưa có multi bot
        $sender = $request->input('sender') ?? 'guest';

        // 1. Sinh embedding (dùng service mới)
        $embedding = \App\Services\EmbeddingService::generate($question);

        // 2. Check câu hỏi tương tự
        $cachedAnswer = $this->checkSimilarQuestions($embedding, $botId, $question);
        if ($cachedAnswer) {
            return response()->json([
                'answer' => $cachedAnswer,
                'matched_from' => 'log_cache'
            ]);
        }

        // 3. Search context từ Qdrant theo bot
        $contextData = $this->getRelevantContextWithScore($question, $botId);
        $context = $contextData['context'];
        $topScore = $contextData['top_score'];

        if (empty($context)) {
            return response()->json([
                'answer' => '[StatusCode=404] Xin lỗi, tôi không tìm thấy câu trả lời phù hợp trong tài liệu.',
                'source' => 'no_context_found'
            ]);
        }

        // 4. Lấy rule (nếu muốn)
        $rule = \App\Models\PromptInstruction::where('bot_id', $botId)->latest()->first()->rule ?? '';

        $systemContent = "Bạn là một trợ lý AI chỉ được phép trả lời dựa trên các đoạn tài liệu nội bộ bên dưới. Nếu không tìm thấy thông tin phù hợp, bạn phải trả lời đúng một câu: '[StatusCode=404] Xin lỗi, tôi không tìm thấy câu trả lời phù hợp trong tài liệu.' Tuyệt đối không tự bịa thông tin hoặc sử dụng kiến thức bên ngoài.\n\nTài liệu:\n";
        if ($rule) {
            $systemContent .= "【ルール】：{$rule}\n";
        }
        $systemContent .= "【ドキュメント】：\n{$context}";

        // 5. Gọi OpenAI
        $openai = \OpenAI::client(env('OPENAI_API_KEY'));
        $chatResponse = $openai->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $systemContent],
                ['role' => 'user', 'content' => $question],
            ],
        ]);
        $answer = $chatResponse->choices[0]->message->content;

        Log::info('Tới save rồi');
        // 6. Lưu lại log, cả MySQL và Qdrant
        $this->saveMessagesWithEmbedding($botId, $sender, $question, $answer, $embedding);

        // 7. Nếu là câu không trả lời được hoặc score thấp, trả lời lỗi
        if (str_contains($answer, '[StatusCode=404]') || $topScore < 0.25) {
            // Có thể lưu unanswered vào DB ở đây (nếu cần)
            return response()->json([
                'answer' => '[StatusCode=404] Xin lỗi, tôi không tìm thấy câu trả lời phù hợp trong tài liệu.',
                'source' => 'no_context_found'
            ]);
        }

        return response()->json([
            'answer' => $answer,
            'source' => 'gpt'
        ]);
    }

    private function checkSimilarQuestions(array $questionEmbedding, int $botId, string $currentQuestion): ?string
    {
        try {
            // ✅ Không dùng cache để debug, lấy trực tiếp từ DB
            $recentMessages = ChatMessage::where('bot_id', $botId)
                ->whereNotNull('question_embedding')
                ->whereNotNull('answer')
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get(['id', 'question', 'answer', 'question_embedding']);

            Log::info("Checking similarity for: {$currentQuestion}");
            Log::info("Found {$recentMessages->count()} previous messages with embeddings");

            if ($recentMessages->isEmpty()) {
                Log::info("No previous messages with embeddings found");
                return null;
            }

            $minDistance = 1.0;
            $closestAnswer = null;
            $closestQuestion = null;

            foreach ($recentMessages as $message) {
                if (empty($message->question_embedding)) {
                    continue;
                }

                // ✅ Parse embedding properly
                $pastVector = null;
                if (is_string($message->question_embedding)) {
                    $pastVector = json_decode($message->question_embedding, true);
                } elseif (is_array($message->question_embedding)) {
                    $pastVector = $message->question_embedding;
                }

                if (!is_array($pastVector) || empty($pastVector)) {
                    Log::warning("Invalid embedding for message ID: {$message->id}");
                    continue;
                }

                // ✅ Check embedding dimensions match
                if (count($pastVector) !== count($questionEmbedding)) {
                    Log::warning("Embedding dimension mismatch for message ID: {$message->id}");
                    continue;
                }

                $distance = $this->cosineDistance($questionEmbedding, $pastVector);

                Log::info("Distance: {$distance} for question: '{$message->question}'");

                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $closestAnswer = $message->answer;
                    $closestQuestion = $message->question;
                }
            }

            Log::info("Min distance: {$minDistance}, Closest question: '{$closestQuestion}'");

            // ✅ Lower threshold để test
            if ($minDistance < 0.15) { // Tạm thời tăng threshold để test
                Log::info("✅ Found similar question with distance: {$minDistance}");
                Log::info("Current: '{$currentQuestion}' vs Previous: '{$closestQuestion}'");
                return $closestAnswer;
            }

            Log::info("No similar question found (min distance: {$minDistance})");
            return null;
        } catch (\Exception $e) {
            Log::error("Error in checkSimilarQuestions: " . $e->getMessage());
            return null;
        }
    }



    private function saveMessagesWithEmbedding($session, $botId, $request, $answer, array $questionEmbedding): void
    {
        try {
            // ✅ Save to MySQL first (để có data cho lần check tiếp theo)
            Log::info("Trước log questionQdrantId");
            $chatMessage = ChatMessage::create([
                'chat_session_id' => $session->id,
                'bot_id' => $botId,
                'sender' => $request->getSender(),
                'question' => $request->getMessage(),
                'answer' => $answer,
                'embedding_id' => null,
                'question_embedding' => json_encode($questionEmbedding), // ✅ Ensure JSON encoding
                'question_qdrant_id' => null, // Sẽ update sau
            ]);

            // ✅ Save to Qdrant
            $questionsCollectionName = "questions_bot_{$botId}";
            $this->ensureQdrantCollection($questionsCollectionName);
            $questionQdrantId = $chatMessage->id; // Use MySQL ID as Qdrant ID
            Log::info("Sau log questionQdrantId");

            QdrantService::upsert($questionsCollectionName, [[
                'id' => $questionQdrantId,
                'vector' => $questionEmbedding,
                'payload' => [
                    'bot_id' => $botId,
                    'question' => $request->getMessage(),
                    'answer' => $answer,
                    'session_id' => $session->id,
                    'sender' => $request->getSender(),
                    'mysql_id' => $chatMessage->id,
                    'created_at' => now()->toISOString()
                ]
            ]]);

            // ✅ Update MySQL with Qdrant ID
            $chatMessage->update(['question_qdrant_id' => $questionQdrantId]);

            Log::info("✅ Saved Q&A with embedding - MySQL ID: {$chatMessage->id}, Qdrant ID: {$questionQdrantId}");
        } catch (\Exception $e) {
            Log::error("Failed to save message with embedding: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            // Fallback: Save without embedding
            $this->saveMessages($session, $botId, $request, $answer);
        }
    }

    // ✅ Generate next question ID
    private function getNextQuestionId(int $botId): int
    {
        try {
            $maxId = ChatMessage::where('bot_id', $botId)
                ->whereNotNull('question_qdrant_id')
                ->max('question_qdrant_id');

            return ($maxId ?? 0) + 1;
        } catch (\Exception $e) {
            return (int) (microtime(true) * 1000);
        }
    }

    // ✅ Ensure questions collection exists
    private function ensureQdrantCollection(string $collectionName): void
    {
        try {
            $collections = QdrantService::listCollections();
            $collectionExists = collect($collections['result']['collections'] ?? [])
                ->pluck('name')
                ->contains($collectionName);

            if (!$collectionExists) {
                QdrantService::createCollection($collectionName, 1536);
                Log::info("✅ Created Qdrant questions collection: {$collectionName}");
            }
        } catch (\Exception $e) {
            Log::warning("Failed to ensure questions collection {$collectionName}: " . $e->getMessage());
        }
    }

    // ✅ Cosine distance calculation (1 - cosine similarity)
    private function cosineDistance(array $a, array $b): float
    {
        $similarity = $this->cosineSimilarity($a, $b);
        return 1.0 - $similarity;
    }

    private function getRelevantContextWithScore(string $question, int $botId): array
    {
        try {
            // Generate embedding for question
            $queryEmbedding = EmbeddingService::generate($question);
            $collectionName = "bot_{$botId}";

            // Cache key
            $cacheKey = 'qdrant_search:' . md5($question . '_' . $botId);
            $searchResults = Cache::get($cacheKey);

            if (!$searchResults) {
                try {
                    // Search using Qdrant
                    $searchResults = QdrantService::search($collectionName, $queryEmbedding, 5, 0.3);
                    Cache::put($cacheKey, $searchResults, now()->addHour());
                } catch (\Exception $e) {
                    Log::error("Qdrant search failed: " . $e->getMessage());
                    // Fallback to old method if Qdrant fails
                    $searchResults = $this->fallbackEmbeddingSearch($queryEmbedding, $botId);
                }
            }

            $relatedChunks = [];
            $topScore = 0;

            foreach ($searchResults as $result) {
                $relatedChunks[] = $result['payload']['content'];
                if ($result['score'] > $topScore) {
                    $topScore = $result['score'];
                }
            }

            return [
                'context' => implode("\n---\n", $relatedChunks),
                'top_score' => $topScore,
                'results' => $searchResults
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get context from Qdrant: " . $e->getMessage());
            return [
                'context' => '',
                'top_score' => 0,
                'results' => []
            ];
        }
    }

    private function getRelevantContext(string $question, int $botId): string
    {
        $contextData = $this->getRelevantContextWithScore($question, $botId);
        return $contextData['context'];
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $aLength = 0.0;
        $bLength = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $aLength += $a[$i] ** 2;
            $bLength += $b[$i] ** 2;
        }

        return $aLength && $bLength ? $dotProduct / (sqrt($aLength) * sqrt($bLength)) : 0.0;
    }

    private function saveMessages($session, $botId, $request, $answer): void
    {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'bot_id' => $botId,
            'sender' => $request->getSender(),
            'question' => $request->getMessage(),
            'answer' => $answer,
            'embedding_id' => null,
        ]);
    }

    private function fallbackEmbeddingSearch(array $queryEmbedding, int $botId): array
    {
        $chunks = DocumentChunk::whereHas('document', function ($q) use ($botId) {
            $q->where('bot_id', $botId);
        })->get();

        $scored = $chunks->map(function ($chunk) use ($queryEmbedding) {
            // For fallback, we don't have embedding in MySQL anymore
            // So we'll return empty or generate on-the-fly
            return [
                'score' => 0,
                'payload' => ['content' => $chunk->content],
            ];
        })->sortByDesc('score')->take(5)->values()->all();

        return $scored;
    }
}
