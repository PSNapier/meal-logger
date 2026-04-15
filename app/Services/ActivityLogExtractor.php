<?php

namespace App\Services;

use App\Ai\ActivityPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;

use function Laravel\Ai\agent;

class ActivityLogExtractor
{
    /**
     * @return array{
     *     log_date:string,
     *     total_sessions:int,
     *     total_minutes:int,
     *     calories_burned:int,
     *     assistant_summary:string
     * }
     */
    public function extract(string $targetDateYmd, string $userContent, ?array $existingDay = null): array
    {
        $activityAgent = agent(
            instructions: ActivityPrompt::instructions($targetDateYmd),
            messages: [],
            tools: [],
            schema: fn (JsonSchema $schema) => [
                'log_date' => $schema->string()->format('date')->required(),
                'total_sessions' => $schema->integer()->min(0)->required(),
                'total_minutes' => $schema->integer()->min(0)->required(),
                'calories_burned' => $schema->integer()->min(0)->required(),
                'assistant_summary' => $schema->string()->required(),
            ],
        );

        $model = config('ai.providers.openrouter.models.text.default');
        $userPrompt = $existingDay === null
            ? "Activity update:\n".$userContent
            : "Current activity day JSON:\n".json_encode($existingDay, JSON_THROW_ON_ERROR)."\n\nUser update:\n".$userContent;

        $response = $activityAgent->prompt(
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
            'total_sessions' => (int) $data['total_sessions'],
            'total_minutes' => (int) $data['total_minutes'],
            'calories_burned' => (int) $data['calories_burned'],
            'assistant_summary' => (string) $data['assistant_summary'],
        ];
    }
}
