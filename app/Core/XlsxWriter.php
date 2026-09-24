<?php
namespace App\Core;

use DateTime;
use DateTimeZone;
use RuntimeException;
use ZipArchive;

/**
 * XlsxWriter — a small, dependency-free Excel (.xlsx) writer.
 *
 * An .xlsx file is a zip of XML files. This writes only what the
 * committee's spreadsheets use — several sheets, a handful of fixed
 * styles, column widths, merged cells, a frozen header, filters and a
 * dropdown list — so no Composer package is needed on the server.
 *
 * Usage:
 *   $x = new XlsxWriter();
 *   $s = $x->addSheet('Master', ['widths' => [16, 20], 'freeze' => 1, 'filter' => true]);
 *   $x->row($s, [['Registration ID', 'header'], ['Submitted', 'header']]);
 *   $x->row($s, ['RSVP-0001', [XlsxWriter::date('2026-09-24 15:41'), 'datetime']]);
 *   $x->send('rsvp.xlsx');
 *
 * A cell is a plain value (text or number) or [value, styleName].
 */
class XlsxWriter
{
    /** Style name => index into cellXfs below. Keep the two in step. */
    private const STYLES = [
        'default' => 0, 'header' => 1, 'text' => 2, 'datetime' => 3, 'money' => 4,
        'title' => 5, 'note' => 6, 'int' => 7, 'textfmt' => 8, 'bold' => 9,
    ];

    private array $sheets = [];

    /**
     * @param array $opt widths (character widths per column), freeze (rows
     *   frozen at the top), filter (bool, on the first row), merges
     *   (['A1:F1', …]), dropdown (['H', ['Pending','Paid']]), rowHeights
     * @return int sheet index
     */
    public function addSheet(string $name, array $opt = []): int
    {
        // Excel refuses sheet names over 31 chars or with []:*?/\
        $name = mb_substr(preg_replace('#[\[\]:*?/\\\\]#u', ' ', $name), 0, 31);
        $this->sheets[] = ['name' => $name, 'opt' => $opt, 'rows' => []];
        return count($this->sheets) - 1;
    }

    /** Append one row. An empty array makes a blank row. */
    public function row(int $sheet, array $cells, ?float $height = null): void
    {
        $this->sheets[$sheet]['rows'][] = ['cells' => $cells, 'height' => $height];
    }

    /**
     * A database DATETIME (local time) as an Excel date serial number,
     * so Excel sorts and filters it as a date, not as text.
     */
    public static function date(?string $value): ?float
    {
        if (empty($value)) {
            return null;
        }
        $tz   = new DateTimeZone(date_default_timezone_get());
        $d    = new DateTime($value, $tz);
        $base = new DateTime('1899-12-30 00:00:00', $tz);
        return round(($d->getTimestamp() - $base->getTimestamp()) / 86400, 6);
    }

    /** Column number (1-based) to letters: 1 → A, 27 → AA. */
    public static function col(int $n): string
    {
        $s = '';
        for (; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26) . $s;
        }
        return $s;
    }

    /** Stream the workbook as a download and stop. */
    public function send(string $filename): void
    {
        $path = $this->build();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        @unlink($path);
        exit;
    }

    /** Write the .xlsx to a temporary file and return its path. */
    public function build(): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for Excel export.');
        }
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip  = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the Excel file.');
        }

        $n = count($this->sheets);
        $overrides = '';
        $sheetList = '';
        $rels      = '';
        $defined   = '';
        foreach ($this->sheets as $i => $sheet) {
            $id = $i + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetList .= '<sheet name="' . $this->esc($sheet['name']) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
            $rels      .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
            if (!empty($sheet['opt']['filter']) && $sheet['rows']) {
                $defined .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\''
                    . $this->esc(str_replace("'", "''", $sheet['name'])) . '\'!' . $this->filterRef($sheet, true) . '</definedName>';
            }
            $zip->addFromString("xl/worksheets/sheet{$id}.xml", $this->sheetXml($sheet, $i === 0));
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides . '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets>' . $sheetList . '</sheets>'
            . ($defined ? '<definedNames>' . $defined . '</definedNames>' : '')
            . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
            . '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->close();
        return $path;
    }

    // ------------------------------------------------------------------

    private function sheetXml(array $sheet, bool $first): string
    {
        $opt = $sheet['opt'];
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        // Printing: every column on one page width (fitToWidth only takes
        // effect when fitToPage is switched on here).
        $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';

        $freeze = (int) ($opt['freeze'] ?? 0);
        $xml .= '<sheetViews><sheetView workbookViewId="0"' . ($first ? ' tabSelected="1"' : '') . '>';
        if ($freeze > 0) {
            $xml .= '<pane ySplit="' . $freeze . '" topLeftCell="A' . ($freeze + 1) . '" activePane="bottomLeft" state="frozen"/>'
                  . '<selection pane="bottomLeft" activeCell="A' . ($freeze + 1) . '" sqref="A' . ($freeze + 1) . '"/>';
        }
        $xml .= '</sheetView></sheetViews>';

        if (!empty($opt['widths'])) {
            $xml .= '<cols>';
            foreach (array_values($opt['widths']) as $i => $w) {
                $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($sheet['rows'] as $r => $row) {
            $rn = $r + 1;
            $xml .= '<row r="' . $rn . '"' . ($row['height'] ? ' ht="' . (float) $row['height'] . '" customHeight="1"' : '') . '>';
            foreach (array_values($row['cells']) as $c => $cell) {
                [$value, $style] = is_array($cell) ? [$cell[0], $cell[1] ?? 'text'] : [$cell, 'text'];
                $ref = self::col($c + 1) . $rn;
                $s   = self::STYLES[$style] ?? 0;
                if ($value === null || $value === '') {
                    $xml .= '<c r="' . $ref . '" s="' . $s . '"/>';
                } elseif ((is_int($value) || is_float($value)) && $style !== 'textfmt') {
                    $xml .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $value . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">'
                          . $this->esc((string) $value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if (!empty($opt['filter']) && $sheet['rows']) {
            $xml .= '<autoFilter ref="' . $this->filterRef($sheet, false) . '"/>';
        }
        if (!empty($opt['merges'])) {
            $xml .= '<mergeCells count="' . count($opt['merges']) . '">';
            foreach ($opt['merges'] as $m) {
                $xml .= '<mergeCell ref="' . $this->esc($m) . '"/>';
            }
            $xml .= '</mergeCells>';
        }
        if (!empty($opt['dropdown'])) {
            [$column, $choices] = $opt['dropdown'];
            $list = '"' . implode(',', array_map(fn($c) => str_replace(['"', ','], '', $c), $choices)) . '"';
            $xml .= '<dataValidations count="1"><dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="'
                  . $column . '2:' . $column . '1000"><formula1>' . $this->esc($list) . '</formula1></dataValidation></dataValidations>';
        }
        $xml .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
              . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
        return $xml . '</worksheet>';
    }

    /** The data range the filter covers: header row down to the last row. */
    private function filterRef(array $sheet, bool $absolute): string
    {
        $cols = max(1, count($sheet['rows'][0]['cells'] ?? []));
        $last = max(2, count($sheet['rows']));
        $d = $absolute ? '$' : '';
        return "{$d}A{$d}1:{$d}" . self::col($cols) . "{$d}{$last}";
    }

    /**
     * Fonts, fills and formats copied from the committee's own sample
     * workbooks: Arial 10, bold 11 on #E8D8B5 headers, a bold 18 title,
     * dd/mm/yyyy hh:mm dates and "RM" #,##0.00 money.
     */
    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy\ hh:mm"/>'
            . '<numFmt numFmtId="165" formatCode="&quot;RM&quot;\ #,##0.00"/></numFmts>'
            . '<fonts count="5">'
            . '<font><sz val="10"/><color rgb="FF000000"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="Arial"/></font>'
            . '<font><b/><sz val="18"/><color rgb="FF000000"/><name val="Arial"/></font>'
            . '<font><sz val="10"/><color rgb="FF000000"/><name val="Arial"/></font>'
            . '<font><i/><sz val="11"/><color rgb="FF000000"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8D8B5"/><bgColor rgb="FFE8D8B5"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="10">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                                          // default
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // header
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'       // text
            . '<xf numFmtId="164" fontId="3" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>' // datetime
            . '<xf numFmtId="165" fontId="3" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>' // money
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // title
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                             // note
            . '<xf numFmtId="1" fontId="3" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'   // int
            . '<xf numFmtId="49" fontId="3" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'  // textfmt (@)
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'       // bold
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function esc(string $s): string
    {
        // XML 1.0 forbids most control characters; a stray one from a
        // pasted name would make Excel refuse to open the whole file.
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
