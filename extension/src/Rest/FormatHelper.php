<?php

namespace MWParsoid\Rest;

use InvalidArgumentException;
use MediaWiki\Rest\ResponseInterface;

/**
 * Format-related REST API helper.
 * Probably should be turned into an object encapsulating format and content version at some point.
 */
class FormatHelper {

	const FORMAT_WIKITEXT = 'wikitext';
	const FORMAT_HTML = 'html';
	const FORMAT_PAGEBUNDLE = 'pagebundle';
	const FORMAT_LINT = 'lint';

	const ERROR_ENCODING = [
		self::FORMAT_WIKITEXT => 'plain',
		self::FORMAT_HTML => 'html',
		self::FORMAT_PAGEBUNDLE => 'json',
		self::FORMAT_LINT => 'json',
	];

	const VALID_PAGE = [
		self::FORMAT_WIKITEXT, self::FORMAT_HTML, self::FORMAT_PAGEBUNDLE, self::FORMAT_LINT
	];

	const VALID_TRANSFORM = [
		self::FORMAT_WIKITEXT => [ self::FORMAT_HTML, self::FORMAT_PAGEBUNDLE, self::FORMAT_LINT ],
		self::FORMAT_HTML => [ self::FORMAT_WIKITEXT ],
		self::FORMAT_PAGEBUNDLE => [ self::FORMAT_WIKITEXT, self::FORMAT_PAGEBUNDLE ],
	];

	/**
	 * Get the content type appropriate for a given response format.
	 * @param string $format One of the FORMAT_* constants
	 * @param string|null $contentVersion Output version, only for HTML and pagebundle
	 *   formats. See Env::getcontentVersion().
	 * @return string
	 */
	public static function getContentType( string $format, string $contentVersion = null ): string {
		if ( $format !== self::FORMAT_WIKITEXT && !$contentVersion ) {
			throw new InvalidArgumentException( '$contentVersion is required for this format' );
		}

		switch ( $format ) {
			case self::FORMAT_WIKITEXT:
				$contentType = 'text/plain';
				// PORT-FIXME in the original the version number is from MWParserEnvironment.wikitextVersion
				// but it did not seem to be used anywhere
				$profile = 'https://www.mediawiki.org/wiki/Specs/wikitext/1.0.0';
				break;
			case self::FORMAT_HTML:
				$contentType = 'text/html';
				$profile = 'https://www.mediawiki.org/wiki/Specs/HTML/' . $contentVersion;
				break;
			case self::FORMAT_PAGEBUNDLE:
				$contentType = 'application/json';
				$profile = 'https://www.mediawiki.org/wiki/Specs/pagebundle/' . $contentVersion;
				break;
			default:
				throw new InvalidArgumentException( "Invalid format $format" );
		}
		return "$contentType; charset=utf-8; profile=\"$profile\"";
	}

	/**
	 * Set the Content-Type header appropriate for a given response format.
	 * @param ResponseInterface $response
	 * @param string $format One of the FORMAT_* constants
	 * @param string|null $contentVersion Output version, only for HTML and pagebundle
	 *   formats. See Env::getcontentVersion().
	 */
	public static function setContentType(
		ResponseInterface $response, string $format, string $contentVersion = null
	): void {
		$response->setHeader( 'Content-Type', self::getContentType( $format, $contentVersion ) );
	}

	/**
	 * Parse a Content-Type header and return the format type and version.
	 * Mostly the inverse of getContentType() but also accounts for legacy formats.
	 * @param string $contentTypeHeader The value of the Content-Type header.
	 * @param string|null &$format Format type will be set here (as a FORMAT_* constant).
	 * @return string|null Format version, or null if it couldn't be identified.
	 * @see Env::getInputContentVersion()
	 */
	public static function parseContentTypeHeader(
		string $contentTypeHeader, string &$format = null
	): ?string {
		$newProfileSyntax = 'https://www.mediawiki.org/wiki/Specs/(HTML|pagebundle)/';
		$oldProfileSyntax = 'mediawiki.org/specs/(html)/';
		$profileRegex = "#\bprofile=\"(?:$newProfileSyntax|$oldProfileSyntax)(\d+\.\d+\.\d+)\"#";
		preg_match( $profileRegex, $contentTypeHeader, $m );
		if ( $m ) {
			switch ( $m[1] ?: $m[2] ) {
				case 'HTML':
				case 'html':
					$format = self::FORMAT_HTML;
				case 'pagebundle':
					$format = self::FORMAT_PAGEBUNDLE;
			}
			return $m[3];
		}
		return null;
	}

}
