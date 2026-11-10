<?php

namespace MediaWiki\Extension\TemplateStyles;

/**
 * @file
 * @license GPL-2.0-or-later
 */

use MediaWiki\Config\Config;
use MediaWiki\Extension\CodeEditor\Hooks\CodeEditorGetPageLanguageHook;
use MediaWiki\Title\Title;

/**
 * TemplateStyles extension hooks
 * All hooks from the CodeEditor extension which is optional to use with this extension.
 */
class CodeEditorHooks implements
	CodeEditorGetPageLanguageHook
{

	public function __construct(
		private readonly Config $config,
	) {
	}

	/**
	 * Edit our CSS content model like core's CSS
	 * @inheritDoc
	 */
	public function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, string $model, string $format ): bool {
		if ( $model === 'sanitized-css' && $this->config->get( 'TemplateStylesUseCodeEditor' ) ) {
			$lang = 'css';
			return false;
		}
		return true;
	}
}
