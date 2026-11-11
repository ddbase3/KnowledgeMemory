# Patch 1: integration and acceptance

## Content

- `KnowledgeMemoryPlugin`: registers the game's domain service lazily using `NOOVERWRITE`; it does not register agent/routing/authorization backends.
- `KnowledgeMemoryPlayFeature`: declares `feature/knowledgememoryplayfeature` with an `administration` default audience.
- `KnowledgeMemorySettingsRootTab` and `KnowledgeMemorySettingsTab`: add a discoverable settings view and its existing-feature permission.
- `KnowledgeMemorySettingsDisplay`: uses host DI and authorized AJAX to save settings. The initial scoped token was removed in the 1.2.4 correction.
- `KnowledgeMemoryGameDisplay`: HTML game entry point.
- `KnowledgeMemoryGameOutput`: POST game-generation entry point with a JSON response, independent authorization and the strict same-origin request check introduced in 1.2.4.
- `KnowledgeMemoryGameService`: sole owner of the game's use of `IAgentExecutionService` and effective agent selection.
- `KnowledgeMemoryPairParser`: validates the assistant's JSON envelope; no external document source is cloned or indexed.
- Frontend: classic Memory game with deterministic matching/scoring, responsive grid, clear errors and a review panel.

## Local test gate

1. PHP syntax check and consistent TAB indentation of PHP, JS, templates and JSON.
2. PHPUnit parser, service, authorization and settings tests, executed with the installed PHPUnit version when available.
3. Node game rules tests.
4. Translation key parity for `lang/Display` and `lang/Settings`.
5. Manually verify asset resolution and ordinary stylesheet/external script delivery under the current BASE3 ILIAS bootstrap. Version 1.2.1 removes the previous `AssetLoader` dependency.

## Agent evidence gate

The model returns a purported exact document excerpt, but that excerpt is not independently attested. Until the current runtime's actual retrieval evidence contract can be verified and consumed, the strict requirement to generate from documents only is **unproven**. The game must not be represented as having completed that requirement. Review `README.md` and `PRIVACY.md` before rollout.

## Release and rollback

This first patch only adds `components/Base3/KnowledgeMemory/` and does not change existing framework/plugins or create a database table. A removal script is included at the patch root. Delete the `KnowledgeMemory` directory through that script only after verifying its contents have not been augmented with local data; do not reset unrelated class-map/cache or settings data. If you configured `knowledge_memory/game`, remove that record through the existing SettingsStore owner to complete uninstall.
