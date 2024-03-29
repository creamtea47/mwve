<?php

namespace Test\Parsoid\Language;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Wikimedia\LangConv\ReplacementMachine;
use Wikimedia\Parsoid\Language\LanguageConverter;
use Wikimedia\Parsoid\Mocks\MockEnv;

class SrTest extends TestCase {

	const CODES = [ "sr-ec", "sr-el" ];

	// phpcs:disable Generic.Files.LineLength.TooLong
	const TEST_CASES = [
			[
				'title' => 'A simple conversion of Latin to Cyrillic',
				'output' => [
					'sr-ec' => "абвг"
				],
				'input' => 'abvg'
			],
			/*
			[
				'title' => 'Same as above, but assert that -{}-s must be removed and not converted',
				'output' => [
				// XXX: we don't support embedded -{}- markup in mocha tests;
				//      use parserTests for that
					// 'sr-ec' => 'ljабnjвгdž'
				],
				'input' => "<span typeof=\"mw:LanguageVariant\" data-mw-variant='{\"disabled\":{\"t\":\"lj\"}}'></span>аб<span typeof=\"mw:LanguageVariant\" data-mw-variant='{\"disabled\":{\"t\":\"nj\"}}'></span>вг<span typeof=\"mw:LanguageVariant\" data-mw-variant='{\"disabled\":{\"t\":\"dž\"}}'></span>"
			]
			*/
		];

	/** @var ReplacementMachine */
	private static $machine;

	public static function setUpBeforeClass(): void {
		$lang = LanguageConverter::loadLanguage( new MockEnv( [] ), 'sr' );
		self::$machine = $lang->getConverter()->getMachine();
	}

	public static function tearDownAfterClass(): void {
		self::$machine = null;
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 * @dataProvider provideSr
	 */
	public function testSr( string $title, array $output, string $input, ?string $invertCode ) {
		foreach ( self::CODES as $variantCode ) {
			if ( !array_key_exists( $variantCode, $output ) ) {
				continue;
			}

			$doc = new DOMDocument();
			$out = self::$machine->convert(
				$doc, $input, $variantCode,
				$invertCode ?? $this->getInvertCode( $variantCode )
			);
			$expected = $output[$variantCode];
			$this->assertEquals( $expected, $out->textContent );
		}
	}

	public function provideSr() {
		return array_map( function ( $item ) {
			return [
				$item['title'],
				$item['output'],
				$item['input'],
				$item['code'] ?? null
			];
		}, self::TEST_CASES );
	}

	private function getInvertCode( $variantCode ) {
		return $variantCode === "sr-ec" ? "sr-el" : "sr-ec";
	}

}
