<?php

namespace App\Services;

use GuzzleHttp\Client;

class QdrantService
{
    /**
     * Khởi tạo client Guzzle kết nối tới Qdrant
     */
    protected static function client(): Client
    {
        return new Client([
            'base_uri' => env('QDRANT_HOST', 'http://localhost:6333')
        ]);
    }

    /**
     * Tạo collection trong Qdrant
     */
    public static function createCollection(string $collectionName, int $vectorSize = 1536)
    {
        $client = self::client();

        $response = $client->put("/collections/{$collectionName}", [
            'json' => [
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => 'Cosine',
                ],
                'hnsw_config' => [
                    'full_scan_threshold' => 10 // Cho phép full scan nếu ít dữ liệu
                ]
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    /**
     * Insert hoặc cập nhật nhiều vector point vào collection
     */
    public static function upsert(string $collection, array $points): void
    {
        $client = self::client();

        $client->put("/collections/{$collection}/points", [
            'json' => ['points' => $points],
        ]);
    }

    /**
     * Tìm các vector gần nhất với vector truyền vào
     */
    public static function search(string $collection, array $vector, int $limit = 3): array
    {
        $client = self::client();

        $response = $client->post("/collections/{$collection}/points/search", [
            'json' => [
                'vector' => $vector,
                'top' => $limit,
                'with_payload' => true,   // Lấy cả nội dung kèm theo
                'with_vector' => false    // Không cần trả về lại vector
            ]
        ]);

        return json_decode($response->getBody(), true)['result'];
    }
}
