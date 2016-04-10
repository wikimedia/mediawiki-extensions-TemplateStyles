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

	/** @var array $tokens */
	private $tokens;
	/** @var int $index */
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
		$this->index = 0;
		preg_match_all( '/(
			  [ \n\t]+
				(?# Sequences of whitespace )
			| \/\* (?: [^*]+ | \*[^\/] )* \*\/ [ \n\t]*
				(?# Comments and any trailing whitespace )
			| " (?: [^"\\\\\n]+ | \\\\. )* ["\n]
				(?# Double-quoted string literals (to newline when unclosed )
			| \' (?: [^\'\\\\\n]+ | \\\\. )* [\'\n]
				(?# Single-quoted string literals (to newline when unclosed )
			| [+-]? (?: [0-9]* \. )? [0-9]+ (?: [_a-z][_a-z0-9-]* | % )?
				(?# Numerical literals - including optional trailing units or percent sign )
			| @? -? (?: [_a-z] | \\\\[0-9a-f]{1,6} [ \n\t]? )
			        (?: [_a-z0-9-]+ | \\\\[0-9a-f]{1,6} [ \n\t]? | [^\0-\177] )* (?: [ \n\t]* \( )?
				(?# Identifiers - including leading `@` for at-rule blocks )
				(?# Trailing open captures are captured to match functional values )
			| \# (?: [_a-z0-9-]+ | \\\\[0-9a-f]{1,6} [ \n\t]? | [^\0-\177] )*
				(?# So-called hatch literals )
			| u\+ [0-9a-f]{1,6} (?: - [0-9a-f]{1,6} )?
				(?# Unicode range literals )
			| u\+ [0-9a-f?]{1,6}
				(?# Unicode mask literals )
			| .
				(?# Any unmatched token is reduced to single characters )
			)/xis', $css, $match );

		$inWhitespaceRun = false;
		foreach ( $match[0] as $token ) {
			if ( preg_match( '/^(?: [ \n\t] | \/\* )/x', $token ) ) {
				// Fold any sequence of whitespace to a single space token
				if ( !$inWhitespaceRun ) {
					$inWhitespaceRun = true;
					$this->tokens[] = ' ';
					continue;
				}

			} else {
				// Decode any hexadecimal escape character into its
				// corresponding UTF-8 sequence - output is UTF-8 so the
				// escaping is unnecessary and this prevents trying to
				// obfuscate ASCII in identifiers to prevent matches.
				$token = preg_replace_callback(
					'/\\\\([0-9a-f]{1,6})[ \n\t]?/',
					function( $match ) {
						return html_entity_decode(
							'&#x' . $match[1] . ';', ENT_NOQUOTES, 'UTF-8' );
					},
					$token
				);

				// Close unclosed string literals
				if ( preg_match( '/^ ([\'"]) (.*) \n $/x', $token, $match ) ) {
					$token = $match[1] . $match[2] . $match[1];
				}
				$inWhitespaceRun = false;
				$this->tokens[] = $token;
			}
		}
	}

	/**
	 * Get a token from the input stream without advancing the current
	 * position.
	 *
	 * @param int $offset Offset from current stream location
	 * @return string|null Token or null if offset is past the end of the
	 *     input stream
	 */
	private function peek( $offset = 0 ) {
		if ( ( $this->index + $offset ) >= count( $this->tokens ) ) {
			return null;
		}
		return $this->tokens[$this->index + $offset];
	}

	/**
	 * Take a list of tokens from the input stream.
	 *
	 * @param int $num Number of tokens to take
	 * @return array List of tokens
	 */
	private function consume( $num = 1 ) {
		if ( $num > 0 ) {
			if ( $this->index+$num >= count( $this->tokens ) ) {
				$num = count( $this->tokens ) - $this->index;
			}
			$text = array_slice( $this->tokens, $this->index, $num );
			$this->index += $num;
			return $text;
		}
		return [];
	}

	/**
	 * Take tokens from the input stream up to but not including a delimiter
	 * from the provided list.
	 *
	 * The next token in the stream should always be validated after using
	 * this function as it may return early if the end of the token stream is
	 * reached.
	 *
	 * @param array $delim List of delimiters
	 * @return array List of tokens
	 */
	private function consumeTo( $delim ) {
		if ( !in_array( null, $delim ) ) {
			// Make sure we don't hit an infinte loop on malformed input
			$delim[] = null;
		}
		$consume = 0;
		while ( !in_array( $this->peek( $consume ), $delim ) ) {
			$consume++;
		}
		return $this->consume( $consume );
	}

	/**
	 * Take consecutive whitespace tokens from the input stream.
	 *
	 * @return array List of whitespace tokens
	 */
	private function consumeWS() {
		$consume = 0;
		while ( $this->peek( $consume ) === ' ' ) {
			$consume++;
		}
		return $this->consume( $consume );
	}

	/**
	 * Parse a CSS declaration.
	 *
	 * Grammar:
	 *
	 *     decl : WS* IDENT WS* ':' TOKEN* ';'
	 *          | WS* IDENT <error> ';'        -> skip
	 *          ;
	 *
	 * @return array [ name => value ]
	 */
	private function parseDecl() {
		$this->consumeWS();
		$name = $this->consume()[0];
		$this->consumeWS();
		if ( $this->peek() != ':' ) {
			$this->consumeTo( [ ';', '}' ] );
			if ( $this->peek() ) {
				$this->consume();
				$this->consumeWS();
			}
			return null;
		}
		$this->consume();
		$this->consumeWS();
		$value = $this->consumeTo( [ ';', '}' ] );
		if ( $this->peek() === ';' ) {
			$this->consume();
			$this->consumeWS();
		}
		return [ $name => $value ];
	}

	/**
	 * Parse a list of CSS declarations.
	 *
	 * Grammar:
	 *
	 *     decls : '}'
	 *           | decl decls
	 *           ;
	 *
	 * @return array List of decls
	 * @see parseDecl
	 */
	private function parseDecls() {
		$decls = [];
		while ( $this->peek() !== null && $this->peek() != '}' ) {
			$decl = $this->parseDecl();
			if ( $decl ) {
				foreach ( $decl as $k => $d ) {
					$decls[$k] = $d;
				}
			}
		}
		return $decls;
	}

	/**
	 * Parse a CSS rule.
	 *
	 * Grammar:
	 *
	 *     rule      : WS* selectors ';'
	 *               | WS* selectors '{' decls
	 *               ;
	 *     selectors : TOKEN*
	 *               | selectors ',' TOKEN*
	 *               ;
	 *
	 * @param string $baseSelectors Selector to prepend to all rules to
	 *     enforce scoping.
	 * @return array|null [ selectors => [ selector* ], decls => [ decl* ] ]
	 */
	public function parseRule( $baseSelectors ) {
		$selectors = [];
		$text = '';
		$this->consumeWS();
		while ( !in_array( $this->peek(), [ '{', ';', null ] ) ) {
			if ( $this->peek() === ',' ) {
				if ( $text !== '' ) {
					$selectors[] = "{$baseSelectors}{$text}";
				}
				$this->consume();
				$this->consumeWS();
				$text = '';
			} else {
				$text .= $this->consume()[0];
			}
		}
		if ( $text !== '' ) {
			$selectors[] = "{$baseSelectors}{$text}";
		}
		if ( $this->peek() === '{' ) {
			$this->consume();
			return [
				'selectors' => $selectors,
				'decls' => $this->parseDecls()
			];
		}
		if ( $this->peek( 0 ) ) {
			$this->consume();
		}
		return null;
	}

	/**
	 * Parses the token array, and returns a tree representing the CSS
	 * suitable for feeding CSSRenderer objects.
	 *
	 * Grammar:
	 *
	 *     anyrule : ATIDENT='@media' WS* TOKEN* '{' rules '}'
	 *             | ATIDENT WS* TOKEN* ';'
	 *             | ATIDENT WS* TOKEN* '{' decls '}'
	 *             | rule
	 *             ;
	 *     rules   : anyrule
	 *             | rules anyrule
	 *             ;
	 *
	 * Output:
	 *
	 *     [ [ name=>ATIDENT? , text=>body? , rules=>rules? ]* ]
	 *
	 * @param string $baseSelectors Selector to prepend to all rules to
	 *     enforce scoping.
	 * @param array $end An array of string representing tokens that can end
	 *     the parse.  Defaults to ending only at the end of the string.
	 * @return array A tree describing the CSS rule blocks.
	 */
	public function rules( $baseSelectors = '', $end = [ null ] ) {
		$atrules = [];
		$rules = [];
		$this->consumeWS();
		while ( !in_array( $this->peek(), $end ) ) {
			if ( strtolower( $this->peek() ) === '@media' ) {
				$at = $this->consume()[0];
				$this->consumeWS();

				$text = '';
				while ( !in_array( $this->peek(), [ '{', ';', null ] ) ) {
					$text .= $this->consume()[0];
				}

				if ( $this->peek() === '{' ) {
					$this->consume();
					$r = $this->rules( $baseSelectors, [ '}', null ] );
					if ( $r ) {
						$atrules[] = [
							'name' => $at,
							'text' => $text,
							'rules' => $r,
						];
					}

				} else {
					$atrules[] = [
						'name' => $at,
						'text' => $text,
					];
				}
			} elseif ( $this->peek()[0] === '@' ) {
				$at = $this->consume()[0];
				$text = '';
				while ( !in_array( $this->peek(), [ '{', ';', null ] ) ) {
					$text .= $this->consume()[0];
				}
				if ( $this->peek() === '{' ) {
					$this->consume();
					$decl = $this->parseDecls();
					if ( $decl ) {
						$atrules[] = [
							'name' => $at,
							'text' => $text,
							'rules' => [
								'selectors' => '',
								'decls' => $decl,
							],
						];
					}
				} else {
					$atrules[] = [
						'name' => $at,
						'text' => $text,
					];
				}
			} elseif ( $this->peek() === '}' ) {
				$this->consume();
			} else {
				$rules[] = $this->parseRule( $baseSelectors );
			}
			$this->consumeWS();
		}
		if ( $rules ) {
			$atrules[] = [
				'name' => '',
				'rules' => $rules,
			];
		}
		$this->consumeWS();
		if ( $this->peek() !== null ) {
			$this->consume();
		}
		return $atrules;
	}
}

