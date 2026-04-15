---
name: audit-plan
description: Audits plans, specs, and guidelines for flaws, simplification opportunities, edge cases, performance risks, and security gaps. Use when user says /audit-plan or asks to review a plan markdown file critically.
---

# Audit Plan

Analyze plan quality before implementation. Focus on risk reduction and simplification.

## Inputs

Accept:

- Path to `.md` plan/spec/guidelines file
- Plan text pasted in chat
- Link content pasted by user

If source missing, ask user for file/path/content.

## Audit Scope

Always evaluate:

1. Prospective flaws
2. Simplification opportunities
3. Edge cases
4. Performance concerns
5. Security concerns

## Method

1. Parse goals, constraints, assumptions, dependencies.
2. Identify ambiguous or conflicting requirements.
3. Stress-test happy path against real-world variance.
4. Compare proposed approach to simpler alternatives.
5. Evaluate implementation readiness (validation, tests, ownership).

## Severity Levels

- `critical`: likely failure, security hole, major data/integrity risk, performance issue, or severe regression
- `non-critical`: important improvement that does not block safe plan execution

## Output Modes

- `shallow` (default): concise findings only; parent numbered items with severity only, no child bullets/details.
- `deep`: full findings with child bullets/details per section requirements.
- If user does not request mode explicitly, use `shallow`.
- If user asks for "deep", "detailed", or equivalent, use `deep`.

## Checks

### Prospective Flaws

- Missing ownership or decision authority
- Hidden coupling across systems
- Circular dependencies
- Undefined failure handling
- Migration or rollout gaps

### Simplification

- Remove unnecessary layers/abstractions
- Merge duplicated workflows
- Prefer existing framework primitives over custom infra
- Sequence delivery into smaller, testable slices

### Edge Cases

- Empty, null, malformed, duplicate, out-of-order inputs
- Timezone, locale, currency, encoding issues
- Concurrency/race conditions
- Partial failures and retry storms
- Permission boundary edge behavior

### Performance

- N+1 queries, unbounded loops, chatty I/O
- Large payloads, missing pagination/batching
- Cold-start and peak-load behavior
- Caching strategy and invalidation rules
- Defined SLO/SLA and performance budget

### Security

- AuthN/AuthZ gaps
- Injection vectors (SQL, command, template, prompt)
- Data exposure in logs/errors/analytics
- Secrets handling and rotation plan
- Rate limiting, abuse prevention, denial-of-service paths

## Required Output

Return concise report in this exact structure:

Numbering rules:

- Always include both `critical` and `non-critical` findings in one pass.
- Do not include a top-level `Findings` section.
- Always list category sections only: `Simplify First`, `Edge Case Matrix`, `Performance Risks`, `Security Risks`.
- If no findings exist in a category, include one concise sentence: "No <category> concerns found."
- If no findings exist across all categories, return one concise sentence: "No critical or non-critical concerns found."
- Continue numbering across sections (do not restart at 1 per section).
- Mode-aware layout:
    - `shallow` mode:
        - Use exactly: `N. [critical|non-critical] <summary>`
        - Do not include child bullets/details.
    - `deep` mode:
        - Use exactly:
            - `N. [critical|non-critical] <summary>`
            - `    - <detail 1>`
            - `    - <detail 2>`
- In `deep` mode, never output top-level `-` bullets for detail lines when they belong to numbered item.
- Every numbered point in every category must include severity tag.

### Simplify First

- Use numbered items with continuing index.
- 1-3 highest leverage simplifications.
- Include severity on each point.

### Edge Case Matrix

- Parent item = one edge case with continuing index.
- `deep` mode child bullets:
    - Expected behavior
    - Test needed
- Include severity on each parent item.
- In `shallow` mode, output parent items only (no child bullets).

### Performance Risks

- Parent item = one performance risk with continuing index.
- `deep` mode child bullets:
    - Trigger condition
    - Mitigation
- Include severity on each parent item.
- In `shallow` mode, output parent items only (no child bullets).

### Security Risks

- Parent item = one security risk with continuing index.
- `deep` mode child bullets:
    - Attack path
    - Mitigation
- Include severity on each parent item.
- In `shallow` mode, output parent items only (no child bullets).

Example pattern:

1. [critical] Risk: unvalidated client date input drives server-side log context
    - Attack path: crafted request with malformed `log_date` bypasses UI assumptions
    - Mitigation: keep server validation (`Y-m-d`) and add strict client validation

2. [non-critical] onSidebarDateChange accepts malformed/empty `newDate`
    - Why it matters: invalid parse can produce bad state and wrong navigation.
    - Recommended fix: validate strict ISO date before parse and ignore invalid input.
    - Evidence: `newDate.split('-').map(Number)` used without guard.

## Planning-Mode Handoff

When audit runs in planning context (plan/spec/guideline authoring flow), always ask what to address next before any edits.

1. Finish audit output first.
2. Immediately prompt user with `AskQuestion` tool so selection UI appears.
3. Build choices from audit numbering:
    - `all` (address all numbered points)
    - `none` (no edits now)
    - Individual numbered points (`1`, `2`, `3`, ...)
4. Allow multi-select so user can choose multiple numbered points.
5. If user selects `none`, stop with short confirmation and make no edits.
6. If user selects `all` or specific numbers, edit plan in place to address selected points only.
7. After edit, summarize which point numbers were addressed and which remain.

Prompt label pattern:

- "Which audit points should I address in plan now?"
- Options: `all`, `none`, plus each numbered finding.

## Behavior Rules

- Prefer specific, actionable criticism over broad statements.
- Reference concrete sections/phrases from provided plan when possible.
- If evidence missing, mark as assumption explicitly.
- Default read-only behavior: do not change, rewrite, or patch plan/spec/guideline file or provided context.
- Only modify file/content after explicit user instruction to edit it (direct request or planning-mode point selection via prompt).
- If user asks for improvements but does not explicitly request edits, return proposed diffs/rewrite suggestions in response only.
- Do not rewrite full plan unless user asks; prioritize high-impact fixes.
