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

    public static function upsert(string $collection, array $points): void
    {
        $client = self::client();

        $response = $client->put("/collections/$collection/points", [
            'json' => ['points' => $points],
        ]);
    }
}
