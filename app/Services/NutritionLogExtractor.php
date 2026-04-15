<?php

namespace App\Services;

use App\Ai\NutritionPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;

use function Laravel\Ai\agent;

class NutritionLogExtractor
{
    /**
     * @param  array{
     *     calories: int|null,
     *     water_oz: float|null,
     *     fiber_g: float|null,
     *     meal_items: list<array{id:int,description:string,food_item_id:int|null,quantity:float|null,unit:string|null}>
     * }|null  $existingDay
     * @param  list<array{food_item_id:int,name:string,unit:string,unit_dimension:string,confidence:float}>  $confirmedMatches
     * @return array{
     *     log_date: string,
     *     calories: int,
     *     water_oz: float,
     *     fiber_g: float,
     *     meal_items: list<array{
     *         description: string,
     *         food_item_id: int|null,
     *         quantity: float|null,
     *         unit: string|null,
     *     }>,
     *     unresolved_items: list<array{
     *         description: string,
     *         quantity: float|null,
     *         unit: string|null
     *     }>,
     *     assistant_summary: string
     * }
     */
    public function extract(string $targetDateYmd, string $userContent, array $confirmedMatches = [], ?array $existingDay = null): array
    {
        $mergeFromPriorLog = $existingDay !== null;

        $nutritionAgent = agent(
            instructions: NutritionPrompt::instructions($targetDateYmd, $mergeFromPriorLog, $confirmedMatches !== []),
            messages: [],
            tools: [],
            schema: fn (JsonSchema $schema) => [
                'log_date' => $schema->string()->format('date')->required(),
                'calories' => $schema->integer()->required(),
                'water_oz' => $schema->number()->required(),
                'fiber_g' => $schema->number()->required(),
                'meal_items' => $schema->array()
                    ->items(
                        $schema->object([
                            'description' => $schema->string()->required(),
                            'food_item_id' => $schema->integer()->nullable(),
                            'quantity' => $schema->number()->nullable(),
                            'unit' => $schema->string()->nullable(),
                        ])
                    )
                    ->min(0)
                    ->required(),
                'unresolved_items' => $schema->array()
                    ->items(
                        $schema->object([
                            'description' => $schema->string()->required(),
                            'quantity' => $schema->number()->nullable(),
                            'unit' => $schema->string()->nullable(),
                        ])
                    )
                    ->min(0)
                    ->required(),
                'assistant_summary' => $schema->string()->required(),
            ],
        );

        $model = config('ai.providers.openrouter.models.text.default');

        $userPrompt = '';
        if ($confirmedMatches !== []) {
            $userPrompt .= "Trusted food-library matches (JSON):\n".json_encode($confirmedMatches, JSON_THROW_ON_ERROR)."\n\n";
        }

        $userPrompt .= $mergeFromPriorLog
            ? "Current saved log (JSON):\n".json_encode($existingDay, JSON_THROW_ON_ERROR)
                ."\n\nUser update (merge into this day):\n".$userContent
            : "Food log:\n\n".$userContent;

        $response = $nutritionAgent->prompt(
            $userPrompt,
            provider: Lab::OpenRouter,
            model: $model,
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new \RuntimeException('Expected structured AI response.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->structured;

        return [
            'log_date' => (string) $data['log_date'],
            'calories' => (int) $data['calories'],
            'water_oz' => (float) $data['water_oz'],
            'fiber_g' => (float) $data['fiber_g'],
            'meal_items' => array_values($data['meal_items'] ?? []),
            'unresolved_items' => array_values($data['unresolved_items'] ?? []),
            'assistant_summary' => (string) ($data['assistant_summary'] ?? ''),
        ];
    }
}
