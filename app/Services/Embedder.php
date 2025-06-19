<?php

namespace App\Services;

use GuzzleHttp\Client;

class Embedder
{
    public static function embed(string $text): array
    {
        $client = new Client();
        $apiKey = env('OPENAI_API_KEY');

        $response = $client->post('https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => "Bearer $apiKey",
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'input' => $text,
                'model' => 'text-embedding-3-small',
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        return $data['data'][0]['embedding'] ?? [];
    }
}
