<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertFoodItemRequest;
use App\Models\FoodItem;
use App\Services\FoodLibraryUpserter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyFoodsController extends Controller
{
    public function index(Request $request): Response
    {
        $foodItems = FoodItem::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get()
            ->map(fn (FoodItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'unit_dimension' => $item->unit_dimension,
                'unit_quantity' => (float) $item->unit_quantity,
                'calories_per_unit' => (int) $item->calories_per_unit,
                'protein_g_per_unit' => (float) $item->protein_g_per_unit,
                'carbs_g_per_unit' => (float) $item->carbs_g_per_unit,
                'fat_g_per_unit' => (float) $item->fat_g_per_unit,
                'sugar_g_per_unit' => (float) $item->sugar_g_per_unit,
                'fiber_g_per_unit' => (float) $item->fiber_g_per_unit,
                'water_oz_per_unit' => (float) $item->water_oz_per_unit,
                'source' => $item->source,
            ])
            ->values()
            ->all();

        return Inertia::render('MyFoods', [
            'food_items' => $foodItems,
        ]);
    }

    public function store(
        UpsertFoodItemRequest $request,
        FoodLibraryUpserter $upserter,
    ): RedirectResponse {
        $upserter->upsert($request->user(), $request->validated(), 'user_manual');

        return back();
    }

    public function update(
        UpsertFoodItemRequest $request,
        FoodItem $foodItem,
        FoodLibraryUpserter $upserter,
    ): RedirectResponse {
        abort_unless((int) $foodItem->user_id === (int) $request->user()->id, 403);

        $upserter->upsert($request->user(), $request->validated(), 'user_manual', $foodItem);

        return back();
    }

    public function destroy(Request $request, FoodItem $foodItem): RedirectResponse
    {
        abort_unless((int) $foodItem->user_id === (int) $request->user()->id, 403);
        $foodItem->delete();

        return back();
    }
}
