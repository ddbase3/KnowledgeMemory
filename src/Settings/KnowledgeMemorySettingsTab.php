<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of KnowledgeMemory for BASE3 Framework.
 *
 * KnowledgeMemory provides document-grounded matching games.
 * It manages game settings, pairs and play sessions.
 * Agent execution uses existing BASE3 and AssistantFoundation services.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\KnowledgeMemory\Settings;

use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Permission;

/** Preserves the existing settings permission without registering an administration tab. */
final class KnowledgeMemorySettingsTab implements IFeature {

	public function __construct(private readonly ITranslation $translation) {}

	public static function getName(): string {
		return 'knowledgememorysettingstab';
	}

	public function getLabel(): string {
		return $this->translation->translate('Settings', self::getName(), 'label');
	}

	public function getDescription(): string {
		return $this->translation->translate('Settings', self::getName(), 'description');
	}

	public function getPermissions(): array {
		return [Permission::for('feature', self::getName())];
	}

	public function getDefaultAudience(): array {
		return ['group' => 'administration'];
	}
}
