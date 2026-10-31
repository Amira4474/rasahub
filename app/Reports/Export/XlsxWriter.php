<?php

namespace App\Reports\Export;

use App\Reports\ReportTable;
use RuntimeException;
use ZipArchive;

/**
 * Writes a report as an Excel workbook (.xlsx). An .xlsx file is a zip of
 * XML files; this writes the few that one formatted sheet needs, so no
 * spreadsheet library is required. Amounts are real numbers with two
 * decimals, so Excel can add them up.
 */
class XlsxWriter
{
    // Cell styles, by their index in styles.xml.
    private const TEXT = 0;

    private const BOLD = 1;

    private const MONEY = 2;

    private const MONEY_BOLD = 3;

    private const WHOLE = 4;

    private const WHOLE_BOLD = 5;

    private const PERCENT = 6;

    public function write(ReportTable $table, string $subtitle, string $currency): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report').'.xlsx';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The Excel file could not be made.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $table->title), 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($table, $subtitle, $currency));
        $zip->close();

        return $path;
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="1" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function sheet(ReportTable $table, string $subtitle, string $currency): string
    {
        $rows = [];
        $rows[] = [[$table->title, self::BOLD]];
        $rows[] = [[$subtitle, self::TEXT]];
        $rows[] = [];
        $rows[] = array_map(fn (array $column): array => [$this->heading($column, $currency), self::BOLD], $table->columns);

        foreach ($table->rows as $row) {
            $rows[] = array_map(fn (array $column): array => $this->cell($row[$column['key']] ?? '', $column['type'], false), $table->columns);
        }

        if ($table->totals !== null) {
            $rows[] = array_map(fn (array $column): array => $this->cell($table->totals[$column['key']] ?? '', $column['type'], true), $table->columns);
        }

        if ($table->note) {
            $rows[] = [];
            $rows[] = [[$table->note, self::TEXT]];
        }

        $xml = '';

        foreach ($rows as $index => $cells) {
            $number = $index + 1;
            $xml .= "<row r=\"{$number}\">";

            foreach ($cells as $col => [$value, $style]) {
                $ref = $this->columnLetter($col).$number;

                if (is_int($value) || is_float($value)) {
                    $xml .= "<c r=\"{$ref}\" s=\"{$style}\"><v>{$value}</v></c>";
                } elseif ($value !== '') {
                    $xml .= "<c r=\"{$ref}\" s=\"{$style}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">".$this->escape((string) $value).'</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        $widths = '';

        foreach ($table->columns as $col => $column) {
            $width = in_array($column['type'], ['money', 'int', 'percent'], true) ? 15 : 26;
            $widths .= '<col min="'.($col + 1).'" max="'.($col + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$widths.'</cols>'
            .'<sheetData>'.$xml.'</sheetData>'
            .'</worksheet>';
    }

    private function heading(array $column, string $currency): string
    {
        return match ($column['type']) {
            'money' => "{$column['label']} ({$currency})",
            'percent' => "{$column['label']} (%)",
            default => $column['label'],
        };
    }

    /**
     * @return array{0: int|float|string, 1: int}
     */
    private function cell(mixed $value, string $type, bool $total): array
    {
        if ($value === '' || $value === null) {
            return ['', self::TEXT];
        }

        return match (true) {
            $type === 'money' && is_numeric($value) => [(float) $value, $total ? self::MONEY_BOLD : self::MONEY],
            $type === 'int' && is_numeric($value) => [(int) $value, $total ? self::WHOLE_BOLD : self::WHOLE],
            $type === 'percent' && is_numeric($value) => [(float) $value, self::PERCENT],
            default => [(string) $value, $total ? self::BOLD : self::TEXT],
        };
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letters = chr(65 + ($index - 1) % 26).$letters;
        }

        return $letters;
    }

    private function escape(string $value): string
    {
        // Characters XML 1.0 does not allow are dropped.
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
