# KNOWLEDGE MEMORY 1.3.4: Exact per-request seed animation

## Corrected behaviour

- A configured round of twelve pairs launches twelve single-pair requests when every returned pair has a unique, populated question and answer. Three of these requests may overlap; **all three usable responses are included**. Twelve pairs yield 24 game cards, not 36 requests or 36 pairs.
- The animation previously rebuilt all three active triplets whenever any one request finished, restarting animations on existing triplets and making them look like new selections. It now creates one persistent animated triplet per actual request. A triplet remains visible until its own request resolves, without being redrawn by other responses. Each request is numbered.
- A round-local set tracks submitted seed signatures so that the same three keywords are not submitted more than once in a round. No seed data or Q&A pairs persist between rounds.
- Duplicate questions/answers, empty content and failed calls may require additional requests, but other usable results are never thrown away. The existing limit of six attempts per desired pair remains unchanged.
- The existing `action=keywords` and `action=cards` endpoints, configured agent, three-request maximum, approved user prompts, empty system prompt and BASE3 authorization remain unchanged.

## Local checks

```sh
cd components/Base3/KnowledgeMemory
node --test test/Frontend/*.test.js
php test/render-smoke.php
```

The frontend tests assert exactly twelve card requests, twelve animated request groups and 24 game cards for twelve successful results, and verify that concurrent out-of-order replies retain the DOM nodes and answers of their peers. The test also covers retries and clean rounds. PHP syntax and archive/rollback checks are performed separately. This does not establish live ILIAS 11/browser or provider performance; those require host-side verification.

Restore version 1.3.3 with `sh restore-knowledge-memory-1.3.3.sh` from the ILIAS repository root.
