<?php

namespace MediaWiki\Extension\TemplateStyles;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;

class TemplateStylesScribuntoLuaLibrary extends LibraryBase {
	/** @inheritDoc */
	public function register() {
		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.TemplateStyles.lua', [], []
		);
	}
}
