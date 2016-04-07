<?php
/**
 * @file
 * @ingroup Extensions
 */

/**
 * Represents a style sheet as a structured tree, organized
 * in rule blocks nested in at-rule blocks.
 *
 * @class
 */
class CSSParser {

	private $tokens;
	private $index;

	/**
	 * Parse and (minimally) validate the passed string as a CSS, and
	 * constructs an array of tokens for parsing, as well as an index
	 * into that array.
	 *
	 * Internally, the class behaves as a lexer.
	 *
	 * @param string $css
	 */
	function __construct( $css ) {
		preg_match_all( '/(
			  [ \n\t]+
			| \/\* (?: [^*]+ | \*[^\/] )* \*\/ [ \n\t]*
			| " (?: [^"\\\\\n]+ | \\\\\. )* ["\n]
			| \' (?: [^\'\\\\\n]+ | \\\\\. )* [\'\n]
			| [+-]? (?: [0-9]* \. )? [0-9]+ (?: [_a-z][_a-z0-9-]* | % )?
			| url [ \n\t]* \(
			| @? -? (?: [_a-z] | \\\\[0-9a-f]{1,6} [ \n\t]? )
			        (?: [_a-z0-9-]+ | \\\\[0-9a-f]{1,6} [ \n\t]? | [^\0-\177] )*
			| \# (?: [_a-z0-9-]+ | \\\\[0-9a-f]{1,6} [ \n\t]? | [^\0-\177] )*
			| u\+ [0-9a-f]{1,6} (?: - [0-9a-f]{1,6} )?
			| u\+ [0-9a-f?]{1,6}
			| <!--
			| -->
			| .)/xis', $css, $match );

		$space = false;
		foreach ( $match[0] as $t ) {
			if ( preg_match( '/^(?:[ \n\t]|\/\*|<!--|-->)/', $t ) ) {
				if ( !$space ) {
					$space = true;
					$this->tokens[] = ' ';
					continue;
				}
			} else {
				$space = false;
				$this->tokens[] = $t;
			}
		}
		$this->index = 0;
	}

	private function peek( $i ) {
		if ( $this->index+$i >= count( $this->tokens ) )
			return null;
		return $this->tokens[$this->index+$i];
	}

	private function consume( $num = 1 ) {
		if ( $num > 0 ) {
			if ( $this->index+$num >= count( $this->tokens ) )
				$num = count( $this->tokens ) - $this->index;
			$text = implode( array_slice( $this->tokens, $this->index, $num ) );
			$this->index += $num;
			return $text;
		}
		return '';
	}

	private function consumeTo( $delim ) {
		$consume = 0;
		while ( !in_array( $this->peek( $consume ), $delim ) )
			$consume++;
		return $this->consume( $consume );
	}

	private function consumeWS() {
		$consume = 0;
		while ( $this->peek( $consume ) === ' ' )
			$consume++;
		return $this->consume( $consume );
	}

	/**
	 * Parses:
	 *		decl		: WS* IDENT WS* ':' TOKEN* ';'
	 *					| WS* IDENT <error> ';'			-> skip
	 *					;
	 *
	 * Returns:
	 *			[ name => value ]
	 */
	private function parseDecl() {
		$this->consumeWS();
		$name = $this->consume();
		$this->consumeWS();
		if ( $this->peek( 0 )!=':' ) {
			$this->consumeTo( [';', '}', null] );
			if ( $this->peek( 0 ) == ';' ) {
				$this->consume();
				$this->consumeWS();
			}
			return null;
		}
		$this->consume();
		$this->consumeWS();
		$value = $this->consumeTo( [';', '}', null] );
		if ( $this->peek( 0 ) == ';' ) {
			$value .= $this->consume();
			$this->consumeWS();
		}
		return [ $name => $value ];
	}

	/**
	 * Parses:
	 *		decls		: '}'
	 *					| decl decls
	 *					;
	 *
	 * Returns:
	 *			[ decl* ]
	 */
	private function parseDecls() {
		$decls = [];
		while ( $this->peek( 0 ) !== null and $this->peek( 0 ) != '}' ) {
			$decl = $this->parseDecl();
			if ( $decl )
				foreach ( $decl as $k => $d )
					$decls[$k] = $d;
		}
		if ( $this->peek( 0 ) == '}' )
			$this->consume();
		return $decls;
	}

	/**
	 * Parses:
	 *		rule		: WS* selectors ';'
	 *					| WS* selectors '{' decls
	 *					;
	 *		selectors	: TOKEN*
	 *					| selectors ',' TOKEN*
	 *					;
	 *
	 * Returns:
	 *			[ selectors => [ selector* ], decls => [ decl* ] ]
	 */
	public function parseRule() {
		$selectors = [];
		$text = '';
		$this->consumeWS();
		while ( !in_array( $this->peek( 0 ), ['{', ';', null] ) ) {
			if ( $this->peek( 0 ) == ',' ) {
				$selectors[] = $text;
				$this->consume();
				$this->consumeWS();
				$text = '';
			} else
				$text .= $this->consume();
		}
		$selectors[] = $text;
		if ( $this->peek( 0 ) == '{' ) {
			$this->consume();
			return [ "selectors"=>$selectors, "decls"=>$this->parseDecls() ];
		}
		return null;
	}

	/**
	 * Parses the token array, and returns a tree representing the CSS suitable
	 * for feeding CSSRenderer objects.
	 *
	 * @param array $end An array of string representing tokens that can end the parse.  Defaults
	 *  to ending only at the end of the string.
	 * @return array A tree describing the CSS rule blocks.
	 *
	 * Parses:
	 *		anyrule			: ATIDENT='@media' WS* TOKEN* '{' rules '}'
	 *						| ATIDENT WS* TOKEN* ';'
	 *						| ATIDENT WS* TOKEN* '{' decls '}'
	 *						| rule
	 *						;
	 *		rules			: anyrule
	 *						| rules anyrule
	 *						;
	 *
	 * Returns:
	 *			[ [ name=>ATIDENT? , text=>body? , rules=>rules? ]* ]
	 */
	public function rules( $end = [ null ] ) {
		$atrules = [];
		$rules = [];
		$this->consumeWS();
		while ( !in_array( $this->peek( 0 ), $end ) ) {
			if ( in_array( $this->peek( 0 ), [ '@media' ] ) ) {
				$at = $this->consume();
				$this->consumeWS();
				$text = '';
				while ( !in_array( $this->peek( 0 ), ['{', ';', null] ) )
					$text .= $this->consume();
				if ( $this->peek( 0 ) == '{' ) {
					$this->consume();
					$r = $this->rules( [ '}', null ] );
					if ( $r )
						$atrules[] = [ "name"=>$at, "text"=>$text, "rules"=>$r ];
				} else {
					$atrules[] = [ "name"=>$at, "text"=>$text ];
				}
			} elseif ( $this->peek( 0 )[0] == '@' ) {
				$at = $this->consume();
				$text = '';
				while ( !in_array( $this->peek( 0 ), ['{', ';', null] ) )
					$text .= $this->consume();
				if ( $this->peek( 0 ) == '{' ) {
					$this->consume();
					$decl = $this->parseDecls();
					if ( $decl )
						$atrules[] = [ "name"=>$at, "text"=>$text, "rules"=>[ "selectors"=>'', "decls"=>$decl ] ];
				} else {
					$atrules[] = [ "name"=>$at, "text"=>$text ];
				}
			} else
				$rules[] = $this->parseRule();
			$this->consumeWS();
		}
		if ( $rules )
			$atrules[] = [ "name"=>'', "rules"=>$rules ];
		if ( $this->peek( 0 ) !== null )
			$this->consume();
		return $atrules;
	}

}

