<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public static function generate(string $text): array
    {
        $apiKey = env('OPENAI_API_KEY');
        $url = 'https://api.openai.com/v1/embeddings';
        $maxLength = 10000; // OpenAI giới hạn tối đa tokens (~25k ký tự)

        // Cắt ngắn input nếu quá dài
        if (mb_strlen($text) > $maxLength) {
            Log::warning("Embedding input trimmed to {$maxLength} characters.");
            $text = mb_substr($text, 0, $maxLength);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
                ->timeout(30)           // giới hạn tối đa 20 giây
                ->retry(3, 1500)        // thử lại 3 lần, delay 1.5s nếu lỗi
                ->post($url, [
                    'input' => $text,
                    'model' => 'text-embedding-3-small',
                ]);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->body();
                Log::error("❌ Embedding failed: $status - $body");
                throw new \Exception("Embedding failed: $status - $body");
            }

            return $response->json('data.0.embedding');
        } catch (\Throwable $e) {
            Log::error("🚨 Exception in EmbeddingService: " . $e->getMessage());
            throw new \Exception("Embedding failed: " . $e->getMessage());
        }
    }
}
