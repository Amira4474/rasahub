<?php

namespace App\Http\Controllers;

use App\Reports\Export\XlsxWriter;
use App\Reports\ReportFilters;
use App\Reports\Reports;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A report as an Excel or PDF file, with the same figures as on screen.
 */
class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $report, string $format, Reports $reports): Response
    {
        $user = $request->user();

        abort_if($user === null || ! $user->canAccessPanel(filament()->getPanel('admin')), 403);
        abort_unless(array_key_exists($report, Reports::available($user)), 403);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $business = app(TenantContext::class)->business();
        $filters = ReportFilters::for($user, $request->query('from'), $request->query('to'), $request->integer('branch') ?: null);
        $table = $reports->run($report, $filters);

        $name = Str::slug($business->acronym.' '.$table->title.' '.$filters->from->toDateString().' '.$filters->to->toDateString());

        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', [
                'table' => $table,
                'subtitle' => $filters->describe(),
                'business' => $business->name,
                'currency' => $business->currency,
            ])->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')->download("{$name}.pdf");
        }

        $path = app(XlsxWriter::class)->write($table, $business->name.' · '.$filters->describe(), $business->currency);

        return response()->download($path, "{$name}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
