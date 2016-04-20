<?php

/**
 * @group TemplateStyles
 */
class CSSParseRenderTest extends MediaWikiTestCase {

	/**
	 * Parse a CSS string and then validate the rendered output.
	 *
	 * @param string $expect Expected CSS output from renderer
	 * @param string $source Input for CSS parser
	 * @param string $baseSelector Prefix for generated rules
	 * @param array $functionWhitelist Allowed functions
	 * @param array $propertyBlacklist Excluded properties
	 * @dataProvider provideRendererAfterParse
	 */
	public function testRendererAfterParse(
		$expect,
		$source,
		$baseSelector = '.X ',
		$functionWhitelist = [ 'whitelisted' ],
		$propertyBlacklist = [ '-evil' ]
	) {
		$tree = new CSSParser( $source );
		$rules = $tree->rules( $baseSelector );
		if ( !$rules ) {
			$this->fail( "Failed to parse $source" );
		}

		$r = new CSSRenderer();
		$r->add( $rules );
		$css = $r->render( $functionWhitelist, $propertyBlacklist );

		$this->assertEquals(
			$expect,
			// Normalize whitespace inherited from the heredocs
			preg_replace( '/[ \t\n]+/', ' ', $css )
		);
	}

	/**
	 * @see testRendererAfterParse
	 */
	public function provideRendererAfterParse() {
		return [
			'Bare declaration' => [
				'expect' => '',
				'css' => <<<CSS
prop: val;
CSS
			],
			'Blacklisted property' => [
				'expect' => '.X .sel {good:123;} ',
				'css' => <<<CSS
.sel {
	good: 123;
	-evil: "boo";
}
CSS
			],
			'Case insensivity' => [
				'expect' => '@media screen { .X .sel1 {prop:WhiteListed(foo);} } ',
				'css' => <<<CSS
@MEDIA screen {
	.sel1 {
		prop: WhiteListed(foo);
		-EVIL: evil;
	}
}
CSS
			],
			'Comment trickery' => [
				'expect' => '.X .sel1 {} .X .sel2 .sel3 {prop3:val3;} ',
				'css' => <<<CSS
.sel1 {
	-ev/* x */il: evil;
}
.sel2 /* { prop2: val2; } */
.sel3 {
	prop3: val3;
} /* unfinishe
CSS
			],
			'Complex selectors' => [
				'expect' => '.X .sel1[foo=\'ba{r\'] #id a.foo::hover {prop1:val1;} ',
				'css' => <<<CSS
.sel1[foo='ba{r'] #id a.foo::hover {
	prop1: val1;
}
CSS
			],
			'Edge cases' => [
				'expect' => '.X :sel {} ',
				'css' => <<<CSS
:sel {
}
CSS
			],
			'Function in function' => [
				'expect' => '.X .sel1 {} ',
				'css' => <<<CSS
.sel1 {
	prop1: whitelisted(1, evil(2));
}
CSS
			],
			'Incomplete rule' => [
				'expect' => '.X .sel {prop:val;} ',
				'css' => <<<CSS
.sel {
	prop: val;
CSS
			],
			'Media block' => [
				'expect' => '.X .sel2 {prop2:val2;} @media print { .X .sel1 {prop1:val1;} } ',
				'css' => <<<CSS
@media print {
	.sel1 {
	prop1: val1;
	}
}

.sel2 {
	prop2: val2;
}
CSS
		],
			'Multiple rules' => [
				'expect' => '.X .sel1 A {prop1:val1;} .X T.sel2 {prop2:val2;} ',
				'css' => <<<CSS
.sel1 A {
	prop1: val1;
}

T.sel2 {
	prop2: val2;
}
CSS
			],
			'Multiple selectors' => [
				'expect' => '.X .sel1,.X TD .sel2["a,comma"],.X #id {prop:val;} ',
				'css' => <<<CSS
.sel1, TD .sel2["a,comma"], #id {
	prop: val;
}
CSS
			],
			'No selector' => [
				'expect' => '{prop1:val1;} ',
				'css' => <<<CSS
{
	prop1: val1;
}
CSS
			],
			'Not a declaration' => [
				'expect' => '.X .sel {prop:val;} ',
				'css' => <<<CSS
.sel {
	not a declaration;
	prop: val;
}
CSS
			],
			'Obfuscated properties' => [
				'expect' => '.X .sel {good:val2;} ',
				'css' => <<<CSS
.sel {
	-\\065 vil: val1;
	go\\00006fd: val2;
}
CSS
			],
			'Rule within rule' => [
				'expect' => '.X .sel1 {prop1:val1;} .X .sel3 {prop4:val4;} ',
				'css' => <<<CSS
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
CSS
			],
			'String literals' => [
				'expect' => '.X .sel {prop1:\'val1\';prop3:"v/**/al\"3";bad:"broken";} ',
				'css' => <<<CSS
.sel {
	prop1: 'val1';
	prop3: "v/**/al\"3";
	bad: "broken
}
CSS
			],
			'Unsupported block' => [
				'expect' => '.X .sel {prop2:val2;} ',
				'css' => <<<CSS
@font-face {
	prop1: val1;
}

.sel {
	prop2: val2;
}
CSS
			],
			'Unwhitelisted function' => [
				'expect' => '.X .sel {prop1:whitelisted(val1);} ',
				'css' => <<<CSS
.sel {
	prop1: whitelisted(val1);
	prop2: evil(val2);
}
CSS
			],
			'Values' => [
				'expect' => '.X .sel {prop:1em .5px 12% #FFF;} ',
				'css' => <<<CSS
.sel {
	prop: 1em .5px 12% #FFF;
}
CSS
			],
			'Whitespace' => [
				'expect' => '.X .sel1 #id{prop2:whitelisted ( val2 ) ;prop3:not whitelisted( val3 );} ',
				'css' => <<<CSS
.sel1
#id{
	-evil
:val1;
prop2/*
	comment */: whitelisted ( val2 )
	;prop3		:not/**/whitelisted( val3 );}
CSS
			],
		];
	}
}

