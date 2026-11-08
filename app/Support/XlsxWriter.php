<?php

namespace App\Support;

/**
 * Гадны сан, PHP-ийн zip өргөтгөл шаардахгүй энгийн XLSX (Excel) бичигч.
 * Нэг хуудастай, урьдчилан тодорхойлсон хэв маягуудтай (STYLE_*): толгой мөр, огноо, мөнгөн дүн, нийт дүн.
 * Файлыг шахалтгүй (stored) zip болгон угсарна.
 */
class XlsxWriter
{
    public const STYLE_DEFAULT = 0;
    public const STYLE_TITLE = 1;
    public const STYLE_SUBTITLE = 2;
    public const STYLE_HEADER = 3;
    public const STYLE_TEXT = 4;
    public const STYLE_DATE = 5;
    public const STYLE_MONEY = 6;
    public const STYLE_INT = 7;
    public const STYLE_TOTAL_LABEL = 8;
    public const STYLE_TOTAL_MONEY = 9;
    public const STYLE_CENTER = 10;

    private array $cols = [];
    private array $rows = [];
    private array $merges = [];
    private ?int $freezeRow = null;
    private ?string $autoFilter = null;
    private ?array $printTitleRows = null;

    public function __construct(private string $sheetName = 'Sheet1') {}

    /** Баганын өргөн (тэмдэгтээр) */
    public function columns(array $widths): static
    {
        $this->cols = array_values($widths);

        return $this;
    }

    /**
     * Мөр нэмнэ. Нүд бүр: скаляр утга, эсвэл ['v' => утга, 's' => хэв маяг, 'f' => томьёо].
     * $styles: баганын индексээр хэв маяг (нүд өөрийн 's'-гүй үед).
     */
    public function row(array $cells, array|int $styles = self::STYLE_DEFAULT, ?float $height = null): int
    {
        $this->rows[] = [array_values($cells), $styles, $height];

        return count($this->rows);
    }

    /** Одоогийн сүүлийн мөрийн дугаар */
    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function merge(string $ref): static
    {
        $this->merges[] = $ref;

        return $this;
    }

    /** $row хүртэлх мөрүүдийг гүйлгэхэд дээр тогтооно */
    public function freezeBelow(int $row): static
    {
        $this->freezeRow = $row;

        return $this;
    }

    public function autoFilter(string $ref): static
    {
        $this->autoFilter = $ref;

        return $this;
    }

    /** Хэвлэхэд хуудас бүрийн дээр давтагдах мөрүүд */
    public function printTitles(int $from, int $to): static
    {
        $this->printTitleRows = [$from, $to];

        return $this;
    }

    public static function col(int $index): string
    {
        $s = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26).$s;
        }

        return $s;
    }

    /** PHP огноог Excel-ийн огнооны тоо болгоно */
    public static function date(?\DateTimeInterface $d): ?int
    {
        // Эх объектыг өөрчлөхгүйн тулд зөвхөн огноогоор нь UTC-д шинээр үүсгэнэ
        return $d ? intdiv((new \DateTimeImmutable($d->format('Y-m-d'), new \DateTimeZone('UTC')))->getTimestamp(), 86400) + 25569 : null;
    }

    public function toString(): string
    {
        return self::book([$this]);
    }

    /** Олон хуудастай ажлын ном (хуудас бүр нь XlsxWriter) */
    public static function book(array $sheets): string
    {
        $sheets = array_values($sheets);
        $rels = '';
        $files = [];
        foreach ($sheets as $i => $sheet) {
            $n = $i + 1;
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $files["xl/worksheets/sheet$n.xml"] = $sheet->sheet($i === 0);
        }
        $rels .= '<Relationship Id="rId'.(count($sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return self::zip([
            '[Content_Types].xml' => self::contentTypes(count($sheets)),
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => self::workbook($sheets),
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>',
            'xl/styles.xml' => self::styles(),
        ] + $files);
    }

    // ───────────────────────────── XML ─────────────────────────────

    private static function esc(string $s): string
    {
        // XML-д зөвшөөрөгдөөгүй удирдах тэмдэгтүүдийг хасна
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(int $sheets): string
    {
        $over = '';
        for ($n = 1; $n <= $sheets; $n++) {
            $over .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$over
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function safeName(): string
    {
        return self::esc(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $this->sheetName), 0, 31));
    }

    private static function workbook(array $sheets): string
    {
        $list = $defined = '';
        foreach ($sheets as $i => $sheet) {
            $name = $sheet->safeName();
            $quoted = "'".str_replace("'", "''", $name)."'";
            $list .= '<sheet name="'.$name.'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
            if ($sheet->autoFilter) {
                $abs = preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $sheet->autoFilter);
                $defined .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">'.$quoted.'!'.$abs.'</definedName>';
            }
            if ($sheet->printTitleRows) {
                $defined .= '<definedName name="_xlnm.Print_Titles" localSheetId="'.$i.'">'.$quoted.'!$'.$sheet->printTitleRows[0].':$'.$sheet->printTitleRows[1].'</definedName>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView activeTab="0"/></bookViews>'
            .'<sheets>'.$list.'</sheets>'
            .($defined ? '<definedNames>'.$defined.'</definedNames>' : '')
            .'<calcPr calcId="191029" fullCalcOnLoad="1"/>'
            .'</workbook>';
    }

    private static function styles(): string
    {
        $border = '<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right>'
            .'<top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>';
        $xf = fn (int $numFmt, int $font, int $fill, int $border, string $align = '') => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '').($font ? ' applyFont="1"' : '').($fill ? ' applyFill="1"' : '').($border ? ' applyBorder="1"' : '')
            .($align ? ' applyAlignment="1"><alignment '.$align.'/></xf>' : '/>');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="#,##0"/></numFmts>'
            .'<fonts count="5">'
            .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF1F3864"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF595959"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDDEBF7"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'.$border.'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="11">'
            .$xf(0, 0, 0, 0)                                                       // 0 default
            .$xf(0, 1, 0, 0, 'vertical="center"')                                 // 1 title
            .$xf(0, 2, 0, 0, 'vertical="center"')                                 // 2 subtitle
            .$xf(0, 3, 2, 1, 'horizontal="center" vertical="center" wrapText="1"') // 3 header
            .$xf(0, 0, 0, 1, 'vertical="center"')                                 // 4 text
            .$xf(164, 0, 0, 1, 'horizontal="center" vertical="center"')           // 5 date
            .$xf(165, 0, 0, 1, 'vertical="center"')                               // 6 money
            .$xf(1, 0, 0, 1, 'horizontal="center" vertical="center"')             // 7 integer
            .$xf(0, 4, 3, 1, 'horizontal="right" vertical="center"')              // 8 total label
            .$xf(165, 4, 3, 1, 'vertical="center"')                               // 9 total money
            .$xf(0, 0, 0, 1, 'horizontal="center" vertical="center"')             // 10 center text
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function sheet(bool $selected = true): string
    {
        $maxCol = max(count($this->cols), ...array_map(fn ($r) => count($r[0]), $this->rows ?: [[[]]]));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.self::col(max(0, $maxCol - 1)).max(1, count($this->rows)).'"/>'
            .'<sheetViews><sheetView workbookViewId="0"'.($selected ? ' tabSelected="1"' : '').'>'
            .($this->freezeRow ? '<pane ySplit="'.$this->freezeRow.'" topLeftCell="A'.($this->freezeRow + 1).'" activePane="bottomLeft" state="frozen"/>'
                .'<selection pane="bottomLeft" activeCell="A'.($this->freezeRow + 1).'" sqref="A'.($this->freezeRow + 1).'"/>' : '')
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>';

        if ($this->cols) {
            $xml .= '<cols>';
            foreach ($this->cols as $i => $w) {
                $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($this->rows as $r => [$cells, $styles, $height]) {
            $n = $r + 1;
            $xml .= '<row r="'.$n.'"'.($height ? ' ht="'.$height.'" customHeight="1"' : '').'>';
            foreach ($cells as $c => $cell) {
                $cell = is_array($cell) ? $cell : ['v' => $cell];
                $s = $cell['s'] ?? (is_array($styles) ? ($styles[$c] ?? self::STYLE_TEXT) : $styles);
                $ref = self::col($c).$n;
                $v = $cell['v'] ?? null;
                if (isset($cell['f'])) {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'"><f>'.self::esc($cell['f']).'</f>'.($v !== null ? '<v>'.$v.'</v>' : '').'</c>';
                } elseif ($v === null || $v === '') {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'"/>';
                } elseif (is_int($v) || is_float($v)) {
                    $xml .= '<c r="'.$ref.'" s="'.$s.'"><v>'.$v.'</v></c>';
                } else {
                    // Текстийг inlineStr-ээр: «=» -ээр эхэлсэн утга томьёо болж ажиллахгүй
                    $xml .= '<c r="'.$ref.'" s="'.$s.'" t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $v).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($this->autoFilter) {
            $xml .= '<autoFilter ref="'.$this->autoFilter.'"/>';
        }
        if ($this->merges) {
            $xml .= '<mergeCells count="'.count($this->merges).'">'.implode('', array_map(fn ($m) => '<mergeCell ref="'.$m.'"/>', $this->merges)).'</mergeCells>';
        }

        return $xml.'<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    /** Шахалтгүй (stored) zip архив */
    private static function zip(array $files): string
    {
        $data = $central = '';
        $time = 0; // 00:00:00
        $date = ((date('Y') - 1980) << 9) | (date('n') << 5) | date('j');
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $len = strlen($content);
            $header = pack('vvvvvVVVvv', 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0);
            $central .= pack('V', 0x02014b50).pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, strlen($data)).$name;
            $data .= pack('V', 0x04034b50).$header.$name.$content;
        }

        return $data.$central.pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($data), 0);
    }
}
