<?php


/**
 * @file
 * @ingroup Extensions
 */

/**
 * Collects parsed CSS trees, and merges them for rendering into text.
 *
 * @class
 */
class CSSRenderer {

	private	$bymedia;

	function __construct() {
		$this->bymedia = [];
	}

	/**
	 * Adds (and merge) a parsed CSS tree to the render list.
	 *
	 * @param array $rules The parsed tree as created by CSSParser::rules()
	 * @param string $media Forcibly specified @media block selector.  Normally unspecified
	 *  and defaults to the empty string.
	 */
	function add( $rules, $media = '' ) {
		if ( !array_key_exists( $media, $this->bymedia ) )
			$this->bymedia[$media] = [];

		foreach ( $rules as $at ) {
			switch ( $at['name'] ) {
				case '@media':
					if ( $media == '' )
						$this->add( $at['rules'], "@media ".$at['text'] );
					break;
				case '':
					$this->bymedia[$media] = array_merge( $this->bymedia[$media], $at['rules'] );
					break;
			}
		}
	}

	/**
	 * Renders the collected CSS trees into a string suitable for inclusion
	 * in a <style> tag.
	 *
	 * @return string Rendered CSS
	 */
	function render() {

		$css = '';

		foreach ( $this->bymedia as $at => $rules ) {
			if ( $at != '' )
				$css .= "$at {\n";
			foreach ( $rules as $rule ) {
				$css .= implode( ',', $rule['selectors'] ) . "{";
				foreach ( $rule['decls'] as $key => $value ) {
					$css .= "$key:$value";
				}
				$css .= "} ";
			}
			if ( $at != '' )
				$css .= "} ";
		}

		return $css;
	}

}


