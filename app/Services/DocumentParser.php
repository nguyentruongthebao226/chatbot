<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIO;
use GuzzleHttp\Client;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;

class DocumentParser
{
    public static function extract(string $path, string $type): string
    {
        $fullPath = storage_path('app/' . $path);
        $text = '';

        switch (strtolower($type)) {
            case 'pdf':
                $parser = new PdfParser();
                $pdf = $parser->parseFile($fullPath);
                $text = $pdf->getText();
                break;

            case 'csv':
                $handle = fopen($fullPath, 'r');
                while (($row = fgetcsv($handle)) !== false) {
                    $text .= implode(' ', $row) . "\n";
                }
                fclose($handle);
                break;

            case 'docx':
                $phpWord = WordIO::load($fullPath);
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if ($element instanceof Text || $element instanceof TextRun) {
                            $text .= $element->getText() . "\n";
                        } elseif (method_exists($element, '__toString')) {
                            $text .= (string)$element . "\n";
                        }
                    }
                }
                break;
            case 'html':
                $html = file_get_contents($fullPath);
                $text = strip_tags($html);
                break;

            case 'url':
                $client = new Client();
                $response = $client->get($path);
                $html = $response->getBody()->getContents();
                $text = strip_tags($html);
                break;

            default:
                throw new \Exception("Unsupported file type: $type");
        }

        return trim($text);
    }
}
