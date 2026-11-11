# KNOWLEDGE MEMORY: Privacy and data processing

Technical status: steps 1, 2 and 3 implemented, same-origin request correction 1.2.4 and parallel round-local pair generation 1.3.4 included, 2026-11-11. Runtime evidence gate remains open. This file describes the game plugin itself and known dependencies. It does not assert legal compliance for any particular deployment.

## Purpose and controller

The plugin creates a personal learning game using one administrator-selected configured BASE3 agent and its documents. The controller, applicable legal basis, user notices, retention schedule and processor agreements belong to the actual deploying organisation and must be assessed for that deployment.

## Data sent and recipients

When starting a round, the plugin passes a per-run agent configuration with an explicitly empty system prompt, the two agreed user-prompt templates (the keyword prompt and repeated single-pair prompt), a fresh random conversation ID per agent request, the fixed `conversation_channel_id` value `knowledge-memory`, the agent ID, and the documented MissionBay attachment context to `IAgentExecutionService`. The active agent runtime and configured model/tool services may forward prompts, extracted documents or metadata to model providers or other processors, as selected by that agent's configuration. No external service is contacted directly by this game plugin.

The game first shows a generated pool of document terms, then repeatedly shows each random three-term combination while an individual card request runs. Each attempt may send up to 36 answers accepted *within that same round* as exclusions through the existing agent service. Up to three card-generation calls may run simultaneously. Duplicate cards and temporary generation failures lead to new individual attempts, up to six attempts per configured pair. Both stages use the existing `IAgentExecutionService`. Keyword terms, seed choices and accepted answers are not retained between rounds. The game shows generated question/answer text and model-reported source excerpts in the authorized player's browser after the round. The review explicitly marks citations as not independently checked against original documents. Those excerpts can contain information from the document corpus and must not be exposed to players who cannot legitimately access the corresponding knowledge.

## Processing and storage

- A single game round's card data, score, flip state, attempts, elapsed time and its own answer-exclusion list are held in in-memory browser objects and cleared when another round starts. The game does not use `localStorage`, cookies or its own database tables for scores or transcripts. Layout type and its random seed live only in the same in-memory browser instance; ResizeObserver responds to size changes without transmitting dimensions or game actions.
- The administrator-selected agent ID, pair count and difficulty are stored using the host-provided `ISettingsStore` under the `knowledge_memory/game` record. Authorized administrators may edit that same record through the existing settings display embedded within the game. Removal uses that same owner; the game itself has no additional settings backend.
- Browser POST requests send form-encoded settings fields and bounded seed/current-round answer-exclusion fields through `IRequest::post()` and return JSON results. The game endpoint has the two action values `keywords` and `cards`, with up to three concurrent `cards` requests per round. Both game actions and the separate administrative settings-save action validate the POST method, `Sec-Fetch-Site: same-origin`, and the form content type through the existing `IRequest::server()` API. Missing Fetch Metadata is rejected. The game creates, stores, sends and checks no additional temporary request tokens. No new request destination is introduced.
- The agent runtime may log or persist prompts, context, conversation history, tool results, retrieved data or usage details, depending on the runtime, memory profiles, configuration and provider. The actual storage and retention of those dependencies are **not verified** by this repository.
- The game sends a warning to the existing `ILogger` when generation fails, recording only the agent stage and exception class (not user prompts, seeds, generated cards or document excerpts). Logger storage and retention are host-controlled.

## Deletion and retention

Refreshing or closing the page removes the game state's in-memory browser objects. It does not remove provider logs, runtime memory, generated text stored elsewhere or host sessions. To delete game settings, remove `knowledge_memory/game` through the existing `ISettingsStore` administration/service. Runtime/provider data deletion must use the corresponding existing owners and the deployment's retention policy. There is no game-owned scheduled cleanup or database migration.

## Security and access boundaries

Both the HTML display and JSON endpoint require explicit allow decisions from `IAuthorizationService` for `feature/knowledgememoryplayfeature`. The administration display and AJAX save action require `feature/knowledgememorysettingstab`. These do not replace the authorization of the configured agent or its document tools. Each state-changing action requires an authenticated trusted host user, POST, a strict same-origin Fetch Metadata header and form content type. Cross-site, same-site, `none` and missing headers fail closed. These checks do not confer authorization or bypass native host access decisions. No browser-supplied agent ID, actor identity, runtime, model or retrieval profile is used to start an agent.

All generated text is inserted into the game UI through DOM `textContent` and server-side HTML escaping. Client-side points are not trusted authorization or reward values. The game does not create a new high score or user profile.

## Data protection, accessibility and outstanding verification

The owner must check actual runtime recipients, transfers, retention, consent or other legal bases, data-subject rights, and host role/document visibility for the installed system. In particular, the current source available for this package does not establish whether the agent's returned excerpts are backed by genuinely retrieved documents, or whether its runtime stores conversation memory. Do not claim ground-truth provenance or GDPR conformity on the basis of this file.

The UI uses native keyboard-operable cards, focus management after matches, result focus, text-only generated content, an additional spoken reveal announcement, progress and status announcements, and CSS reduced-motion preferences. The five layouts use no third-party scripts, image requests, external fonts or tracking service. The native browser Fullscreen API changes presentation only. Intentional card overlap requires manual pointer, touch and keyboard focus testing on deployed devices; reduced-motion disables decorative keyword movement. Manual testing against WCAG 2.2 AA and the deployed ILIAS theme remains required. Relevant legal considerations include GDPR 2016/679, ePrivacy 2002/58/EC and applicable national accessibility laws and standards, according to deployment scope.
