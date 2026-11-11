# KNOWLEDGE MEMORY 1.3.5: Responsive two-column presentation and expanded tests

## Scope

Presentation and tests only. The complete existing board is on the left when the screen is in landscape orientation. A single wider right panel contains the title, start and fullscreen controls, authorized configuration form, points, pairs, time, progress indicator and the end-of-round result/review. Portrait orientation places the entire control panel above the board. No settings tab, route, authorization model or alternate API is introduced. The agent and game runtime code are unchanged.

The HTML template moves existing elements without changing identifiers, messages, accessibility hooks or their event owners. CSS uses viewport orientation to choose two columns or a vertical stack. The agent settings remain available only to users with the existing permission.

## Tests and evidence

- `node --test --experimental-test-coverage test/Frontend/*.test.js` runs the frontend unit suite with layout-contract checks, a completed game and concurrent request scenarios. Its Node V8 coverage for `assets/knowledge-memory/game.js` reaches **100% lines, 100% functions, approximately 92% branches** in this local environment. There is no new or modified game logic in this release.
- `php test/render-smoke.php` verifies rendered game/settings templates, both settings-authorized and non-admin output, board/sidebar containment and no duplicated game hooks.
- Additional PHPUnit tests cover the play and settings feature identities, service registration, game display authorization, missing translation and settings display resolution, settings HTML and shared assets. PHP syntax checks passed.
- PHPUnit 11.5/12.5 and PHP coverage could not be executed here because the environment lacks PHPUnit and PHP coverage extensions; no all-plugin 100% claim is made.
- Chromium cannot start reliably in the provided execution environment. The real ILIAS 11 browser presentation remains to be checked on the target host.

## Installation and rollback

Install on release 1.3.4 using `unzip -o knowledge-memory-1.3.5.zip` from the ILIAS repository root. Restore the exact previous files using `sh restore-knowledge-memory-1.3.4.sh`. The rollback script preflights hashes and refuses locally modified paths or symlinks.
