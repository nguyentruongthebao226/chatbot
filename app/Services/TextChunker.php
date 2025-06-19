<?php

namespace App\Services;

class TextChunker
{
    public static function chunk(string $text, int $maxWords = 200): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $chunks = [];
        $currentChunk = '';
        $wordCount = 0;

        foreach ($sentences as $sentence) {
            $wordsInSentence = str_word_count($sentence);

            // Nếu câu quá dài 1 mình đã vượt maxWords → tách riêng luôn
            if ($wordsInSentence > $maxWords) {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                    $wordCount = 0;
                }
                $chunks[] = trim($sentence);
                continue;
            }

            if ($wordCount + $wordsInSentence > $maxWords) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence . ' ';
                $wordCount = $wordsInSentence;
            } else {
                $currentChunk .= $sentence . ' ';
                $wordCount += $wordsInSentence;
            }
        }

        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    public static function splitChunkSafely(string $chunk, int $maxLength = 8000): array
    {
        if (strlen($chunk) <= $maxLength) return [$chunk];

        // Nếu quá dài thì tách ra từng đoạn nhỏ theo khoảng ký tự
        $parts = str_split($chunk, $maxLength - 500); // chừa khoảng trống an toàn
        return array_map('trim', $parts);
    }

}
