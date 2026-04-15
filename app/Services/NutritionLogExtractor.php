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
     *     items: list<array{
     *         id:int,
     *         description:string,
     *         calories:int,
     *         protein_g:float,
     *         carbs_g:float,
     *         fat_g:float,
     *         sugar_g:float,
     *         fiber_g:float,
     *         water_oz:float
     *     }>
     * }|null  $existingDay
     * @return array{
     *     log_date: string,
     *     calories: int,
     *     water_oz: float,
     *     fiber_g: float,
     *     items: list<array{
     *         description: string,
     *         calories: int,
     *         protein_g: float,
     *         carbs_g: float,
     *         fat_g: float,
     *         sugar_g: float,
     *         fiber_g: float,
     *         water_oz: float
     *     }>,
     *     assistant_summary: string
     * }
     */
    public function extract(string $targetDateYmd, string $userContent, ?array $existingDay = null): array
    {
        $mergeFromPriorLog = $existingDay !== null;

        $nutritionAgent = agent(
            instructions: NutritionPrompt::instructions($targetDateYmd, $mergeFromPriorLog),
            messages: [],
            tools: [],
            schema: fn (JsonSchema $schema) => [
                'log_date' => $schema->string()->format('date')->required(),
                'calories' => $schema->integer()->required(),
                'water_oz' => $schema->number()->required(),
                'fiber_g' => $schema->number()->required(),
                'items' => $schema->array()
                    ->items(
                        $schema->object([
                            'description' => $schema->string()->required(),
                            'calories' => $schema->integer()->required(),
                            'protein_g' => $schema->number()->required(),
                            'carbs_g' => $schema->number()->required(),
                            'fat_g' => $schema->number()->required(),
                            'sugar_g' => $schema->number()->required(),
                            'fiber_g' => $schema->number()->required(),
                            'water_oz' => $schema->number()->required(),
                        ])
                    )
                    ->min(0)
                    ->required(),
                'assistant_summary' => $schema->string()->required(),
            ],
        );

        $model = config('ai.providers.openrouter.models.text.default');

        $userPrompt = $mergeFromPriorLog
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
            'items' => array_values($data['items'] ?? []),
            'assistant_summary' => (string) ($data['assistant_summary'] ?? ''),
        ];
    }
}
