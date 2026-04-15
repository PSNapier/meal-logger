<?php

namespace App\Services;

use App\Ai\SymptomsPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;

use function Laravel\Ai\agent;

class SymptomsLogExtractor
{
    /**
     * @return array{
     *     log_date:string,
     *     trend:string|null,
     *     fatigue:string|null,
     *     dizziness:string|null,
     *     max_pain:int|null,
     *     assistant_summary:string
     * }
     */
    public function extract(string $targetDateYmd, string $userContent, ?array $existingDay = null): array
    {
        $symptomAgent = agent(
            instructions: SymptomsPrompt::instructions($targetDateYmd),
            messages: [],
            tools: [],
            schema: fn (JsonSchema $schema) => [
                'log_date' => $schema->string()->format('date')->required(),
                'trend' => $schema->string()->nullable(),
                'fatigue' => $schema->string()->nullable(),
                'dizziness' => $schema->string()->nullable(),
                'max_pain' => $schema->integer()->min(0)->max(10)->nullable(),
                'assistant_summary' => $schema->string()->required(),
            ],
        );

        $model = config('ai.providers.openrouter.models.text.default');
        $userPrompt = $existingDay === null
            ? "Symptoms update:\n".$userContent
            : "Current symptoms day JSON:\n".json_encode($existingDay, JSON_THROW_ON_ERROR)."\n\nUser update:\n".$userContent;

        $response = $symptomAgent->prompt(
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
            'trend' => isset($data['trend']) ? (string) $data['trend'] : null,
            'fatigue' => isset($data['fatigue']) ? (string) $data['fatigue'] : null,
            'dizziness' => isset($data['dizziness']) ? (string) $data['dizziness'] : null,
            'max_pain' => isset($data['max_pain']) ? (int) $data['max_pain'] : null,
            'assistant_summary' => (string) $data['assistant_summary'],
        ];
    }
}
