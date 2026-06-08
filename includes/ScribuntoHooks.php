<?php

namespace MediaWiki\Extension\TemplateStyles;

use MediaWiki\Extension\Scribunto\Hooks\ScribuntoExternalLibrariesHook;

class ScribuntoHooks implements ScribuntoExternalLibrariesHook {

	/**
	 * External Lua library for Scribunto
	 *
	 * @param string $engine
	 * @param array &$extraLibraries
	 */
	public function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.TemplateStyles'] = TemplateStylesScribuntoLuaLibrary::class;
		}
	}
}
