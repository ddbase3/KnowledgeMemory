# Release 1.3.0: contextual triple seeds and responsive board

## Generation and evidence

All generation remains within `KnowledgeMemoryGameService` and calls the configured `IAgentExecutionService`. The keyword stage asks for 8 to 14 document-related groups of 4 to 8 terms. The parser requires at least 6 groups with at least 4 valid, nonduplicated terms. A second AJAX request contains the chosen three-term combinations for each pair and a bounded history of used answers. The server does not accept agent identity or model choices from the browser. Every seed is constrained to a short, single topic term. All triples go to one `execute()` call, with an explicit 1-based `seed_index` per response item.

The card parser requires one distinct response per index, nontrivial source excerpts containing the complete answer, nonoverlapping answers, no significant word from the answer visibly repeated in its question and no exact reuse of an answer from recent rounds. Malformed or unsupported outputs are rejected, with separate translated error messages for keyword discovery and card generation. Prompt-only source grounding does not prove that the quotes are real. No source files are cached and no question bank is introduced.

## Layouts

The UI has five presentation modes: classic, staggered, scattered, orbit and wave. Orbit uses one or two concentric rings based on card count, bounded to a maximum nominal 620px board height. Scattered cards can overlap and rotate up to 15 degrees, preserving in-board bounds, with focused, hovered and exposed cards raised above the stack. All other modes keep their grid-based placement. Geometry is recalculated at the existing ResizeObserver boundary without changing points or matches.

The game shell fills the host's available width. Desktop uses a narrow sidebar for points, pairs, time and progress; phones stack the sidebar and board. Admin settings are collapsed by default and use compact fields. Native fullscreen uses the game element and the browser Fullscreen API, not another host display or iframe. Reduced-motion users receive stable non-animated content.

## Verification boundaries

Locally run: Node tests for rules, geometry and seed distribution; PHP syntax; parser smoke; template render; install and exact rollback against a copied 1.2.4 baseline. PHPUnit tests cover both authorized AJAX stages, denial scenarios and parser limits, but must be executed with the deployed BASE3 dependencies. Integration checks still needed in ILIAS: actual document retrieval for both phases, rejected and missing source citations, non-admin denial, fullscreen interaction, card accessibility under overlapping layers, phone responsive layout and concurrent load. Never claim a local PHP fixture validates document provenance.
