<?php
namespace App\Core;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * SheetReader — rows of text from a spreadsheet the committee already has.
 *
 *   SheetReader::file($tmpPath, 'centres.xlsx')  → [['No.', 'Centre Name', …], …]
 *   SheetReader::text("1\tPusat A\t…")           → rows pasted from Excel
 *
 * Reads the first worksheet of an .xlsx (an .xlsx is a zip of XML, so no
 * library is needed — the same idea as XlsxWriter), a .csv, or text
 * copied straight out of Excel (tab-separated). Every cell comes back as
 * a trimmed string; long numbers such as bank accounts keep every digit.
 */
final class SheetReader
{
    private const MAX_ROWS = 5000;
    private const NS_REL   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public static function file(string $path, string $name): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'xlsx' || $ext === 'xlsm') {
            return self::xlsx($path);
        }
        if ($ext === 'csv' || $ext === 'txt' || $ext === 'tsv') {
            return self::text((string) file_get_contents($path));
        }
        throw new RuntimeException('請上傳 Excel (.xlsx) 或 CSV 檔案。Please upload an Excel (.xlsx) or CSV file.');
    }

    /** Pasted text (tabs from Excel) or CSV. */
    public static function text(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);            // Excel's UTF-8 mark
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $sep   = str_contains($text, "\t") ? "\t" : ',';
        $rows  = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = array_map(static fn($c) => trim((string) $c), str_getcsv($line, $sep, '"', ''));
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }
        return $rows;
    }

    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('無法開啟這個 Excel 檔案。This Excel file could not be opened.');
        }
        try {
            $sheet = self::firstSheetPath($zip);
            $strings = self::sharedStrings($zip);
            $xml = self::xml($zip, $sheet);
            if ($xml === null) {
                throw new RuntimeException('Excel 檔案裡找不到工作表。No worksheet found in this Excel file.');
            }
            $rows = [];
            foreach ($xml->sheetData->row ?? [] as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $col = self::colIndex((string) $c['r']) ?? count($cells);
                    $cells[$col] = self::cellValue($c, $strings);
                }
                if (!$cells || implode('', $cells) === '') {
                    continue;
                }
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                $rows[] = array_replace($line, $cells);
                if (count($rows) >= self::MAX_ROWS) {
                    break;
                }
            }
            return $rows;
        } finally {
            $zip->close();
        }
    }

    private static function xml(ZipArchive $zip, string $name): ?SimpleXMLElement
    {
        // Size checked before unpacking: a tiny "zip bomb" cannot fill the memory.
        $st = $zip->statName($name);
        if ($st === false || $st['size'] > 30 * 1024 * 1024 || ($raw = $zip->getFromName($name)) === false) {
            return null;
        }
        $xml = @simplexml_load_string($raw, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        return $xml === false ? null : $xml;
    }

    /** xl/worksheets/sheet1.xml, or whatever the workbook lists first. */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $book = self::xml($zip, 'xl/workbook.xml');
        $rels = self::xml($zip, 'xl/_rels/workbook.xml.rels');
        if ($book && $rels && isset($book->sheets->sheet[0])) {
            $rid = (string) $book->sheets->sheet[0]->attributes(self::NS_REL)['id'];
            foreach ($rels->Relationship as $r) {
                if ((string) $r['Id'] === $rid) {
                    $target = ltrim((string) $r['Target'], '/');
                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = self::xml($zip, 'xl/sharedStrings.xml');
        $out = [];
        foreach ($xml->si ?? [] as $si) {
            $out[] = self::richText($si);
        }
        return $out;
    }

    /** Plain <t>, or the runs of a formatted cell joined up. */
    private static function richText(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $s = '';
        foreach ($node->r ?? [] as $r) {
            $s .= (string) $r->t;
        }
        return $s;
    }

    private static function cellValue(SimpleXMLElement $c, array $strings): string
    {
        $type = (string) $c['t'];
        if ($type === 's') {
            return trim($strings[(int) $c->v] ?? '');
        }
        if ($type === 'inlineStr') {
            return trim(self::richText($c->is));
        }
        $v = trim((string) $c->v);
        if ($type === '' || $type === 'n') {
            // 123456789012 stored as 1.23456789012E+11: write every digit out.
            if (is_numeric($v) && stripos($v, 'e') !== false && (float) $v == floor((float) $v)) {
                return number_format((float) $v, 0, '', '');
            }
        }
        return $v;
    }

    /** "C7" → 2 */
    private static function colIndex(string $ref): ?int
    {
        if (!preg_match('/^([A-Z]+)\d*$/', strtoupper($ref), $m)) {
            return null;
        }
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}
