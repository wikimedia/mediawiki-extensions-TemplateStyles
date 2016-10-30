<?php

/**
 * TemplateStyles extension hooks
 *
 * @file
 * @ingroup Extensions
 * @license LGPL-2.0+
 */
class TemplateStylesHooks {
	/**
	 * Register parser hooks
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setHook( 'templatestyles', 'TemplateStylesHooks::render' );
		return true;
	}

	/**
	 * @param string $blob
	 * @return string
	 */
	private static function decodeFromBlob( $blob ) {
		$tree = gzdecode( $blob );
		if ( $tree ) {
			$tree = unserialize( $tree );
		}
		return $tree;
	}

	/**
	 * @param string $tree
	 * @return string
	 */
	private static function encodeToBlob( $tree ) {
		return gzencode( serialize( $tree ) );
	}

	/**
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 */
	public static function onOutputPageParserOutput( &$out, $parserOutput ) {
		$config = \MediaWiki\MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'templatestyles' );
		$renderer = new CSSRenderer();
		$pages = [];

		foreach ( self::getConfigArray( $config, 'Namespaces' ) as $ns ) {
			if ( array_key_exists( $ns, $parserOutput->getTemplates() ) ) {
				foreach ( $parserOutput->getTemplates()[$ns] as $title => $pageid ) {
					$pages[$pageid] = $title;
				}
			}
		}

		if ( count( $pages ) ) {
			$db = wfGetDB( DB_SLAVE );
			$res = $db->select(
				'page_props',
				[ 'pp_page', 'pp_value' ],
				[
					'pp_page' => array_keys( $pages ),
					'pp_propname' => 'templatestyles'
				],
				__METHOD__,
				[ 'ORDER BY', 'pp_page' ]
			);
			foreach ( $res as $row ) {
				$css = self::decodeFromBlob( $row->pp_value );
				if ( $css ) {
					$renderer->add( $css );
				}
			}

		}

		$selfcss = $parserOutput->getProperty( 'templatestyles' );
		if ( $selfcss ) {
			$selfcss = self::decodeFromBlob( $selfcss );
			if ( $selfcss ) {
				$renderer->add( $selfcss );
			}
		}

		$css = $renderer->render(
			self::getConfigArray( $config, 'FunctionWhitelist' ),
			self::getConfigArray( $config, 'PropertyBlacklist' )
		);
		if ( $css ) {
			$out->addInlineStyle( $css );
		}
	}

	/**
	 * Convert a object-style configuration value to a plain array by
	 * returning the array keys from the found configuration where the
	 * associated value is truthy.
	 *
	 * @param Config $config Configuration instance
	 * @param string $name Name of configuration option
	 * @return array Configuration data
	 */
	private static function getConfigArray( Config $config, $name ) {
		return array_keys( array_filter(
			$config->get( "TemplateStyles{$name}" ),
			function ( $val ) {
				return (bool)$val;
			}
		) );
	}

	/**
	 * Parser hook for <templatestyles>.
	 * If there is a CSS provided, render its source on the page and attach the
	 * parsed stylesheet to the page as a Property.
	 *
	 * @param string $input: The content of the tag.
	 * @param array $args: The attributes of the tag.
	 * @param Parser $parser: Parser instance available to render
	 *  wikitext into html, or parser methods.
	 * @param PPFrame $frame: Can be used to see what template parameters ("{{{1}}}", etc.)
	 *  this hook was used with.
	 *
	 * @return string: HTML to insert in the page.
	 */
	public static function render( $input, $args, $parser, $frame ) {
		$css = new CSSParser( $input );

		if ( $css ) {
			$parser->getOutput()->setProperty(
				'templatestyles',
				self::encodeToBlob( $css->rules( '#mw-content-text ' ) )
			);
		}

		// TODO: The UX would benefit from the CSS being run through the
		// hook for syntax highlighting rather that simply being presented
		// as a preformatted block.
		$html =
			Html::openElement( 'div', [ 'class' => 'mw-templatestyles-doc' ] )
			. Html::rawElement(
				'p',
				[ 'class' => 'mw-templatestyles-caption' ],
				wfMessage( 'templatestyles-doc-header' ) )
			. Html::element(
				'pre',
				[ 'class' => 'mw-templatestyles-stylesheet' ],
				$input )
			. Html::closeElement( 'div' );

		return $html;
	}

}
