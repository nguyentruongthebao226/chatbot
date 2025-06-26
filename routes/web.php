<?php

use App\Models\Document;
use App\Models\TrainingDocument;
use App\Services\DocumentParser;
use App\Services\Embedder;
use App\Services\QdrantService;
use App\Services\TextChunker;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

Route::get('/', fn() => view('welcome'));

// Kiểm tra PHP info
Route::get('/test-phpinfo', fn() => phpinfo());

/**
 * ==========================
 * 📄 Test đọc nội dung tài liệu
 * ==========================
 */

// Đọc file theo ID từ DB
Route::get('/test-read/{id}', function ($id) {
    $document = TrainingDocument::find($id);
    if (!$document) return 'Không tìm thấy document';

    $content = DocumentParser::extract($document->path, $document->type);
    dd($content);
});

// Đọc nội dung từ URL (html)
Route::get('/test-url', function (Request $request) {
    $url = $request->query('url');
    if (!filter_var($url, FILTER_VALIDATE_URL)) return response('URL không hợp lệ', 400);

    try {
        $content = DocumentParser::extract($url, 'url');
        return response('<pre>' . e(substr($content, 0, 3000)) . '</pre>');
    } catch (\Exception $e) {
        return response('Lỗi: ' . $e->getMessage(), 500);
    }
});

/**
 * ==========================
 * ✂️ Chia đoạn văn bản (chunk)
 * ==========================
 */

// Chunk nội dung tài liệu trong DB
Route::get('/test-chunk/{id}', function ($id) {
    $document = TrainingDocument::find($id);
    if (!$document) return 'Không tìm thấy document';

    $text = DocumentParser::extract($document->path, $document->type);
    $chunks = TextChunker::chunk($text, 200);

    return response()->json([
        'chunk_count' => count($chunks),
        'preview' => array_slice($chunks, 0, 3)
    ]);
});

// Chunk nội dung từ URL
Route::get('/test-chunk-url', function (Request $request) {
    $url = $request->query('url');
    if (!filter_var($url, FILTER_VALIDATE_URL)) return response('URL không hợp lệ', 400);

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

/**
 * ==========================
 * 🧠 Test tạo embedding
 * ==========================
 */
Route::get('/test-embed', function () {
    $text = "Xin chào, đây là nội dung cần nhúng.";
    $vector = Embedder::embed($text);

    return response()->json([
        'dimensions' => count($vector),
        'preview' => array_slice($vector, 0, 5)
    ]);
});

/**
 * ==========================
 * 🏗️ Tạo collection và huấn luyện (train)
 * ==========================
 */

// Tạo collection mới trong Qdrant
Route::get('/create-collection', fn() => response()->json(QdrantService::createCollection('doc_chunks')));

// Train dữ liệu từ document trong DB
Route::get('/train/{id}', function ($id) {
    $document = TrainingDocument::find($id);
    if (!$document) return response('Không tìm thấy tài liệu', 404);

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
        logger('Đã insert vào Qdrant:', $point);
    }

    return response("Train thành công " . count($chunks) . " đoạn.");
});

// Train từ URL
Route::get('/train-url', function (Request $request) {
    $url = $request->query('url');
    if (!filter_var($url, FILTER_VALIDATE_URL)) return response('URL không hợp lệ', 400);

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

/**
 * ==========================
 * 🔍 Debug & Reindex
 * ==========================
 */

// Gửi yêu cầu reindex
Route::get('/reindex', function () {
    $response = Http::post('http://localhost:6333/collections/doc_chunks/segments/recreate_index');
    return $response->json();
});

// Debug vector theo ID
Route::get('/debug-vectors/{id}', function ($id) {
    $pointId = $id * 1000;
    $response = Http::get("http://localhost:6333/collections/doc_chunks/points/$pointId");
    return $response->json();
});
