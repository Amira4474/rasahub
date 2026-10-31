<?php

namespace App\Reports;

/**
 * A report's result: a title, its columns, its rows and its totals. The
 * admin page, the Excel file and the PDF are all drawn from it, so they
 * always agree.
 */
class ReportTable
{
    /**
     * @param  list<array{key: string, label: string, type: string}>  $columns  type: text, int, money, percent, datetime
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     * @param  array{type: string, labels: list<string>, series: array<string, list<float>>}|null  $chart
     * @param  list<array{label: string, value: string}>  $highlights  Figures shown above the table.
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
        public ?array $totals = null,
        public ?array $chart = null,
        public array $highlights = [],
        public ?string $note = null,
    ) {}

    public static function column(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }
}
