<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

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

    public static function listCollections(): array
    {
        $client = self::client();
        $response = $client->get("/collections");
        return json_decode($response->getBody(), true);
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

        $response = $client->put("/collections/{$collection}/points", [
            'json' => ['points' => $points],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Failed to upsert points: " . $response->getBody());
        }

        Log::info("✅ Upserted " . count($points) . " points to {$collection}");
    }

    /**
     * Tìm các vector gần nhất với vector truyền vào
     */
    public static function search(string $collection, array $vector, int $limit = 5, float $scoreThreshold = 0.0): array
    {
        $client = self::client();

        $response = $client->post("/collections/{$collection}/points/search", [
            'json' => [
                'vector' => $vector,
                'top' => $limit,
                'with_payload' => true,
                'with_vector' => false,
                'score_threshold' => $scoreThreshold
            ]
        ]);

        return json_decode($response->getBody(), true)['result'] ?? [];
    }

    public static function deletePoints(string $collection, array $pointIds): void
    {
        $client = self::client();

        $response = $client->post("/collections/{$collection}/points/delete", [
            'json' => [
                'points' => $pointIds
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Failed to delete points: " . $response->getBody());
        }
    }

    public static function deleteCollection(string $collectionName): void
    {
        $client = self::client();

        $response = $client->delete("/collections/{$collectionName}");

        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Failed to delete collection {$collectionName}: " . $response->getBody());
        }
    }
}
