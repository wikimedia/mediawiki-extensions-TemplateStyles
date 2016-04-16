<?php

/**
 * @group TemplateStyles
 */
class CSSParseRenderTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();
	}

	public static function provideCSSParser() {
		return [
			[	'test' => 'Bare declaration',
				'expect' => '',
				'css' => <<<FIN
					prop: val;
FIN
			],
			[	'test' => 'Blacklisted property',
				'expect' => '.X .sel {good:123;} ',
				'css' => <<<FIN
					.sel {
					  good: 123;
					  -evil: "boo";
				}
FIN
			],
			[	'test' => 'Case insensivity',
				'expect' => '@media screen { .X .sel1 {prop:WhiteListed(foo);} } ',
				'css' => <<<FIN
					@MEDIA screen {
						.sel1 {
							prop: WhiteListed(foo);
							-EVIL: evil;
						}
					}
FIN
			],
			[	'test' => 'Comment trickery',
				'expect' => '.X .sel1 {} .X .sel2 .sel3 {prop3:val3;} ',
				'css' => <<<FIN
					.sel1 {
						-ev/* x */il: evil;
					}
					.sel2 /* { prop2: val2; } */
					.sel3 {
						prop3: val3;
					} /* unfinishe
FIN
			],
			[	'test' => 'Complex selectors',
				'expect' => '.X .sel1[foo=\'ba{r\'] #id a.foo::hover {prop1:val1;} ',
				'css' => <<<FIN
					.sel1[foo='ba{r'] #id a.foo::hover {
						prop1: val1;
					}
FIN
			],
			[	'test' => 'Edge cases',
				'expect' => '.X :sel {} ',
				'css' => <<<FIN
					:sel {
					}
FIN
			],
			[	'test' => 'Function in function',
				'expect' => '.X .sel1 {} ',
				'css' => <<<FIN
					.sel1 {
						prop1: whitelisted(1, evil(2));
					}
FIN
			],
			[	'test' => 'Incomplete rule',
				'expect' => '.X .sel {prop:val;} ',
				'css' => <<<FIN
					.sel {
					  prop: val;
FIN
			],
			[	'test' => 'Media block',
				'expect' => '.X .sel2 {prop2:val2;} @media print { .X .sel1 {prop1:val1;} } ',
				'css' => <<<FIN
					@media print {
					  .sel1 {
						prop1: val1;
					  }
					}

					.sel2 {
					  prop2: val2;
					}
FIN
			],
			[	'test' => 'Multiple rules',
				'expect' => '.X .sel1 A {prop1:val1;} .X T.sel2 {prop2:val2;} ',
				'css' => <<<FIN
					.sel1 A {
					  prop1: val1;
					}

					T.sel2 {
					  prop2: val2;
					}
FIN
			],
			[	'test' => 'Multiple selectors',
				'expect' => '.X .sel1,.X TD .sel2["a,comma"],.X #id {prop:val;} ',
				'css' => <<<FIN
					.sel1, TD .sel2["a,comma"], #id {
					  prop: val;
					}
FIN
			],
			[	'test' => 'No selector',
				'expect' => '{prop1:val1;} ',
				'css' => <<<FIN
					{
						prop1: val1;
					}
FIN
			],
			[	'test' => 'Not a declaration',
				'expect' => '.X .sel {prop:val;} ',
				'css' => <<<FIN
					.sel {
					  not a declaration;
					  prop: val;
					}
FIN
			],
			[	'test' => 'Obfuscated properties',
				'expect' => '.X .sel {good:val2;} ',
				'css' => <<<FIN
					.sel {
					  -\\065 vil: val1;
					  go\\00006fd: val2;
					}
FIN
			],
			[	'test' => 'Rule within rule',
				'expect' => '.X .sel1 {prop1:val1;} .X .sel3 {prop4:val4;} ',
				'css' => <<<FIN
					.sel1 {
					  prop1: val1;
					  .sel2 {
						prop2: val2;
					  }
					  prop3: val3;
					}

					.sel3 {
					  prop4: val4;
					}
FIN
			],
			[	'test' => 'String literals',
				'expect' => '.X .sel {prop1:\'val1\';prop3:"v/**/al\"3";bad:"broken" ;} ',
				'css' => <<<FIN
					.sel {
					  prop1: 'val1';
					  prop3: "v/**/al\"3";
					  bad: "broken
					}
FIN
			],
			[	'test' => 'Unsupported block',
				'expect' => '.X .sel {prop2:val2;} ',
				'css' => <<<FIN
					@font-face {
					  prop1: val1;
					}

					.sel {
					  prop2: val2;
					}
FIN
			],
			[	'test' => 'Unwhitelisted function',
				'expect' => '.X .sel {prop1:whitelisted(val1);} ',
				'css' => <<<FIN
					.sel {
					  prop1: whitelisted(val1);
					  prop2: evil(val2);
					}
FIN
			],
			[	'test' => 'Values',
				'expect' => '.X .sel {prop:1em .5px 12% #FFF;} ',
				'css' => <<<FIN
					.sel {
					  prop: 1em .5px 12% #FFF;
					}
FIN
			],
			[	'test' => 'Whitespace',
				'expect' => '.X .sel1 #id{prop2:whitelisted ( val2 ) ;prop3:not whitelisted( val3 );} ',
				'css' => <<<FIN
					.sel1
					#id{
						-evil
					:val1;
					prop2/*
						comment */: whitelisted ( val2 )
					 ;prop3		:not/**/whitelisted( val3 );}
FIN
			]
		];
	}

	/**
	 * @dataProvider provideCSSParser
	 */
	public function testCSSParser( $test, $expect, $source ) {

		$tree = new CSSParser( $source );
		$rules = $tree->rules( '.X ' );
		if ( !$rules ) {
			$this->fail( "$test: Stylesheet did not parse." );
			return;
		}

		$r = new CSSRenderer();
		$r->add( $rules );
		$css = $r->render( [ "whitelisted" ], [ "-evil" ] );

		$this->assertEquals(
			$expect,
			preg_replace( '/[ \t\n]+/', ' ', $css ),
			"$test: parse did not return expected output."
		);

	}

}

