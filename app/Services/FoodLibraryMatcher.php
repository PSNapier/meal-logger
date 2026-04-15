<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\User;

class FoodLibraryMatcher
{
    /**
     * @return list<array{name:string,food_item:FoodItem|null,confidence:float}>
     */
    public function match(User $user, string $content): array
    {
        $candidates = $this->extractCandidateNames($content);
        if ($candidates === []) {
            return [];
        }

        $library = FoodItem::query()
            ->where('user_id', $user->id)
            ->select(['id', 'user_id', 'name', 'normalized_name', 'unit', 'unit_dimension'])
            ->get();

        $results = [];
        foreach ($candidates as $candidate) {
            $normalized = self::normalizeName($candidate);
            $bestItem = null;
            $bestScore = 0.0;

            foreach ($library as $item) {
                $score = $this->similarity($normalized, (string) $item->normalized_name);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestItem = $item;
                }
            }

            $results[] = [
                'name' => $candidate,
                'food_item' => $bestItem,
                'confidence' => round($bestScore, 3),
            ];
        }

        return $results;
    }

    public static function normalizeName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return preg_replace('/[^a-z0-9 ]+/', '', $normalized) ?? $normalized;
    }

    /**
     * @return list<string>
     */
    private function extractCandidateNames(string $content): array
    {
        $parts = preg_split('/[\n,;]+/', $content) ?: [];
        $candidates = [];

        foreach ($parts as $part) {
            $text = trim($part);
            if ($text === '') {
                continue;
            }

            // Remove obvious quantity prefixes to improve name matching.
            $text = preg_replace('/^\d+(\.\d+)?\s*(oz|g|gram|grams|lb|lbs|cup|cups)\b/i', '', $text) ?? $text;
            $text = trim($text, "- \t\n\r\0\x0B");
            if ($text === '') {
                continue;
            }

            $candidates[] = $text;
        }

        return array_values(array_unique($candidates));
    }

    private function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}

