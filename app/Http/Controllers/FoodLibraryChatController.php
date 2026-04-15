<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFoodLibraryChatRequest;
use App\Models\FoodItem;
use App\Services\FoodLibraryExtractor;
use App\Services\FoodLibraryMatcher;
use App\Services\FoodLibraryUpserter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class FoodLibraryChatController extends Controller
{
    public function __invoke(
        StoreFoodLibraryChatRequest $request,
        FoodLibraryExtractor $extractor,
        FoodLibraryUpserter $upserter,
    ): RedirectResponse {
        $user = $request->user();
        $message = $request->validated('message');

        try {
            $result = $extractor->extract($message);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'message' => 'Could not process food library request right now.',
            ]);
        }

        foreach ($result['actions'] as $action) {
            $type = strtolower((string) ($action['type'] ?? ''));
            if ($type === 'upsert' && is_array($action['food_item'] ?? null)) {
                $upserter->upsert($user, $action['food_item'], 'ai_chat');
                continue;
            }

            if ($type === 'delete') {
                $targetName = FoodLibraryMatcher::normalizeName((string) ($action['target_name'] ?? ''));
                if ($targetName === '') {
                    continue;
                }

                FoodItem::query()
                    ->where('user_id', $user->id)
                    ->where('normalized_name', $targetName)
                    ->delete();
            }
        }

        Log::info('Food library chat mutation completed.', [
            'user_id' => $user->id,
            'action_count' => count($result['actions']),
        ]);

        return back()->with('foodLibraryAssistant', $result['assistant_summary']);
    }
}
