# KNOWLEDGE MEMORY

KNOWLEDGE MEMORY is a BASE3 plugin for matching document-grounded questions and answers. It uses one existing, enabled MissionBay agent through `IAgentConfigurationService` and `IAgentExecutionService`. It does not own a new agent registry, model connection, document retriever, authorization scheme or database.

## Version 1.3.5

This release changes **only presentation and tests**. The complete board is the left column in landscape screen orientation. The right column contains the existing title, start and fullscreen controls, embedded authorized agent/pair/difficulty configuration, score, match counter, time, progress and the finished-game result with its review. In portrait screen orientation the entire controls/status/result column is stacked above the unchanged board. The right column is deliberately wider than the former narrow statistics rail. An admin who lacks settings permission still sees the game without the configuration control.

No JavaScript game logic, card positioning algorithm, AJAX request, agent prompt, concurrency, round state, permissions or API contract has been changed. The `game.js` file is byte-for-byte unchanged from release 1.3.4.

Additional tests verify landscape and portrait CSS layout rules, board/sidebar DOM containment, both authorized and unauthorized configuration presentations, result rendering after finishing a round, fullscreen controls and unchanged parallel Q&A behavior. The Node V8 coverage run reports **100% line and 100% function coverage for `game.js`** and **approximately 92% branch coverage**. This is specifically JavaScript runtime coverage, not overall plugin coverage. The PHP tests have also been expanded to cover game display authorization and missing dependencies, feature identity and plugin DI registration. PHP syntax and both HTML render variants pass locally. PHPUnit 11.5/12.5 and PHP line coverage could not be run here because neither PHPUnit nor a PHP coverage driver is installed. Therefore **100% total PHP/plugin coverage is not claimed**.

Checks:

```sh
cd components/Base3/KnowledgeMemory
node --test --experimental-test-coverage test/Frontend/*.test.js
php test/render-smoke.php
# On a host equipped with PHPUnit and a coverage driver:
# phpunit -c phpunit.xml.dist --coverage-text
```

From the host repository root apply `unzip -o knowledge-memory-1.3.5.zip`. Roll back with `sh restore-knowledge-memory-1.3.4.sh` (both from the host repository root). This release does not modify stored settings.

## Version 1.3.4

All three concurrent card requests contribute their individually accepted Q&A pairs to the same round. The animated triple list now retains each in-flight request's existing DOM element and only animates a newly submitted triple once, rather than replaying all three when any response arrives. Each triple has an attempt number so that concurrent work cannot be mistaken for three fresh combinations per accepted pair. Every successfully returned, distinct pair is retained; twelve successful requests create exactly twelve pairs (24 cards). Additional requests occur only when a result is duplicated, empty or fails. The current round also avoids resending the same three-term seed combination. No seed, response or exclusion is stored between rounds.

The changes are confined to the existing JavaScript card loop, keyword animation and their tests. The two approved prompts, forced empty system prompt, existing agent/runtime/API, settings and permission boundaries remain unchanged. The provider-facing concurrency limit stays at three requests. There is no new route, agent service, cache or fallback pipeline.

## Version 1.3.3

The game retains its single HTML display, existing settings display and one `KnowledgeMemoryGameOutput` AJAX endpoint. Each round uses the same two `action` values:

1. `action=keywords`: the configured agent receives the agreed flat-list prompt, now aiming for at least 100 terms from the attached documents. The model does not choose groups or perform random selection.
2. `action=cards`: JavaScript selects one random triple of distinct words and requests exactly one pair through the existing endpoint. Up to three of these independent requests run concurrently. Duplicate questions and answers are rejected only against pairs accepted in the *current round*. A duplicate, empty response or temporary card-generation failure triggers another individual request, without discarding earlier accepted pairs. Only an accepted pair advances the preparation counter. Each new round starts with an empty pair set and an independently generated keyword pool. The game does not retain answer or seed history between rounds. The existing limit of six attempts per configured pair prevents uncontrolled provider calls. It never fabricates missing cards.

The keyword cloud continues animating throughout preparation; up to three active triples are shown together with the existing selection animation and a progress bar based on accepted pairs. The board appears only after the target count has been reached. All accepted pairs use the existing `GameState` mechanics.

Every agent call sets the system input and invocation's effective `system_prompt` to `""` without persisting changes to the configured agent. The second user prompt is unchanged from version 1.3.1, except that it now receives one indexed triple and asks for exactly one result. The first user prompt differs only in the approved keyword target of 100. Answers accepted earlier *in the same round* are passed as optional exclusion hints, bounded to the existing 36 terms, while the authoritative duplicate check runs in JavaScript. Concurrent calls can see the same exclusion list; the browser resolves any resulting duplicate on receipt. The parser requires a populated question and answer, but does not discard usable cards for missing optional source labels or arbitrary length limits. Returned source labels are unverified model statements.

The game UI now includes a top-right fullscreen control, a full-width shell without the former 1190px limit, a compact settings form and a narrow scoring sidebar beside the board on wide displays. On phones the score and board stack vertically. Orbit mode uses actual circle geometry, including bounded overlapping rings for large rounds. Scattered mode deliberately overlaps some cards with random rotations up to 15 degrees. Hovered, focused and flipped cards are brought to the front. Card text is slightly smaller for improved fit.

## Existing integration and authorization

- `KnowledgeMemory\Display\KnowledgeMemoryGameDisplay`: game UI.
- `KnowledgeMemory\Display\KnowledgeMemorySettingsDisplay`: the same authorized settings UI, embedded within the game for authorized administrators. The separate BASE3 administration tab is removed.
- `KnowledgeMemory\Output\KnowledgeMemoryGameOutput`: **the same** AJAX endpoint for both stages, differentiated by the `action` field.
- `KnowledgeMemory\Service\KnowledgeMemoryGameService`: sole owner of prompt generation and `IAgentExecutionService` calls.
- `KnowledgeMemory\Service\KnowledgeMemoryPairParser`: sole owner of keyword and card structure validation.

Both endpoint actions independently require `IAuthorizationService` to ALLOW the existing `knowledge_memory` play feature, a trusted authenticated host user, `POST`, `Sec-Fetch-Site: same-origin` and `application/x-www-form-urlencoded`. A request without the Fetch Metadata proof is rejected. Form data are read by the injected `IRequest::post()` service. No additional `IToken` validation or alternative request path exists. Settings updates continue through the existing settings display, feature permission and `ISettingsStore` record `knowledge_memory/game`.

The browser **never** selects the agent, model or retrieval profile for generation. The server reads its existing settings and resolves the agent via `IAgentConfigurationService::getAgent($agentId, 'agent')`. Every agent call includes the documented MissionBay attachment context fields `agent_id` and `agent_config_group = agent`, and use `IAgentExecutionService::execute()`.

## Setup and installation

1. Configure or select an enabled document-capable agent through the existing MissionBay administration.
2. Configure its ID, card-pair count and difficulty in the existing KNOWLEDGE MEMORY settings display.
3. Install the 1.3.3 patch over the accepted 1.3.2 source from your ILIAS repository root:

```sh
unzip -o knowledge-memory-1.3.3.zip
```

The patch changes files only under `components/Base3/KnowledgeMemory/` and includes `restore-knowledge-memory-1.3.2.sh` at repository root. The version 1.3.1 admin-tab removal remains in effect. The rollback script checks file hashes before restoring the precise baseline, supports partially installed patches and refuses locally modified files. It does not alter stored settings. Use the existing BASE3 discovery lifecycle as needed for the updated manifest. No migrations are required.

For source-based checks:

```sh
cd components/Base3/KnowledgeMemory
node --test test/Frontend/*.test.js
php test/render-smoke.php
```

Use the project's PHPUnit installation to run the test suite configured in `phpunit.xml.dist`. Actual ILIAS 11 + MissionBay agent and browser integration requires host-side testing with genuine documents. This package alone cannot establish the number of valid documents, runtime permissions, provider responses or application performance under concurrent load.

## Returned card schema

Each individual card-generation call returns exactly one pair, with `seed_index` 1:

```json
{
	"pairs": [
		{
			"seed_index": 1,
			"question": "Wie heißt die Eigenschaft instabiler Kerne?",
			"answer": "Radioaktivität",
			"source_title": "Dokumenttitel",
			"source_excerpt": "Instabile Kerne zeigen die Eigenschaft Radioaktivität beim Zerfall."
		}
	]
}
```

This example illustrates the **response format**, not a built-in card or an assertion that an accessible document actually says so. The parser accepts a populated question and answer without enforcing an arbitrary index or requiring the model to provide usable source fields. When present, source text is displayed as model-reported and unverified. Duplicate questions and answers are handled only against the current round's accepted pairs. The cards returned to the UI do not include `seed_index`.

## Provenance limitation

**The source citations remain model-reported, not independently authenticated.** Although the two approved prompt templates require retrieval from the configured agent's documents and include the correct attachment context, the available `AgentExecutionResult` contract does not guarantee authoritative retrieved document spans. A model-supplied quote is not proof of source origin. The existing MissionBay and AssistantRuntime owners must be inspected and, where needed, extended to expose trustworthy evidence before strictly document-exclusive generation can be certified. Do not add a second retriever to KnowledgeMemory. The results view explicitly labels its citations as unverified.

See `docs/agent-setup.md`, `docs/version-1.3.0.md` and root `PRIVACY.md` for operational, accessibility and privacy notes. Older `docs/step-1.md`, `step-2.md`, and `step-3.md` describe the earlier releases for historical comparison.
