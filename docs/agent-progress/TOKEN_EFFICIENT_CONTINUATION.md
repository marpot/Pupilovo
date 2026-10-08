# Token-efficient continuation — 2026-10-08

This instruction supplements MASTER_PLAN and does not supersede security or user approval requirements.

- Continue from CURRENT_STATE and PENDING_TASKS; do not re-read the entire repository or repeat completed B/C work unless required for a failing test.
- Prioritize D: pricing repository, Woo matcher, versioned dry-run, idempotent import on isolated fixtures. Then E: Action Scheduler, safe sync. Then F: operational admin UI, critical integration/security tests, installable ZIP.
- Use targeted file reads, narrow diffs and concise command outputs. Avoid verbose commentary, repeated whole-repository audits and speculative refactoring.
- Batch related edits; run targeted tests after each unit and full suite at milestones. Record exact pass/fail counts.
- Update CURRENT_STATE, PENDING_TASKS, TEST_RESULTS and RESUME_INSTRUCTIONS after each milestone, with changed paths and next command.
- Do not merge, push, deploy, or modify real WooCommerce products/categories without explicit approval. Do not bypass permission prompts.
- Before exhausting context or usage, stop at a coherent checkpoint; do not claim autonomous execution past account limits.
- Final status must distinguish implemented, tested, blocked, and pending items.
