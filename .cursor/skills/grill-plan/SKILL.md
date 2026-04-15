---
name: grill-plan
description: Stress-tests plans, specs, and implementation ideas through structured interrogation. Use when user says /grill-plan, asks to pressure-test a plan, or wants deeper planning before coding.
---

# Grill Plan

Relentlessly test plan quality. Goal: expose unknowns before build.

## Inputs

Accept one of:
- Plain-text plan in chat
- Path to a markdown file with plan/spec/guidelines
- Link to plan text pasted by user

If plan source missing, ask for it first.

## Operating Mode

- Ask one focused question at a time.
- Prefer multiple-choice via `AskQuestion` tool.
- No generic yes/no unless decision truly binary.
- After each answer: brief acknowledgement, then next highest-risk question.
- Stop only when all critical branches resolved.

## Investigation Order

Follow this order unless context demands otherwise:

1. Objective + constraints
2. Scope boundaries (in/out)
3. Data model + state transitions
4. Failure modes + rollback
5. Security + abuse paths
6. Performance + scaling limits
7. Edge cases + weird inputs
8. Operational concerns (observability, deploy, migration)
9. Test strategy + acceptance criteria

## Question Quality Rules

Each question should:
- Target one decision
- Include 2-4 realistic options
- Explain trade-off if unclear
- Avoid asking what can be learned from repo/files

If answer can be discovered from code/docs, inspect directly instead of asking.

## Drill-Down Framework

For each major decision, walk this tree:

1. Assumption
2. Evidence
3. Risk if assumption wrong
4. Mitigation
5. Owner + trigger to revisit

Do not move on until branch is concrete enough to implement.

## Stop Conditions

Stop grilling when all are true:
- Core decisions made
- Biggest risks have mitigations
- Open questions are non-blocking and explicitly tracked
- MVP slice clearly defined

## Output Format

When complete, return:

### Decisions Locked
- Bullet list of finalized choices

### Risks Remaining
- Risk, impact, mitigation, owner

### Open Questions
- Only unresolved, non-blocking items

### Build-Ready Checklist
- [ ] Scope frozen for first iteration
- [ ] Security model reviewed
- [ ] Performance budget defined
- [ ] Rollback path defined
- [ ] Test plan covers happy path + edge cases

## Defaults

- Bias toward simpler architecture first.
- Reject premature abstraction unless clear near-term need.
- Prefer explicit constraints over optimistic assumptions.
