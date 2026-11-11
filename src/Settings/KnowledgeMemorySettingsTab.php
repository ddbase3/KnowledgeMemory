<?php declare(strict_types=1);

namespace KnowledgeMemory\Settings;

use Base3\Authorization\Api\IFeature;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Permission;

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
