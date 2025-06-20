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
                    'distance' => 'Cosine'
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
}
