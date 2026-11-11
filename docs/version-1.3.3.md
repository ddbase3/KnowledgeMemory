# KNOWLEDGE MEMORY 1.3.3: Independent rounds and concurrent cards

## Behaviour

- A new round clears game cards, accepted answers and generated keywords. No answer or random seed combination from any earlier round is retained as an exclusion. The next round may contain the same Q&A cards as a previous round.
- The authorized existing `action=keywords` endpoint returns a new flat keyword list. `game.js` selects one three-term seed per card request using JavaScript randomness, without an LLM random-selection step.
- At most three single-card `action=cards` requests are active at once. Every request uses the existing POST endpoint, configured agent and `IAgentExecutionService`. Per-request `conversation_id` remains unique. No route, service, storage, token, or authorization system is added.
- All results are checked against the current round's accepted questions and answers. Only accepted pairs advance the progress indicator. Another individual request replaces a duplicate or unusable response. A transient card-generation request failure does not discard previously accepted cards; method, permission and request-validation failures stop the round.
- The animated keyword cloud now shows up to three concurrent seed triplets and accepted-pair progress. The existing six-attempts-per-target-pair limit prevents unlimited agent calls. If every attempt fails, the game reports that limit instead of inventing card content.
- The parser only requires nonempty question and answer text from an agent-supplied JSON `pairs` array. Source titles and excerpts are still requested by the approved prompt, but missing source fields alone do not suppress a playable card. The result UI identifies provided source excerpts as unverified model claims.

## Unchanged contracts

Both approved user prompts and the forced empty system prompt remain unchanged. The plugin-owned endpoint requires the same authenticated host user, explicit BASE3 authorization, same-origin POST and form content type. The agent service still uses configured `agent` and the existing MissionBay context fields. No storage of a game's cards is added between rounds.

## Local checks

```sh
cd components/Base3/KnowledgeMemory
node --test test/Frontend/*.test.js
php test/render-smoke.php
```

PHP syntax checks, isolated parser checks and rollback are also part of the release checks. A live ILIAS 11/MissionBay integration test, realistic multi-user load and independently authenticated document-source evidence are not available in the local environment. Restore the exact previous source with `sh restore-knowledge-memory-1.3.2.sh` from the ILIAS repository root.
