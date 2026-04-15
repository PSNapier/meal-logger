<?php

namespace App\Services;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;

use function Laravel\Ai\agent;

class FoodLibraryExtractor
{
    /**
     * @return array{assistant_summary:string,actions:list<array{type:string,food_item:array<string,mixed>|null,target_name:string|null}>}
     */
    public function extract(string $message): array
    {
        $libraryAgent = agent(
            instructions: <<<TXT
You manage a user's personal food library.
Return only structured actions:
- type "upsert" with full per-unit nutrition fields in food_item when adding/updating entries.
- type "delete" with target_name when removing an entry.
Only output nutrition values when the user clearly asks to create/update.
TXT,
            messages: [],
            tools: [],
            schema: fn (JsonSchema $schema) => [
                'assistant_summary' => $schema->string()->required(),
                'actions' => $schema->array()->items(
                    $schema->object([
                        'type' => $schema->string()->required(),
                        'food_item' => $schema->object([
                            'name' => $schema->string(),
                            'unit' => $schema->string(),
                            'unit_dimension' => $schema->string(),
                            'unit_quantity' => $schema->number(),
                            'calories_per_unit' => $schema->integer(),
                            'protein_g_per_unit' => $schema->number(),
                            'carbs_g_per_unit' => $schema->number(),
                            'fat_g_per_unit' => $schema->number(),
                            'sugar_g_per_unit' => $schema->number(),
                            'fiber_g_per_unit' => $schema->number(),
                            'water_oz_per_unit' => $schema->number(),
                        ])->nullable(),
                        'target_name' => $schema->string()->nullable(),
                    ])
                )->required(),
            ],
        );

        $response = $libraryAgent->prompt(
            $message,
            provider: Lab::OpenRouter,
            model: config('ai.providers.openrouter.models.text.default'),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new \RuntimeException('Expected structured AI response.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->structured;

        return [
            'assistant_summary' => (string) ($data['assistant_summary'] ?? ''),
            'actions' => array_values($data['actions'] ?? []),
        ];
    }
}

