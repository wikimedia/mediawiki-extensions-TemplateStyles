<?php

/**
 * @file
 * @license GPL-2.0-or-later
 */

use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\MediaWikiServices;

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

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return TemplateStylesContent::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$output
	) {
		'@phan-var TemplateStylesContent $content';
		$services = MediaWikiServices::getInstance();
		$page = $cpoParams->getPage();
		$parserOptions = $cpoParams->getParserOptions();

		// Inject our warnings into the resulting ParserOutput
		parent::fillParserOutput( $content, $cpoParams, $output );

		if ( $cpoParams->getGenerateHtml() ) {
			$html = "";
			$html .= "<pre class=\"mw-code mw-css\" dir=\"ltr\">\n";
			$html .= htmlspecialchars( $content->getNativeData(), ENT_NOQUOTES );
			$html .= "\n</pre>\n";
		} else {
			$html = '';
		}

		$output->clearWrapperDivClass();
		$output->setText( $html );

		$status = $content->sanitize( [ 'novalue' => true, 'class' => $parserOptions->getWrapOutputClass() ] );
		if ( $status->getErrors() ) {
			foreach ( $status->getErrors() as $error ) {
				$output->addWarningMsg( $error['message'], $error['params'] );
			}
			$services->getTrackingCategories()->addTrackingCategory(
				$output,
				'templatestyles-stylesheet-error-category',
				$page
			);
		}
	}
}
