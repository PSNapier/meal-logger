<?php

namespace App\Ai;

final class NutritionPrompt
{
    public static function instructions(string $targetDate, bool $mergeFromPriorLog, bool $hasConfirmedMatches): string
    {
        $mergeBlock = $mergeFromPriorLog ? <<<'TXT'

UPDATE mode (current log JSON was sent with the user message):
- You receive a snapshot of what is already saved for this day (meal_items include stable `id` values).
- The user's text is an incremental change, not necessarily a full recount: merge it into that snapshot.
- Add new foods/drinks as new meal_items rows. Adjust totals when they correct portions or add items.
- If they remove, delete, skip, or "didn't have" something, omit that line from meal_items (match by `id` when they reference a numbered item or paste the id; otherwise match by description).
- If they replace an item (e.g. "actually it was diet soda"), update that row conceptually: remove the old line and output the corrected line (same logical meal count unless they add more).
- If the message is a full new day recap and clearly replaces everything, you may rebuild the whole list from their text alone.
- Output the COMPLETE day after applying their message: every meal_items row that should remain for the day, with fresh estimates if anything changed.
- calories, water_oz, and fiber_g must reflect the full updated day (coherent with meal_items and the hydration rules below).

TXT
            : <<<'TXT'

FIRST entry mode (no prior snapshot, or empty day):
- Build the day only from the user's food log text.

TXT;

        $matchBlock = $hasConfirmedMatches ? <<<'TXT'

Food library match mode:
- Some entries may be marked as trusted matches (by food_item_id).
- For trusted matches, do not research nutrition and do not output macro estimates yourself.
- Return the matched food_item_id plus consumed quantity and unit.
- If you cannot confidently map a line item to a trusted id, put it in unresolved_items.

TXT
            : <<<'TXT'

Food library mode:
- If there is no trusted match for an item, place it in unresolved_items.
- Do not invent nutrition values for unresolved items.

TXT;

        return <<<TXT
You are a nutrition tracker. Estimate calories, macros, sugar, fiber, and hydration from the user's food log for ONE calendar day.

Target date (Y-m-d) for this extraction — you MUST set log_date to exactly: {$targetDate}
{$mergeBlock}
{$matchBlock}
For each food or drink line item that should exist for the day after your update, produce one meal_items row with realistic estimates.

Guidelines:
- Only count water, sparkling water, coffee, and tea toward per-item water_oz and the day total water_oz.
- Do NOT count sodas (including diet/zero), fruit or vegetable juice, or smoothies toward water ounces.
- Use reasonable real-world estimates (restaurant data if applicable).
- If portions are unclear, make a best guess (no long prose in structured fields).
- If the user names a restaurant or brand, use typical nutrition data for that item.

Structured output rules:
- log_date must be "{$targetDate}".
- meal_items: include only trusted food-library-backed rows with fields: description, food_item_id, quantity, unit.
- unresolved_items: include rows that could not be mapped safely, each with description, quantity, and unit.
- assistant_summary must mention unresolved items clearly so user can add/fix them in My Foods.
- calories, water_oz, and fiber_g should be estimated from meal_items only (trusted rows only).
- assistant_summary: markdown for the user ONLY in this layout (no tables, no other headings):
  # Daily Totals
  Calories: <n> kcal
  Water: <n> oz
  Fiber: <n> g

  # Notes
  - bullet(s): brief nutrition commentary (e.g. hydration, sugar/protein balance). Mention adds/removes when relevant.
  Use thousands separators for calories when ≥ 1000. Title case section headings exactly: "Daily Totals" and "Notes". Blank line before "# Notes". No emoji.
TXT;
    }
}
