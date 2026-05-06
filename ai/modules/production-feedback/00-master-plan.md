# Production Feedback Plan

## Status

- Module id: `production-feedback`
- Status: `ready_for_feedback`
- Current phase: `01-feedback-intake-and-triage`
- State file: `ai/state/production-feedback.json`
- Last updated: `2026-05-06`

## Purpose

Track production test feedback as durable, implementation-ready work. Each feedback item should be converted into a scoped fix or feature plan, assigned to a phase, verified, and reflected in the state file so the next session can resume without reconstructing context from chat.

## Phase Index

1. [Phase 01 - Feedback Intake And Triage](01-feedback-intake-and-triage.md)
2. [Phase 02 - Production Fixes And Regressions](02-production-fixes-and-regressions.md)
3. [Phase 03 - Product Followups And Enhancements](03-product-followups-and-enhancements.md)
4. [Phase 04 - Release Verification And Handoff](04-release-verification-and-handoff.md)

## Feedback Workflow

1. Capture the user feedback in this module with the date, production context, affected URL or workflow, expected behavior, and observed behavior.
2. Classify it as `bug`, `regression`, `missing_feature`, `ux_issue`, `content`, `performance`, `security`, or `deployment`.
3. Decide whether it belongs in an existing phase or needs a new appropriately named phase file inside this directory.
4. Update `ai/state/production-feedback.json` before implementation starts.
5. Implement the fix using the application conventions and relevant skills.
6. Run the smallest meaningful verification set, then record results in the state file and phase file.

## Priority Rules

- `critical`: production blocker, data loss, security issue, broken authentication, broken trip access, or deployment failure.
- `high`: core trip workflow fails or is confusing enough to block normal use.
- `medium`: important product fit, polish, or repeated annoyance that does not block testing.
- `low`: nice-to-have improvement, copy tweak, or minor visual refinement.

## Planning Template

Use this structure when adding a new feedback item to a phase file:

```md
### Feedback YYYY-MM-DD-Short-Slug

- Status: `planned`
- Priority: `medium`
- Type: `bug`
- Reported from: `production`
- Affected workflow: ``
- Evidence: ``
- Expected behavior: ``
- Observed behavior: ``
- Implementation plan:
  1. Inspect the affected routes, controllers, Vue pages, requests, policies, and tests.
  2. Make the smallest production-safe change that fixes the root cause.
  3. Add or update focused tests.
  4. Run the affected verification commands.
- Verification:
  - `pending`
- Handoff notes:
  - `none`
```

## State Update Protocol

When feedback arrives:

1. Add the feedback item to the relevant phase file.
2. Set `current_status` in `ai/state/production-feedback.json` to `triaging`, `planning`, `in_progress`, `blocked`, or `verified`.
3. Add an entry to `feedback_items`.
4. Record the next action, affected files once known, and verification commands.
5. Do not mark an item `verified` until tests or an equivalent production check have passed.

