<?php

use App\Models\Document;
use App\Services\DocumentParser;
use App\Services\Embedder;
use App\Services\QdrantService;
use App\Services\TextChunker;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-read/{id}', function ($id) {
    $document = Document::find($id);
    if (!$document) {
        return 'Không tìm thấy document';
    }

    $content = DocumentParser::extract($document->path, $document->type);

    dd($content); // Kiểm tra nội dung đọc được
});

Route::get('/test-url', function (\Illuminate\Http\Request $request) {
    $url = $request->query('url');

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return response('URL không hợp lệ', 400);
    }

    try {
        $content = DocumentParser::extract($url, 'url');
        return response('<pre>' . e(substr($content, 0, 3000)) . '</pre>');
    } catch (\Exception $e) {
        return response('Lỗi: ' . $e->getMessage(), 500);
    }
});

Route::get('/test-chunk/{id}', function ($id) {
    $document = Document::find($id);
    if (!$document) return 'Không tìm thấy document';

    $text = DocumentParser::extract($document->path, $document->type);
    $chunks = TextChunker::chunk($text, 200); // chia mỗi đoạn ~200 từ

    return response()->json([
        'chunk_count' => count($chunks),
        'preview' => array_slice($chunks, 0, 3), // chỉ preview 3 đoạn đầu
    ]);
});

Route::get('/test-chunk-url', function (Request $request) {
    $url = $request->query('url');
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return response('URL không hợp lệ', 400);
    }

    try {
        $text = DocumentParser::extract($url, 'url');
        $chunks = TextChunker::chunk($text, 200);

        return response()->json([
            'chunk_count' => count($chunks),
            'preview' => array_slice($chunks, 0, 3)
        ]);
    } catch (\Exception $e) {
        return response('Lỗi khi xử lý: ' . $e->getMessage(), 500);
    }
});

Route::get('/test-embed', function () {
    $text = "Xin chào, đây là nội dung cần nhúng.";
    $vector = Embedder::embed($text);

    return response()->json([
        'dimensions' => count($vector),
        'preview' => array_slice($vector, 0, 5),
    ]);
});

Route::get('/train/{id}', function ($id) {
    $document = Document::find($id);
    if (!$document) {
        return response('Không tìm thấy tài liệu', 404);
    }

    $text = DocumentParser::extract($document->path, $document->type);
    $chunks = TextChunker::chunk($text, 200);

    foreach ($chunks as $index => $chunk) {
        $embedding = Embedder::embed($chunk);

        $payload = [
            'document_id' => $document->id,
            'chunk_index' => $index,
            'text' => $chunk,
        ];

       $point = [
            'id' => $document->id * 1000 + $index,
            'vector' => $embedding,
            'payload' => $payload
        ];

        QdrantService::upsert('doc_chunks', [$point]);
    }

    return response("Train thành công " . count($chunks) . " đoạn.");
});

Route::get('/train-url', function (Request $request) {
    $url = $request->query('url');

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return response('URL không hợp lệ', 400);
    }

    try {
        $text = DocumentParser::extract($url, 'url');
        $chunks = TextChunker::chunk($text, 200);

        foreach ($chunks as $index => $chunk) {
            $subChunks = TextChunker::splitChunkSafely($chunk);

            foreach ($subChunks as $subIndex => $subChunk) {
                $embedding = Embedder::embed($subChunk);

                $payload = [
                    'source_type' => 'url',
                    'source_url' => $url,
                    'chunk_index' => "$index-$subIndex",
                    'text' => $subChunk
                ];

                $point = [
                    'id' => crc32($url . "-$index-$subIndex"),
                    'vector' => $embedding,
                    'payload' => $payload
                ];

                QdrantService::upsert('doc_chunks', [$point]);
            }
        }

        return response("Train URL thành công với " . count($chunks) . " đoạn.");
    } catch (\Exception $e) {
        return response('Lỗi: ' . $e->getMessage(), 500);
    }
});
