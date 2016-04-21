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

	/** @var array $byMedia */
	private $byMedia;

	function __construct() {
		$this->byMedia = [];
	}

	/**
	 * Adds (and merge) a parsed CSS tree to the render list.
	 *
	 * @param array $rules The parsed tree as created by CSSParser::rules()
	 * @param string $media Forcibly specified @media block selector.
	 */
	function add( $rules, $media = '' ) {
		if ( !array_key_exists( $media, $this->byMedia ) ) {
			$this->byMedia[$media] = [];
		}

		foreach ( $rules as $rule ) {
			switch ( strtolower( $rule['name'] ) ) {
				case '@media':
					if ( $media == '' ) {
						$this->add(
							$rule['rules'], "@media {$rule['text']}"
						);
					}
					break;
				case '':
					$this->byMedia[$media] = array_merge(
						$this->byMedia[$media], $rule['rules']
					);
					break;
			}
		}
	}

	/**
	 * Render the collected CSS trees into a string suitable for inclusion
	 * in a <style> tag.
	 *
	 * @param array $functionWhitelist List of functions that are allowed
	 * @param array $propertyBlacklist List of properties that not allowed
	 * @return string Rendered CSS
	 */
	function render(
		array $functionWhitelist = [],
		array $propertyBlacklist = []
	) {
		// Normalize whitelist and blacklist values to lowercase
		$functionWhitelist = array_map( 'strtolower', $functionWhitelist );
		$propertyBlacklist = array_map( 'strtolower', $propertyBlacklist );

		$css = '';
		foreach ( $this->byMedia as $media => $rules ) {
			if ( $media !== '' ) {
				$css .= "{$media} {\n";
			}
			foreach ( $rules as $rule ) {
				if ( $rule !== null ) {
					$css .= $this->renderRule(
						$rule, $functionWhitelist, $propertyBlacklist );
				}
			}
			if ( $media !== '' ) {
				$css .= '} ';
			}
		}
		return $css;
	}

	/**
	 * Render a single rule.
	 *
	 * @param array $rule Parsed rule
	 * @param array $functionWhitelist List of functions that are allowed
	 * @param array $propertyBlacklist List of properties that not allowed
	 * @return string Rendered CSS
	 */
	private function renderRule(
		array $rule,
		array $functionWhitelist,
		array $propertyBlacklist
	) {
		$css = '';
		if ( $rule &&
			array_key_exists( 'selectors', $rule ) &&
			array_key_exists( 'decls', $rule )
		) {
			$css .= implode( ',', $rule['selectors'] ) . '{';
			foreach ( $rule['decls'] as $prop => $values ) {
				$css .= $this->renderDecl(
					$prop, $values, $functionWhitelist, $propertyBlacklist );
			}
			$css .= '} ';
		}
		return $css;
	}

	/**
	 * Render a property declaration.
	 *
	 * @param string $prop Property name
	 * @param array $values Parsed property values
	 * @param array $functionWhitelist List of functions that are allowed
	 * @param array $propertyBlacklist List of properties that not allowed
	 * @return string Rendered CSS
	 */
	private function renderDecl(
		$prop,
		array $values,
		array $functionWhitelist,
		array $propertyBlacklist
	) {
		if ( in_array( strtolower( $prop ), $propertyBlacklist ) ) {
			// Property is blacklisted
			return '';
		}
		foreach ( $values as $value ) {
			if ( preg_match( '/^ (\S+) \s* \( $/x', $value, $match ) ) {
				if ( !in_array( strtolower( $match[1] ), $functionWhitelist ) ) {
					// Function is blacklisted
					return '';
				}
			}
		}
		return $prop . ':' . implode( '', $values ) . ';';
	}
}
