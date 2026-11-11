<?php
	$id = htmlspecialchars($this->_['root_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$t = $this->_['texts'];
	$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$config = json_encode([
		'apiUrl' => $this->_['api_url'],
		'texts' => $t
	], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<link rel="stylesheet" href="<?php echo $esc($this->_['css_url']); ?>">
<section id="<?php echo $id; ?>" class="km-game" aria-label="<?php echo $esc($t['title']); ?>" data-km-game-config="<?php echo $esc($config); ?>">
	<div class="km-game__shell">
		<div class="km-game__play">
			<aside class="km-game__sidebar" aria-label="<?php echo $esc($t['title']); ?>">
				<header class="km-game__header">
					<div class="km-game__identity">
						<div class="km-game__brand">
							<span class="km-game__brand-symbol" aria-hidden="true">✦</span>
							<span class="km-game__eyebrow"><?php echo $esc($t['eyebrow']); ?></span>
						</div>
						<h2><?php echo $esc($t['title']); ?></h2>
						<p class="km-game__lead"><?php echo $esc($t['introduction']); ?></p>
					</div>
					<div class="km-game__actions">
						<div class="km-game__actions-buttons">
							<button class="km-game__start" data-km-action="start" type="button"><?php echo $esc($t['start']); ?></button>
							<button class="km-game__fullscreen" data-km-action="fullscreen" type="button" aria-label="<?php echo $esc($t['fullscreen']); ?>" aria-pressed="false" title="<?php echo $esc($t['fullscreen']); ?>"><span aria-hidden="true">⛶</span></button>
						</div>
						<p class="km-game__actions-hint"><?php echo $esc($t['start_hint']); ?></p>
					</div>
				</header>

				<?php if ($this->_['settings_html'] !== '') { ?>
					<details class="km-game__configure">
						<summary><?php echo $esc($t['configure_game']); ?></summary>
						<?php echo $this->_['settings_html']; ?>
					</details>
				<?php } ?>
				<div class="km-game__stats" role="group" aria-label="<?php echo $esc($t['statistics']); ?>">
					<div class="km-game__stat km-game__stat--score">
						<span class="km-game__stat-icon" aria-hidden="true">✦</span>
						<div class="km-game__stat-text">
							<span><?php echo $esc($t['points']); ?></span>
							<strong data-km-stat="score">0</strong>
						</div>
						<span class="km-game__score-pop" data-km-score-pop aria-hidden="true"></span>
					</div>
					<div class="km-game__stat">
						<span class="km-game__stat-icon km-game__stat-icon--mint" aria-hidden="true">◇</span>
						<div class="km-game__stat-text">
							<span><?php echo $esc($t['matches']); ?></span>
							<strong data-km-stat="matches">0/0</strong>
						</div>
					</div>
					<div class="km-game__stat">
						<span class="km-game__stat-icon km-game__stat-icon--peach" aria-hidden="true">◷</span>
						<div class="km-game__stat-text">
							<span><?php echo $esc($t['time']); ?></span>
							<strong data-km-stat="time">00:00</strong>
						</div>
					</div>
				</div>

				<div class="km-game__progress-wrap">
					<div class="km-game__progress-label">
						<span><?php echo $esc($t['progress']); ?></span>
						<strong data-km-percent>0%</strong>
					</div>
					<div class="km-game__progress" role="progressbar" aria-label="<?php echo $esc($t['progress']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-km-progress>
						<span data-km-progress-fill></span>
					</div>
				</div>
				<div class="km-game__result" role="region" tabindex="-1" aria-label="<?php echo $esc($t['result_label']); ?>" data-km-result hidden></div>
			</aside>
			<section class="km-game__arena" aria-label="<?php echo $esc($t['board']); ?>">
				<div class="km-game__arena-header">
					<div class="km-game__arena-label">
						<span class="km-game__arena-kicker"><?php echo $esc($t['board_kicker']); ?></span>
						<span class="km-game__layout-name"><span class="km-game__layout-dot" aria-hidden="true"></span><span data-km-layout-label><?php echo $esc($t['layout_pending']); ?></span></span>
					</div>
					<span class="km-game__combo" data-km-combo hidden></span>
				</div>
				<p class="km-game__feedback" role="status" aria-live="polite" aria-atomic="true" data-km-feedback><?php echo $esc($t['ready']); ?></p>
				<p class="km-game__sr-only" role="status" aria-live="polite" aria-atomic="true" data-km-announcement></p>
				<div class="km-game__board-shell">
					<div class="km-game__empty" data-km-empty>
						<div class="km-game__empty-art" aria-hidden="true"><span>✦</span><span>✦</span><span>✦</span></div>
						<strong data-km-empty-title><?php echo $esc($t['empty_title']); ?></strong>
						<p><?php echo $esc($t['empty_subtitle']); ?></p>
					</div>
					<div class="km-game__seed-stage" data-km-seed-stage hidden>
						<div class="km-game__seed-heading"><span class="km-game__seed-spark" aria-hidden="true">✦</span><strong data-km-seed-heading><?php echo $esc($t['loading_keywords']); ?></strong></div>
						<div class="km-game__seed-cloud" data-km-seed-cloud aria-hidden="true"></div>
						<div class="km-game__seed-combinations" data-km-seed-combinations></div>
					</div>
					<div class="km-game__board" role="group" aria-label="<?php echo $esc($t['board']); ?>" data-km-board></div>
				</div>
				<p class="km-game__board-hint"><?php echo $esc($t['board_hint']); ?></p>
			</section>
		</div>
	</div>
</section>
<script src="<?php echo $esc($this->_['js_url']); ?>"></script>
