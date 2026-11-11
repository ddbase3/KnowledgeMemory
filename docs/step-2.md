# Patch 2: adaptive design and layouts

## Scope and architecture

Patch 2 builds on the accepted Patch 1 sources in `KnowledgeMemory` and changes only the existing presentation owner. `game.js` is still the sole browser game controller. `GameState` remains the sole score and matching rules owner. `IAgentConfigurationService`, `IAgentExecutionService`, the HTML/JSON endpoints, permissions, temporary tokens and SettingsStore integration were untouched by Patch 2. The redundant request tokens were later removed in 1.2.4.

New behavior:

- A responsive, light game interface with visual score tiles, labelled progress, a layout indicator, start/empty state and accessible status text.
- Five layouts: classic grid, half-cell staggered rows, scattered cells with bounded rotation, an orbit/perimeter and waves.
- One layout per round with no immediate repeated layout; seed-stable layout positions for reflow.
- A resizable card grid driven by the actual board width. Reflow does not replace card DOM, change current selected/matched cards, reset the timer or restart a round.
- 3D card flips, bounded mismatch animation, score pop, combo indicator, card-type badges, result stars and a styled source review.
- Keyboard focus after successful matches, locked-card `aria-disabled`, status announcements and reduced-motion support.

## Files

- `assets/knowledge-memory/game.js`: extends the existing UI state with pure layout helpers.
- `assets/knowledge-memory/game.css`: full light UI redesign.
- `tpl/Display/KnowledgeMemoryGameDisplay.php`: revised presentation structure, same asset loader and configuration.
- `lang/Display/de.ini`, `lang/Display/en.ini`: all new visible and accessible labels.
- `test/Frontend/layout.test.js`: width/count/mode/seed invariant tests.
- `README.md`, `PRIVACY.md`, `base3.json`: updated documentation and module version.

No database migrations, new foundation interfaces, separate services, runtime modes or secondary agent paths are part of this patch.

## Test gates

1. `node --test components/Base3/KnowledgeMemory/test/Frontend/*.test.js` (both the original game-rule tests and layout tests).
2. `php -l components/Base3/KnowledgeMemory/tpl/Display/KnowledgeMemoryGameDisplay.php` and existing PHP files; validate JSON and language catalogs.
3. Verify layout slot bounds and uniqueness for 8, 12, 16, 20, 24 cards and multiple widths/seeds.
4. Browser test: start from an enabled agent, observe all five layouts over successive rounds, resize during an opened/mismatched game, use keyboard/tab/Enter, verify source details and source privacy, repeat with reduced motion.
5. Test on mobile, tablet and large screens, including 200% zoom and the deployed ILIAS theme. Visual/browser testing and agent execution still require the real target environment and are not established by the Node tests.

## Rollback

Install from ILIAS repository root via `unzip -o knowledge-memory-02-design.zip` after installing Patch 1. The patch root includes `restore-knowledge-memory-01.sh`, which checks the files changed by Patch 2 and restores the complete original Patch 1 versions while removing only Patch 2-specific new files. Run it from the ILIAS repository root, not from inside the module. It refuses to overwrite local modifications. The Step 1 uninstall script can then operate on the original file checksums if complete removal is required.

## Outstanding Step 1 acceptance requirements

The current model-supplied `source_excerpt` is not independently verified as authentic retrieved document text. Strict document-only content generation still requires proof at the existing MissionBay/AssistantRuntime retrieval owner. The actual installed `Base3Ilias`, `MissionBay`, `AssistantRuntime` and other game integrations have not been verified in this package. Patch 2 does not claim to resolve these limitations by UI changes.
