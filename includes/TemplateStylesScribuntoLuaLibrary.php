<?php

namespace MediaWiki\Extension\TemplateStyles;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;

class TemplateStylesScribuntoLuaLibrary extends LibraryBase {
	/** @inheritDoc */
	public function register() {
		$lib = [
			'makeTag' => $this->makeTag( ... ),
		];
		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.TemplateStyles.lua', $lib, []
		);
	}

	/**
	 * Handler for mw.ext.TemplateStyles.link()
	 *
	 * @param string $title
	 * @return string[]
	 */
	private function makeTag( string $title ): array {
		return $this->getEngine()->callParserFunction( 'empty', '#tag:templatestyles', [
			1 => '',
			'src' => $title
		] );
	}
}
