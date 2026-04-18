<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ImportUserDataRequest;
use App\Services\UserDataPorter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataController extends Controller
{
    public function __construct(
        private readonly UserDataPorter $porter,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('settings/Data');
    }

    public function download(Request $request): StreamedResponse
    {
        $user = $request->user();
        $payload = $this->porter->export($user);

        $filename = sprintf(
            'meal-logger-export-%d-%s.json',
            $user->id,
            now()->toDateString(),
        );

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function preview(ImportUserDataRequest $request): JsonResponse
    {
        try {
            $contents = $request->file('file')->getContent();
            $decoded = $this->porter->decodeUploadedJson($contents, $request->file('file')->getClientOriginalName());
            $summary = $this->porter->summarize($request->user(), $decoded);
            $totals = $this->conflictTotals($summary);

            return response()->json([
                'summary' => $summary,
                'has_conflicts' => $totals['conflicting'] > 0,
                'totals' => $totals,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(ImportUserDataRequest $request): JsonResponse
    {
        try {
            $contents = $request->file('file')->getContent();
            $decoded = $this->porter->decodeUploadedJson($contents, $request->file('file')->getClientOriginalName());
            $mode = (string) $request->validated('mode');

            $this->porter->import($request->user(), $decoded, $mode);

            return response()->json([
                'message' => $mode === UserDataPorter::MODE_MERGE
                    ? __('Data imported (merge).')
                    : __('Data imported (overwrite).'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param  array<string, array<string, int>>  $summary
     * @return array{new: int, identical: int, conflicting: int, blank: int, updates: int}
     */
    private function conflictTotals(array $summary): array
    {
        $totals = [
            'new' => 0,
            'identical' => 0,
            'conflicting' => 0,
            'blank' => 0,
            'updates' => 0,
        ];

        foreach ($summary as $bucket) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $bucket[$k] ?? 0;
            }
        }

        return $totals;
    }
}
