<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrainingDocument;
use App\Models\DocumentChunk;
use App\Services\DocumentParser;
use App\Services\QdrantService;
use App\Services\TextChunker;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TrainController extends Controller
{
    public function train(Request $request)
    {
        $botId = $request->input('bot_id', 1); // hoặc lấy từ authenticated user
        $type = $request->input('type');
        $name = $request->input('name');
        $collectionName = "bot_{$botId}";

        try {
            // Kiểm tra duplicate
            $existing = TrainingDocument::where('name', $name)->where('bot_id', $botId)->first();
            if ($existing) {
                // Nếu cho phép replace thì xóa tài liệu cũ
                if ($request->input('replace')) {
                    $this->deleteExistingDocument($existing, $collectionName);
                } else {
                    return response()->json([
                        'message' => 'Tên tài liệu đã tồn tại',
                        'exists' => true,
                        'status' => 209
                    ]);
                }
            }

            // Parse nội dung file
            if ($request->hasFile('content')) {
                $file = $request->file('content');
                $tempPath = $file->store('temp');
                $content = DocumentParser::extract($tempPath, $type);
                Storage::delete($tempPath);
            } else if ($type === 'website') {
                $url = $request->input('url');
                $content = DocumentParser::extract($url, 'url');
            } else if ($request->filled('content_data')) {
                $content = $request->input('content_data');
            } else {
                return response()->json(['message' => 'Thiếu dữ liệu hoặc file.'], 400);
            }

            // Tạo bản ghi TrainingDocument
            $doc = TrainingDocument::create([
                'bot_id' => $botId,
                'type' => $type,
                'name' => $name,
                'content' => $content,
            ]);

            // Đảm bảo Qdrant collection tồn tại
            $this->ensureQdrantCollection($collectionName);

            // Xử lý chunk và lưu vào Qdrant/MySQL
            $this->processAndSaveChunks($doc, $content, $collectionName);

            return response()->json([
                'message' => 'Đã train thành công',
                'id' => $doc->id,
                'collection' => $collectionName,
                'chunks_count' => $doc->chunks()->count()
            ]);
        } catch (\Throwable $e) {
            Log::error('Train failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function ensureQdrantCollection($collectionName)
    {
        $collections = QdrantService::listCollections();
        $collectionExists = collect($collections['result']['collections'] ?? [])
            ->pluck('name')
            ->contains($collectionName);

        if (!$collectionExists) {
            QdrantService::createCollection($collectionName, 1536);
            Log::info("Tạo Qdrant collection mới: {$collectionName}");
        }
    }

    private function processAndSaveChunks($doc, $content, $collectionName)
    {
        $chunks = TextChunker::chunk($content, 300);
        $qdrantPoints = [];
        $dbChunks = [];

        DB::beginTransaction();

        try {
            $startingId = $this->getNextQdrantId($collectionName);

            foreach ($chunks as $index => $chunk) {
                $cleanChunk = $this->sanitizeUtf8($chunk);
                if (empty(trim($cleanChunk))) continue;

                $embedding = EmbeddingService::generate($cleanChunk);
                $qdrantId = $startingId + $index;

                $qdrantPoints[] = [
                    'id' => $qdrantId,
                    'vector' => $embedding,
                    'payload' => [
                        'document_id' => $doc->id,
                        'bot_id' => $doc->bot_id,
                        'content' => $cleanChunk,
                        'document_name' => $doc->name,
                        'document_type' => $doc->type,
                        'chunk_index' => $index,
                        'created_at' => now()->toISOString()
                    ]
                ];

                $dbChunks[] = [
                    'document_id' => $doc->id,
                    'content' => $cleanChunk,
                    'qdrant_id' => $qdrantId,
                    'vector_collection' => $collectionName,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            if (empty($qdrantPoints)) {
                throw new \Exception("Không có chunk hợp lệ để train.");
            }

            DocumentChunk::insert($dbChunks);
            QdrantService::upsert($collectionName, $qdrantPoints);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function getNextQdrantId($collectionName)
    {
        $maxId = DocumentChunk::where('vector_collection', $collectionName)
            ->whereNotNull('qdrant_id')
            ->max('qdrant_id');
        return ($maxId ?? 0) + 1;
    }

    private function deleteExistingDocument($doc, $collectionName)
    {
        $chunks = $doc->chunks()->get();
        $qdrantIds = $chunks->pluck('qdrant_id')->filter()->values()->all();

        if (!empty($qdrantIds)) {
            QdrantService::deletePoints($collectionName, $qdrantIds);
        }
        DocumentChunk::where('document_id', $doc->id)->delete();
        $doc->delete();
    }

    private function sanitizeUtf8($text)
    {
        if (empty($text)) return '';
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, SJIS, EUC-JP, ASCII');
        }
        $text = iconv(mb_detect_encoding($text, mb_detect_order(), true), "UTF-8//IGNORE", $text);
        return trim($text);
    }
}
