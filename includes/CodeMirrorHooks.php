<?php

namespace MediaWiki\Extension\TemplateStyles;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CodeMirror\Hooks\CodeMirrorGetModeHook;
use MediaWiki\Title\Title;

/**
 * TemplateStyles extension hooks
 * All hooks from the CodeMirror extension which is optional to use with this extension.
 */
class CodeMirrorHooks implements CodeMirrorGetModeHook {

	public function __construct(
		private readonly Config $config,
	) {
	}

	/**
	 * Edit our CSS content model like core's CSS
	 * @param Title $title Title being edited
	 * @param string|null &$mode CodeMirror mode to use
	 * @param string $model Content model
	 * @return bool
	 */
	public function onCodeMirrorGetMode( Title $title, ?string &$mode, string $model ): bool {
		if ( $model === 'sanitized-css' && $this->config->get( 'TemplateStylesUseCodeMirror' ) ) {
			$mode = 'css';
			return false;
		}
		return true;
	}
}
