<?php

/**
 * @file
 * @license https://opensource.org/licenses/GPL-2.0 GPL-2.0+
 */

/**
 * Content handler for sanitized CSS
 */
class TemplateStylesContentHandler extends CodeContentHandler {

	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId = 'sanitized-css' ) {
		parent::__construct( $modelId, [ CONTENT_FORMAT_CSS ] );
	}

	protected function getContentClass() {
		return TemplateStylesContent::class;
	}
}
