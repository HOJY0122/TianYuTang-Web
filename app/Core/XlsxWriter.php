<?php
namespace App\Core;

use DateTime;
use DateTimeZone;

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
    /**
     * Named cell styles: name => [numFmtId, fontId, fillId, borderId, alignment].
     * The index of each entry is its style number in the file, so the
     * list is only ever appended to. Fonts, fills and borders are the
     * tables in stylesXml(). "_z" variants are the shaded (zebra) rows.
     */
    private const XF = [
        'default'    => [0,   0, 0, 0, ''],
        'header'     => [0,   1, 2, 1, '<alignment horizontal="center" vertical="center" wrapText="1"/>'],
        'text'       => [0,   0, 0, 1, '<alignment vertical="center"/>'],
        'datetime'   => [164, 0, 0, 1, '<alignment horizontal="left" vertical="center"/>'],
        'money'      => [165, 0, 0, 1, '<alignment vertical="center"/>'],
        'title'      => [0,   2, 0, 0, '<alignment horizontal="left" vertical="center"/>'],
        'note'       => [0,   3, 4, 0, '<alignment vertical="center" wrapText="1"/>'],
        'int'        => [1,   0, 0, 1, '<alignment horizontal="center" vertical="center"/>'],
        'textfmt'    => [49,  0, 0, 1, '<alignment vertical="center"/>'],
        'bold'       => [0,   1, 0, 1, '<alignment vertical="center"/>'],
        'text_z'     => [0,   0, 3, 1, '<alignment vertical="center"/>'],
        'datetime_z' => [164, 0, 3, 1, '<alignment horizontal="left" vertical="center"/>'],
        'money_z'    => [165, 0, 3, 1, '<alignment vertical="center"/>'],
        'int_z'      => [1,   0, 3, 1, '<alignment horizontal="center" vertical="center"/>'],
        'textfmt_z'  => [49,  0, 3, 1, '<alignment vertical="center"/>'],
        'blank'      => [0,   0, 0, 0, ''],
        'kpi'        => [1,   4, 0, 1, '<alignment horizontal="center" vertical="center"/>'],
        'kpimoney'   => [165, 4, 0, 1, '<alignment horizontal="right" vertical="center"/>'],
        'subtitle'   => [0,   5, 0, 0, '<alignment horizontal="left" vertical="center"/>'],
        'status'     => [0,   1, 0, 1, '<alignment horizontal="center" vertical="center"/>'],
        'status_z'   => [0,   1, 3, 1, '<alignment horizontal="center" vertical="center"/>'],
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
        $bytes = $this->build();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
        exit;
    }

    /**
     * The finished .xlsx file, as bytes.
     *
     * Zipped by our own small writer (see zip() below) and built in
     * memory, on purpose: shared hosting often lacks the PHP zip
     * extension, or restricts the temp folder — and either one made the
     * Excel buttons fail on the live server while working locally.
     */
    public function build(): string
    {
        $files = [];
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
            $quoted = '\'' . $this->esc(str_replace("'", "''", $sheet['name'])) . '\'';
            if (!empty($sheet['opt']['freeze']) && !empty($sheet['opt']['filter'])) {
                $defined .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . $i . '">' . $quoted . '!$1:$' . (int) $sheet['opt']['freeze'] . '</definedName>';
            }
            if (!empty($sheet['opt']['filter']) && $sheet['rows']) {
                $defined .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\''
                    . $this->esc(str_replace("'", "''", $sheet['name'])) . '\'!' . $this->filterRef($sheet, true) . '</definedName>';
            }
        }

        $files['[Content_Types].xml'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
        $files['_rels/.rels'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $files['docProps/core.xml'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>' . $this->esc($this->creator) . '</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
        $files['docProps/app.xml'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Microsoft Excel</Application></Properties>';
        $files['xl/workbook.xml'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets>' . $sheetList . '</sheets>'
            . ($defined ? '<definedNames>' . $defined . '</definedNames>' : '')
            . '</workbook>';
        $files['xl/_rels/workbook.xml.rels'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
            . '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
        $files['xl/styles.xml'] = $this->stylesXml();
        foreach ($this->sheets as $i => $sheet) {
            $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->sheetXml($sheet, $i === 0);
        }
        return self::zip($files);
    }

    /** Shown as the file's author in Excel's File → Info. */
    public string $creator = 'Tian Yu Tang';

    /**
     * A minimal zip archive writer: [name => contents] in, zip bytes out.
     *
     * The format is simple — each file is a small header plus its
     * (deflated) data, and a directory at the end lists where each one
     * starts. Deflate comes from zlib, which PHP almost always has; if it
     * is missing too, files are stored uncompressed, which Excel accepts.
     */
    public static function zip(array $files): string
    {
        $data = '';
        $dir  = '';
        $t    = getdate();
        $dosTime = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $dosDate = (max(0, $t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];

        foreach ($files as $name => $content) {
            $crc    = crc32($content);
            $packed = function_exists('gzdeflate') ? gzdeflate($content, 6) : false;
            $method = $packed === false ? 0 : 8;
            if ($packed === false) {
                $packed = $content;
            }
            // version 20, flag 0x0800 = file names are UTF-8
            $common = pack('vvvvvVVVv', 20, 0x0800, $method, $dosTime, $dosDate, $crc,
                strlen($packed), strlen($content), strlen($name));
            $offset = strlen($data);
            $data  .= "PK\x03\x04" . $common . "\0\0" . $name . $packed;
            $dir   .= "PK\x01\x02" . pack('v', 20) . $common . pack('vvvvVV', 0, 0, 0, 0, 0, $offset) . $name;
        }
        return $data . $dir . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files),
            strlen($dir), strlen($data), 0);
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
        $xml .= '<sheetViews><sheetView workbookViewId="0"' . ($first ? ' tabSelected="1"' : '')
              . (!empty($opt['nogrid']) ? ' showGridLines="0"' : '') . '>';
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
            // Shade every other data row below the frozen header, so a
            // long list is easy to follow across the page.
            $zebra = !empty($opt['zebra']) && $rn > $freeze && ($rn - $freeze) % 2 === 0;
            foreach (array_values($row['cells']) as $c => $cell) {
                [$value, $style] = is_array($cell) ? [$cell[0], $cell[1] ?? 'text'] : [$cell, 'text'];
                if ($zebra && isset(self::XF[$style . '_z'])) {
                    $style .= '_z';
                }
                $ref = self::col($c + 1) . $rn;
                $s   = array_search($style, array_keys(self::XF), true);
                $s   = $s === false ? 0 : $s;
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
        // A4 landscape; the header row repeats (print titles are set in
        // the workbook) and every page is footed "Page X of Y".
        $xml .= '<pageMargins left="0.5" right="0.5" top="0.7" bottom="0.7" header="0.3" footer="0.3"/>'
              . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
              . '<headerFooter><oddHeader>' . $this->esc('&L&"Arial,Bold"' . str_replace('&', '&&', $this->creator) . '&R&A') . '</oddHeader>'
              . '<oddFooter>' . $this->esc('&L&D &T&RPage &P / &N') . '</oddFooter></headerFooter>';
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
     * Fonts, fills and formats follow the committee's own sample
     * workbooks — Arial 10, bold headers on #E8D8B5, "RM" #,##0.00 money,
     * dd/mm/yyyy hh:mm dates — plus thin borders, shaded alternate rows
     * and the site's red for the title, so a printout reads like a form.
     */
    private function stylesXml(): string
    {
        $xfs = '';
        foreach (self::XF as [$fmt, $font, $fill, $border, $align]) {
            $xfs .= '<xf numFmtId="' . $fmt . '" fontId="' . $font . '" fillId="' . $fill . '" borderId="' . $border . '" xfId="0"'
                  . ($fmt ? ' applyNumberFormat="1"' : '') . ' applyFont="1"' . ($fill ? ' applyFill="1"' : '')
                  . ($border ? ' applyBorder="1"' : '') . ($align ? ' applyAlignment="1">' . $align . '</xf>' : '/>');
        }
        $line = '<left style="thin"><color rgb="FFC9B48A"/></left><right style="thin"><color rgb="FFC9B48A"/></right>'
              . '<top style="thin"><color rgb="FFC9B48A"/></top><bottom style="thin"><color rgb="FFC9B48A"/></bottom><diagonal/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy\ hh:mm"/>'
            . '<numFmt numFmtId="165" formatCode="&quot;RM&quot;\ #,##0.00"/></numFmts>'
            . '<fonts count="6">'
            . '<font><sz val="10"/><color rgb="FF000000"/><name val="Arial"/><family val="2"/></font>'            // 0 body
            . '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="Arial"/><family val="2"/></font>'        // 1 header / bold
            . '<font><b/><sz val="18"/><color rgb="FF9F211B"/><name val="Arial"/><family val="2"/></font>'        // 2 title (site red)
            . '<font><i/><sz val="10"/><color rgb="FF5C4A36"/><name val="Arial"/><family val="2"/></font>'        // 3 note
            . '<font><b/><sz val="12"/><color rgb="FF9F211B"/><name val="Arial"/><family val="2"/></font>'        // 4 KPI number
            . '<font><sz val="10"/><color rgb="FF6B5B4B"/><name val="Arial"/><family val="2"/></font>'            // 5 subtitle
            . '</fonts>'
            . '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8D8B5"/><bgColor indexed="64"/></patternFill></fill>'  // 2 header
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFBF6EC"/><bgColor indexed="64"/></patternFill></fill>'  // 3 zebra
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF6DC"/><bgColor indexed="64"/></patternFill></fill>'  // 4 note
            . '</fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>' . $line . '</border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count(self::XF) . '">' . $xfs . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
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
