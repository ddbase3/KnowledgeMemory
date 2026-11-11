# KNOWLEDGE MEMORY 1.3.2: Single-pair generation

## Accepted flow

1. Call the configured agent once for a flat JSON list of keywords using the approved keyword prompt, with the target raised to 100 terms.
2. Randomly choose a three-term combination in `game.js`. A previously used combination is not selected again within the browser session until its bounded history is reset.
3. Display the active triple in the animated preparation panel and request one pair with `action=cards` on the existing AJAX endpoint. The unchanged approved Q&A prompt receives one triple, with index 1. The effective system prompt is always the empty string, without changing the stored agent configuration.
4. Compare the candidate's normalized answer and question with accepted pairs. Reject duplicate answers from recent page-session history as well. If duplicated, draw another triple and try again. Update the preparation progress bar only after accepting one pair.
5. Repeat until the configured pair count is reached, then shuffle and start the existing game. A round stops with a clear error after six attempts per desired pair rather than making unlimited expensive agent calls. Failed rounds never invent or silently substitute cards.

## Architecture

The owner is unchanged: `KnowledgeMemoryGameService` calls the configured `IAgentExecutionService`; `KnowledgeMemoryGameOutput` handles both authorized AJAX actions; `KnowledgeMemoryPairParser` validates single-pair JSON structure; `game.js` owns random selection, duplicate checks and round progress. This does not add a new service, endpoint, agent, permission, context copy, credential or data store. The existing same-origin POST requirements and BASE3 authorization remain unchanged.

## Source integrity and security

The attached-document guidance is part of the two approved user prompts. Source excerpts returned by the model are not independently verified against the retrieved documents. No display or system permission is a substitute for document-level tool authorization. All generated content is written as text, not HTML. Only accepted pairs enter the browser game state. The keyword pool, used triples and recent answers stay in page memory and disappear when the page closes.

## Local tests and acceptance

- `node --test test/Frontend/*.test.js`
- `php test/render-smoke.php`
- `php -l` for changed PHP sources and tests
- PHPUnit 11.5 or 12.5 via `phpunit.xml.dist`, where installed

Verify on the target ILIAS 11 host with the real configured document agent, including a 12-pair round, multiple repeated answers, responsive layouts and the fullscreen button. Real runtime behavior, source-provenance confirmation and concurrent load are not established by local tests. Rollback via `sh restore-knowledge-memory-1.3.1.sh` from the ILIAS repository root.
