<?php

namespace Wikimedia\Parsoid\Tools;

// Hacky preprocessing of command-line arguments: look for
// --integrated and/or --standalone flags.
$parsoidMode = null;
for ( $arg = reset( $argv ); $arg !== false; $arg = next( $argv ) ) {
	if ( $arg === '--' ) {
		# end of options
		break;
	} elseif ( $arg === '--integrated' || $arg === '--standalone' ) {
		$parsoidMode = substr( $arg, 2 );
		break;
	}
}

# On scandium and production machines, you should use:
# sudo -u www-data php /srv/mediawiki/multiversion/MWScript.php \
#     /srv/parsoid-testing/bin/<cmd>.php --wiki=hiwiki --integrated <args>
#
# eg:
#
# USER@scandium:/srv/mediawiki/multiversion$ echo '==Foo==' | \
#    sudo -u www-data php MWScript.php \
#    /srv/parsoid-testing/bin/parse.php --wiki=hiwiki --integrated
#

// phpcs:disable Generic.Classes.DuplicateClassName.Found
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
if ( $parsoidMode === 'integrated' ) {
	/* Is MW installed w/ Parsoid?  Then use core's copy of Maintenance.php. */
	if ( strval( getenv( 'MW_INSTALL_PATH' ) ) !== '' ) {
		require_once getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php';
		require_once getenv( 'MW_INSTALL_PATH' ) . '/includes/AutoLoader.php';
	} else {
		error_log( 'MW_INSTALL_PATH environment variable must be defined.' );
	}

	// EVIL(ish) hack:
	// Override autoloader to ensure all of Parsoid is running from the
	// same place as this file (since there will also be another copy of
	// Parsoid included from the vendor/wikimedia/parsoid directory)
	// @phan-suppress-next-line PhanUndeclaredClassStaticProperty
	\AutoLoader::$psr4Namespaces += [
		// Keep this in sync with the "autoload" clause in /composer.json!
		'Wikimedia\\Parsoid\\' => __DIR__ . "/../src",
		// And this is from autoload-dev
		'Wikimedia\\Parsoid\\Tools\\' => __DIR__ . "/../tools/",
	];

	abstract class Maintenance extends \Maintenance {
		public function __construct() {
			parent::__construct();
			$this->requireExtension( 'Parsoid' );
		}

		public function addDefaultParams(): void {
			$this->addOption(
				'integrated',
				'Run parsoid integrated with a host MediaWiki installation ' .
				'at MW_INSTALL_PATH'
			);
			$this->addOption(
				'standalone',
				'Run parsoid standalone, communicating with a host MediaWiki ' .
				'using network API (see --domain option)'
			);
			parent::addDefaultParams();
		}

		/**
		 * Make the protected method from the superclass into a public method
		 * so we can use this from TestUtils.php.
		 */
		public function addOption(
			$name, $description, $required = false,
			$withArg = false, $shortName = false,
			$multiOccurrence = false
		) {
			parent::addOption(
				$name, $description, $required, $withArg, $shortName,
				$multiOccurrence
			);
		}

		/** Make the options array available to ExtendedOptsProcessor */
		protected function getOptions() {
			return $this->mOptions;
		}
	}

	define( 'PARSOID_RUN_MAINTENANCE_IF_MAIN', RUN_MAINTENANCE_IF_MAIN );

	// Ensure parsoid extension is loaded on production machines.
	$_SERVER['SERVERGROUP'] = 'parsoid';
} else {
	/* Use Parsoid's stand-alone clone of the Maintenance framework */
	require_once __DIR__ . '/../vendor/autoload.php';

	abstract class Maintenance extends OptsProcessor {
		public function addDefaultParams(): void {
			$this->addOption(
				'integrated',
				'Run parsoid integrated with a host MediaWiki installation ' .
				'at MW_INSTALL_PATH'
			);
			$this->addOption(
				'standalone',
				'Run parsoid standalone, communicating with a host MediaWiki ' .
				'using network API (see --domain option)'
			);
			parent::addDefaultParams();
		}

		/** Make the options array available to ExtendedOptsProcessor */
		protected function getOptions() {
			return $this->options;
		}

		public function setup() {
			# Abort if called from a web server
			# wfIsCLI() is not available yet
			if ( PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' ) {
				$this->fatalError( 'This script must be run from the command line' );
			}
			# Make sure we can handle script parameters
			if ( !defined( 'HPHP_VERSION' ) && !ini_get( 'register_argc_argv' ) ) {
				$this->fatalError( 'Cannot get command line arguments, register_argc_argv is set to false' );
			}

			// Send PHP warnings and errors to stderr instead of stdout.
			// This aids in diagnosing problems, while keeping messages
			// out of redirected output.
			if ( ini_get( 'display_errors' ) ) {
				ini_set( 'display_errors', 'stderr' );
			}

			$this->loadParamsAndArgs();

			# Set max execution time to 0 (no limit). PHP.net says that
			# "When running PHP from the command line the default setting is 0."
			# But sometimes this doesn't seem to be the case.
			ini_set( 'max_execution_time', 0 );

			# Turn off output buffering if it's on
			while ( ob_get_level() > 0 ) {
				ob_end_flush();
			}
		}

		/** For compatibility with core. */
		public static function requireTestsAutoloader() {
			/* do nothing, for compatibility only */
		}

		/** For compatibility with core */
		public function finalSetup() {
			if ( ob_get_level() ) {
				ob_end_flush();
			}
		}

		// Implementation copied from core:
		/**
		 * Wrapper for posix_isatty()
		 * We default as considering stdin a tty (for nice readline methods)
		 * but treating stdout as not a tty to avoid color codes
		 *
		 * @param mixed $fd File descriptor
		 * @return bool
		 */
		// phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
		public static function posix_isatty( $fd ) {
			if ( !function_exists( 'posix_isatty' ) ) {
				return !$fd;
			} else {
				return posix_isatty( $fd );
			}
		}
	}

	define( 'PARSOID_RUN_MAINTENANCE_IF_MAIN', __DIR__ . '/doMaintenance.php' );
}
