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
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setHook( 'templatestyles', 'TemplateStylesHooks::render' );
		return true;
	}

	private static function decodeFromBlob( $blob ) {
		$tree = gzdecode( $blob );
		if ( $tree ) {
			$tree = unserialize( $tree );
		}
		return $tree;
	}

	private static function encodeToBlob( $tree ) {
		return gzencode( serialize( $tree ) );
	}

	public static function onOutputPageParserOutput( &$out, $parseroutput ) {
		global $wgTemplateStylesNamespaces;
		if ( $wgTemplateStylesNamespaces ) {
			$namespaces = $wgTemplateStylesNamespaces;
		} else {
			$namespaces = [ NS_TEMPLATE ];
		}

		$renderer = new CSSRenderer();
		$pages = [];

		foreach ( $namespaces as $ns ) {
			if ( array_key_exists( $ns, $parseroutput->getTemplates() ) ) {
				foreach ( $parseroutput->getTemplates()[$ns] as $title => $pageid ) {
					$pages[$pageid] = $title;
				}
			}
		}

		if ( count( $pages ) ) {
			$db = wfGetDB( DB_SLAVE );
			$res = $db->select( 'page_props', [ 'pp_page', 'pp_value' ], [
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

		$selfcss = $out->getProperty( 'templatestyles' );
		if ( $selfcss ) {
			$selfcss = self::decodeFromBlob( unserialize( gzdecode( $selfcss ) ) );
			if ( $selfcss ) {
				$renderer->add( $selfcss );
			}
		}

		$css = $renderer->render();
		if ( $css ) {
			$out->addInlineStyle( $css );
		}
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
			$parser->getOutput()->setProperty( 'templatestyles', self::encodeToBlob( $css->rules() ) );
		}

		// TODO: The UX would benefit from the CSS being run through the
		// hook for syntax highlighting rather that simply being presented
		// as a preformatted block.
		$html =
			Html::openElement( 'div', [ 'class' => 'mw-templatestyles-doc' ] )
			. Html::rawElement(
				'p',
				[ 'class' => 'mw-templatestyles-caption' ],
				wfMessage( 'templatedata-doc-title' ) )
			. Html::element(
				'pre',
				[ 'class' => 'mw-templatestyles-stylesheet' ],
				$input )
			. Html::closeElement( 'div' );

		return $html;
	}

}
