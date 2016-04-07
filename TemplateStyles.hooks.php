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
		$parser->setHook( 'templatestyles', array( 'TemplateStylesHooks', 'render' ) );
		return true;
	}

	public static function onOutputPageParserOutput( &$out, $parseroutput ) {
		global $wgTemplateStylesNamespaces;
		if ( $wgTemplateStylesNamespaces )
			$namespaces = $wgTemplateStylesNamespaces;
		else
			$namespaces = [ NS_TEMPLATE ];

		$renderer = new CSSRenderer();
		$pages = [];

		if ( $out->canUseWikiPage() )
			$pages[$out->getWikiPage()->getID()] = 'self';

		foreach ( $namespaces as $ns )
			if ( array_key_exists( $ns, $parseroutput->getTemplates() ) )
				foreach ( $parseroutput->getTemplates()[$ns] as $title => $pageid )
					$pages[$pageid] = $title;

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
				$css = unserialize( gzdecode( $row->pp_value ) );
				$renderer->add( $css );
			}

		}

		$selfcss = $out->getProperty( 'templatestyles' );
		if ( $selfcss ) {
			$selfcss = unserialize( gzdecode( $selfcss ) );
			$renderer->add( $selfcss );
		}

		$css = $renderer->render();
		if ( $css )
			$out->addInlineStyle( $css );
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

		if ( $css )
			$parser->getOutput()->setProperty( 'templatestyles', gzencode( serialize( $css->rules() ) ) );

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
