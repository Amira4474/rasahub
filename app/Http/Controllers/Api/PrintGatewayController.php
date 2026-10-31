<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrintJobStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Business;
use App\Models\PrintJob;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What the print gateway calls. Its key reads "acronym.branch.secret": the
 * business, the branch and the secret, so each request is answered inside
 * that business's own database and for that branch only.
 */
class PrintGatewayController extends Controller
{
    /**
     * A job taken but not confirmed within this long is offered again, in
     * case the gateway stopped part way.
     */
    private const RECLAIM_AFTER_SECONDS = 120;

    private const MAX_ATTEMPTS = 5;

    public function ping(Request $request): JsonResponse
    {
        return $this->asGateway($request, fn (Business $business, Branch $branch): JsonResponse => response()->json([
            'business' => $business->name,
            'branch' => $branch->name,
        ]));
    }

    /**
     * Hand the gateway the jobs waiting at its branch, oldest first, and
     * mark them as being printed.
     */
    public function claim(Request $request): JsonResponse
    {
        $limit = max(1, min(20, (int) $request->input('limit', 10)));

        return $this->asGateway($request, function (Business $business, Branch $branch) use ($limit): JsonResponse {
            $jobs = DB::connection((new PrintJob)->getConnectionName())->transaction(function () use ($branch, $limit) {
                $jobs = PrintJob::where('branch_id', $branch->id)
                    ->where(fn ($query) => $query
                        ->where('status', PrintJobStatus::Queued)
                        ->orWhere(fn ($query) => $query
                            ->where('status', PrintJobStatus::Printing)
                            ->where('claimed_at', '<', now()->subSeconds(self::RECLAIM_AFTER_SECONDS))
                            ->where('attempts', '<', self::MAX_ATTEMPTS)))
                    ->with('printer')
                    ->oldest('id')
                    ->limit($limit)
                    ->lockForUpdate()
                    ->get();

                foreach ($jobs as $job) {
                    $job->update(['status' => PrintJobStatus::Printing, 'claimed_at' => now(), 'attempts' => $job->attempts + 1]);
                }

                return $jobs;
            });

            return response()->json([
                'jobs' => $jobs->map(fn (PrintJob $job): array => [
                    'id' => $job->ulid,
                    'type' => $job->type,
                    'printer' => [
                        'name' => $job->printer->name,
                        'host' => $job->printer->host,
                        'port' => $job->printer->port,
                        'paper_width' => $job->printer->paper_width,
                    ],
                    'document' => $job->document,
                ])->values(),
            ]);
        });
    }

    public function printed(Request $request, string $job): JsonResponse
    {
        return $this->report($request, $job, PrintJobStatus::Printed, null);
    }

    public function failed(Request $request, string $job): JsonResponse
    {
        $request->validate(['error' => ['nullable', 'string', 'max:1000']]);

        return $this->report($request, $job, PrintJobStatus::Failed, $request->input('error') ?: 'The gateway could not print it.');
    }

    private function report(Request $request, string $ulid, PrintJobStatus $status, ?string $error): JsonResponse
    {
        return $this->asGateway($request, function (Business $business, Branch $branch) use ($ulid, $status, $error): JsonResponse {
            $job = PrintJob::where('branch_id', $branch->id)->where('ulid', $ulid)->first();

            if ($job === null) {
                return response()->json(['message' => 'No such print job at this branch.'], 404);
            }

            $job->update([
                'status' => $status,
                'error' => $error,
                'printed_at' => $status === PrintJobStatus::Printed ? now() : null,
            ]);

            return response()->json(['status' => $status->value]);
        });
    }

    /**
     * Check the gateway key and run the callback inside its business, for its
     * branch. Every failure gets the same answer.
     *
     * @param  Closure(Business, Branch): JsonResponse  $callback
     */
    private function asGateway(Request $request, Closure $callback): JsonResponse
    {
        $refused = response()->json(['message' => 'The gateway key is not valid.'], 401);
        $parts = explode('.', (string) $request->bearerToken(), 3);

        if (count($parts) !== 3) {
            return $refused;
        }

        [$acronym, $branchUlid, $secret] = $parts;

        $business = Business::where('acronym', strtoupper($acronym))->where('is_active', true)->first();

        if ($business === null || $business->accessProblem() !== null) {
            return $refused;
        }

        return $business->run(function () use ($business, $branchUlid, $secret, $callback, $refused): JsonResponse {
            $branch = Branch::where('ulid', $branchUlid)->where('is_active', true)->first();

            if ($branch === null || ! $branch->acceptsGatewaySecret($secret)) {
                return $refused;
            }

            $branch->forceFill(['gateway_seen_at' => now()])->saveQuietly();

            return $callback($business, $branch);
        });
    }
}
