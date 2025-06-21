<?php

namespace App\Services;

class TextChunker
{
    /**
     * Chia văn bản dài thành các đoạn nhỏ, mỗi đoạn tối đa $maxWords từ
     */
    public static function chunk(string $text, int $maxWords = 200): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text); // Tách theo câu
        $chunks = [];
        $currentChunk = '';
        $wordCount = 0;

        foreach ($sentences as $sentence) {
            $wordsInSentence = str_word_count($sentence);

            // Nếu 1 câu quá dài, tách thành chunk riêng
            if ($wordsInSentence > $maxWords) {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                    $wordCount = 0;
                }
                $chunks[] = trim($sentence);
                continue;
            }

            // Nếu thêm câu này vượt quá giới hạn → lưu chunk hiện tại lại
            if ($wordCount + $wordsInSentence > $maxWords) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence . ' ';
                $wordCount = $wordsInSentence;
            } else {
                $currentChunk .= $sentence . ' ';
                $wordCount += $wordsInSentence;
            }
        }

        // Lưu đoạn cuối nếu còn nội dung
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Tách 1 đoạn dài thành nhiều phần ngắn hơn để tránh lỗi giới hạn độ dài (token hoặc ký tự)
     */
    public static function splitChunkSafely(string $chunk, int $maxLength = 8000): array
    {
        // Nếu đủ ngắn thì trả về luôn
        if (strlen($chunk) <= $maxLength) return [$chunk];

        // Cắt đoạn theo độ dài ký tự, chừa 500 ký tự để tránh vượt ngưỡng giới hạn token
        $parts = str_split($chunk, $maxLength - 500);

        return array_map('trim', $parts);
    }
}
