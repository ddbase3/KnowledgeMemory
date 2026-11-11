# Version 1.3.1: approved prompts, flat keywords, no administration navigation

## Agent invocation

Both agent executions use `IAgentExecutionService` with the same configured agent, authorization and document attachment context as in 1.3.0. Every invocation sets both the `system` input and its local `system_prompt` value to `""`. Stored MissionBay configuration is not written or changed.

The first user prompt is the document keyword prompt agreed in the conversation. Its answer is a single JSON array of strings, not an object with groups. The only structural requirement before random selection is at least three usable terms. The second user prompt is the agreed short Q&A prompt: each triple is thematic guidance, not a mandatory relation, and exactly one populated pair is requested per index. `source_title` and `source_excerpt` are still requested and displayed as unverified model-reported evidence. Literal inclusion of the answer in the excerpt is not a hard parsing requirement.

The browser randomly selects three different terms per pair from the flat list. It does not ask the LLM to draw random terms. It does not introduce persistent keyword state, new agents, additional endpoints or additional retrieval components.

## Settings navigation

The existing `KnowledgeMemorySettingsDisplay` remains embedded within the game and continues to save through the existing AJAX endpoint and `ISettingsStore`. Its feature permission identity `knowledgememorysettingstab` and default `administration` audience remain unchanged. The former class with that name is now a feature-only declaration, not a `ISettingsTab`. The unused root settings tab source is removed using the supplied checksum-checked cleanup script.

## Installation and validation

Install over 1.3.0 from the ILIAS repository root:

```sh
unzip -o knowledge-memory-1.3.1.zip
sh remove-knowledge-memory-admin-root-tab.sh
```

Run JavaScript tests and the PHP render smoke from `components/Base3/KnowledgeMemory`. The PHPUnit tests must be run in a deployment with PHPUnit and all BASE3 dependencies. Actual ILIAS/MissionBay retrieval, provenance and browser integration remain unverified here. To undo the release including root-tab removal, run `sh restore-knowledge-memory-1.3.0.sh` from the repository root.
