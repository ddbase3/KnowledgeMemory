<?php
	$id = htmlspecialchars($this->_['root_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$t = $this->_['texts'];
	$s = $this->_['settings'];
	$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$config = json_encode([
		'apiUrl' => $this->_['api_url'],
		'saveFailed' => $t['save_failed']
	], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<link rel="stylesheet" href="<?php echo $esc($this->_['css_url']); ?>">
<section class="km-settings" id="<?php echo $id; ?>" data-km-settings-config="<?php echo $esc($config); ?>">
	<h2><?php echo $esc($t['settings_title']); ?></h2>
	<p><?php echo $esc($t['settings_help']); ?></p>
	<p><a href="<?php echo $esc($this->_['game_url']); ?>"><?php echo $esc($t['open_game']); ?></a></p>
	<form data-km-settings-form>
		<div class="km-settings__field">
			<label for="<?php echo $id; ?>-agent"><?php echo $esc($t['agent']); ?></label>
		<select id="<?php echo $id; ?>-agent" name="agent_id" required>
			<option value=""><?php echo $esc($t['choose_agent']); ?></option>
			<?php foreach ($this->_['agents'] as $agentId => $label) { ?>
				<option value="<?php echo $esc($agentId); ?>" <?php echo $s['agent_id'] === $agentId ? 'selected' : ''; ?>><?php echo $esc($label); ?></option>
			<?php } ?>
		</select>
		</div>
		<div class="km-settings__field">
			<label for="<?php echo $id; ?>-pairs"><?php echo $esc($t['pair_count']); ?></label>
		<select id="<?php echo $id; ?>-pairs" name="pairs">
			<?php foreach ([4, 6, 8, 10, 12] as $count) { ?>
				<option value="<?php echo $count; ?>" <?php echo $s['pairs'] === $count ? 'selected' : ''; ?>><?php echo $count; ?></option>
			<?php } ?>
		</select>
		</div>
		<div class="km-settings__field">
			<label for="<?php echo $id; ?>-difficulty"><?php echo $esc($t['difficulty']); ?></label>
		<select id="<?php echo $id; ?>-difficulty" name="difficulty">
			<?php foreach (['easy', 'normal', 'hard'] as $level) { ?>
				<option value="<?php echo $level; ?>" <?php echo $s['difficulty'] === $level ? 'selected' : ''; ?>><?php echo $esc($t['difficulty_' . $level]); ?></option>
			<?php } ?>
		</select>
		</div>
		<button type="submit"><?php echo $esc($t['save']); ?></button>
		<p aria-live="polite" data-km-settings-message></p>
	</form>
</section>
<script src="<?php echo $esc($this->_['js_url']); ?>"></script>
