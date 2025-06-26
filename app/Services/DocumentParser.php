<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIO;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\IOFactory as ExcelIOFactory;

class DocumentParser
{
    /**
     * Trích xuất văn bản từ file hoặc URL (pdf, docx, csv, html, url)
     */
    public static function extract(string $path, string $type): string
    {
        $fullPath = storage_path('app/' . $path);
        $text = '';

        switch (strtolower($type)) {
            case 'pdf':
                $text = (new PdfParser())->parseFile($fullPath)->getText();
                break;

            case 'excel':
                $text = self::extractTextFromExcel($fullPath);
                break;

            case 'docx':
                $phpWord = WordIO::load($fullPath);
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if ($element instanceof Text || $element instanceof TextRun) {
                            $text .= $element->getText() . "\n";
                        } elseif (method_exists($element, '__toString')) {
                            $text .= (string) $element . "\n";
                        }
                    }
                }
                break;

            case 'html':
                $html = file_get_contents($fullPath);
                $text = self::cleanHtml($html);
                break;

            case 'url':
                $html = (new Client())->get($path)->getBody()->getContents();
                $text = self::cleanHtml($html);
                break;

            default:
                throw new \Exception("Unsupported file type: $type");
        }

        return trim($text);
    }

    protected static function extractTextFromExcel(string $path): string
    {
        $text = '';
        $spreadsheet = ExcelIOFactory::load($path);
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            foreach ($worksheet->toArray() as $row) {
                $text .= implode(' ', $row) . "\n";
            }
        }
        return $text;
    }

    /**
     * Làm sạch HTML: loại bỏ script/style/meta/onClick... và chỉ giữ lại văn bản cần thiết
     */
    protected static function cleanHtml(string $html): string
    {
        // Xoá toàn bộ các thẻ script, style, meta...
        $html = preg_replace('/<(script|style|meta|noscript)[^>]*>.*?<\/\1>/si', '', $html);

        // Xoá các thuộc tính inline như onClick, style
        $html = preg_replace('/<(.*?)\s+(on\w+|style)="[^"]*"/i', '<$1', $html);

        // Xoá toàn bộ các thẻ HTML còn lại → giữ lại text
        $text = strip_tags($html);

        // Làm sạch khoảng trắng dư thừa
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
