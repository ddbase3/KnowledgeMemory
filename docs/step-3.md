# Patch 3: Game quality and completion checks

## Patch scope

Patch 3 builds exclusively on the verified Patch 2 ZIP sources, without changing or replacing the existing BASE3 agent, authorization, settings, routing or retrieval owners.

Changes:

- `KnowledgeMemoryPairParser`: strict JSON fields independent of key order; full-term rather than substring excerpt checks; normalized duplicate detection; rejection of overlapping answer cards; and rejection of bidirectional control characters.
- `KnowledgeMemoryGameService`: stronger prompt instructions to prefer unique, concrete answers and document-supported excerpts. Prompt instructions and consistency checks do **not** establish authentic document provenance.
- Browser game: a single pure summary function derived from the established `GameState`, accessible spoken card text on flips, clean reset on a new round and an explicit note that source excerpts are model-reported and unverified.
- German and English translations, tests, README, `PRIVACY.md`, version `1.2.0`, and the accompanying agent-setup documentation updated in the same patch.

No database schema, settings records, agent definitions, background jobs, new routes or additional stored game state are introduced. No source code outside `components/Base3/KnowledgeMemory/` is changed.

## Install and rollback

From the ILIAS repository root with patches 1 and 2 already installed:

```sh
unzip -o knowledge-memory-03-release.zip
```

The archive contains an independent `restore-knowledge-memory-02.sh` in the repository root. It checks known file hashes and rejects locally changed patch-owned files, restores the exact Patch 2 baseline from a packaged snapshot, and removes only files introduced by Patch 3. It does not change `ISettingsStore` data or host services. Run from the same repository root:

```sh
sh restore-knowledge-memory-02.sh
```

To roll further back, use the preceding `restore-knowledge-memory-01.sh`, and for complete removal first restore Patch 1 and then use its removal script. Do not run removal scripts while accepting newer patch files as the baseline.

## Local verification

- PHP 8.4 syntax checks on every PHP source, test and template.
- Node 22 built-in test runner: game rules, layout validity and translated UI keys.
- PHP parser and configured-agent smoke tests with test objects. These are not equivalent to MissionBay/ILIAS runtime tests.
- Archive ZIP scope, symlink/traversal/duplicate-entry checks, clean and partially applied rollback and modified-file refusal.
- JSON manifest validation and literal TAB indentation of nested module source.

## Host acceptance matrix

| Verification | Local source/tests | Must be tested on actual ILIAS 11 / configured MissionBay |
| --- | --- | --- |
| Game state, scoring, combo, progress and restart | Yes | Real browser feedback and focus |
| Five responsive layouts and seeded reflow | Node geometry tests | Browser at desktop, mobile, zoom 200% and ILIAS CSS |
| JSON generation and agent config IDs | Contract-based smoke | MissionBay effective configuration and tool profiles |
| File-attachment context | Correct input fields | Confirm documents actually attached |
| Citation consistency | Parser checks model text | Authoritative retrieved source span verification **not established** |
| Access to game/settings | BASE3 contracts and unit tests | Native effective role/feature decisions and document visibility |
| CSP/asset load in AJAX display | Template source | Real host navigation, `IAssetResolver` CSS/JS URLs, CSP and browser console |
| Keyboard and live status announcements | Source checks | Manual keyboard and screen-reader checks, WCAG 2.2 AA |
| Concurrent round generation | Stateless game code | Representative parallel clients, rate/cost behavior, downstream pressure |
| Agent errors, denied same-origin requests and settings races | Contract-level tests | Live host endpoints and logging |
| Data processing and deletion | Plugin `PRIVACY.md` | Runtime/provider retention and responsible controller policies |

**Status:** Patch 3 finalizes the planned game-side quality and presentation work, but its deployment cannot be signed off as a fully evidence-verified production game from the available sources. The model-only-source limitation and actual ILIAS/MissionBay verification remain open and must not be replaced with an alternate architecture.

## Display repair in version 1.2.1

The game and settings screens now load the plugin stylesheet and game script through normal HTML asset tags, with URLs supplied by the same `IAssetResolver`. `game.js` initializes both screens from encoded template attributes, without inline scripts. The game composes the existing authorized `KnowledgeMemorySettingsDisplay` rather than providing a second settings form, save endpoint, or agent selector service. Test with both game and settings permissions, as well as a user authorized only to play. The real ILIAS 11 host still needs a browser integration check.


## Follow-up correction 1.2.2: native ILIAS form requests

The HTML game and settings display continue to use the same controllers,
linked actions, agent configuration and token scopes. The two AJAX POST bodies
are form-encoded rather than JSON-encoded to remain compatible with the
reported ILIAS request security behavior. `IRequest::post()` is the sole
input source for these actions; the JSON response representation is unchanged.
The configured pair count arrives as a string and is converted to an integer
before the existing domain validator runs. The temporary scoped token was required in 1.2.2 but removed in version 1.2.4. Both endpoints still require an explicit positive `IAuthorizationService` result and strict same-origin POST validation.
This correction does not establish document provenance or host-level deployment
acceptance; those gates remain unchanged.


## Follow-up correction 1.2.3: scoped AJAX token in the request header

The repeated 403 was emitted by the game's own invalid-request condition.
It does not prove that ILIAS blocked the entire request. Since the token is
currently the only field used by `IToken::check()`, both actions now submit it
through `X-Knowledge-Memory-Token` rather than in ILIAS POST data. The existing
POST method, native user identity, `IAuthorizationService::decide()` and scoped
`IToken::check()` remain mandatory. `IRequest::server()` is the only entry point
for the token; there is no alternate token transport or fallback. The token-header change did not resolve the real 403 and was superseded in 1.2.4. The result of a live ILIAS test is still required before declaring the issue resolved.


## Follow-up correction 1.2.4: remove the redundant request token

The user supplied the existing `AgentArena/README.md` account of the matching host issue: its extra `IToken` comparison failed in embedded ILIAS. KnowledgeMemory had introduced the same extra temporary-token responsibility. Version 1.2.4 removes `IToken` from game and settings displays and from the round endpoint, without changing BASE3 or the ILIAS adapter.

Both original actions now require native host identity, their existing feature-specific `IAuthorizationService` decision, `POST`, `Sec-Fetch-Site: same-origin`, and `application/x-www-form-urlencoded`. Missing Fetch Metadata, `same-site`, `cross-site`, wrong methods, and wrong Content-Type are rejected before storage or agent invocation. No alternate request format, fallback, settings owner, or public path is introduced.

Tests include positive and negative authorization, origin/header, media-type and normal game/settings flows. Actual ILIAS/host delivery of Fetch Metadata remains a live integration check, and authoritative document evidence remains a separate open release requirement.
