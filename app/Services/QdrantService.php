<?php

namespace App\Services;

use GuzzleHttp\Client;

class QdrantService
{
    protected static function client(): Client
    {
        return new Client([
            'base_uri' => env('QDRANT_HOST', 'http://localhost:6333')
        ]);
    }

    public static function createCollection(string $collectionName, int $vectorSize = 1536)
    {
        $client = self::client();

        $response = $client->put("/collections/$collectionName", [
            'json' => [
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => 'Cosine',
                ],
                'hnsw_config' => [
                    'full_scan_threshold' => 10
                ]
            ]
        ]);

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        return response()->json($data);
    }

    public static function upsert(string $collection, array $points): void
    {
        $client = self::client();

        $client->put("/collections/$collection/points", [
            'json' => ['points' => $points],
        ]);
    }

    public static function search(string $collection, array $vector, int $limit = 3): array
    {
        $client = self::client();

        $response = $client->post("/collections/$collection/points/search", [
            'json' => [
                'vector' => $vector,
                'top' => $limit,
                'with_payload' => true,
                'with_vector' => false
            ]
        ]);

        return json_decode($response->getBody(), true)['result'];
    }
}
