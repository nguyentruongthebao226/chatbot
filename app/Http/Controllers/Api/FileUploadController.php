<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingDocument;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'Không có file được gửi lên'], 400);
        }

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('uploads', $fileName);

        $document = TrainingDocument::create([
            'name' => $fileName,
            'type' => $file->getClientOriginalExtension(),
            'path' => $filePath
        ]);

        return response()->json([
            'message' => 'Upload thành công',
            'document_id' => $document->id,
            'path' => $filePath
        ]);
    }
}
