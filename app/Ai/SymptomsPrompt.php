<?php

namespace App\Ai;

final class SymptomsPrompt
{
    public static function instructions(string $targetDate): string
    {
        return <<<TXT
You are a symptoms tracking assistant for one calendar day.
Target date (Y-m-d): {$targetDate}

Extract only:
- trend: better|same|worse
- fatigue: good|baseline|bad
- dizziness: none|low|high
- max_pain: integer 0-10 (or null if not present)

Rules:
- log_date must equal "{$targetDate}" exactly.
- Never output values outside allowed enums/range.
- Keep assistant_summary concise and supportive.
TXT;
    }
}
