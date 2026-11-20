<?php
/**
 * Refusing to overwrite what somebody wrote by hand.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

use SugarCommerce\Schema\Render\Functions;

/**
 * The checks that stop a run before it eats code.
 *
 * Generated files are written wholesale. A generator that quietly overwrites
 * whatever was there is worse than one that stops, because nothing fails at
 * the moment of the loss -- only months later, somewhere else.
 *
 * @since 1.0.0
 */
final class Guard {

	/**
	 * Constructor.
	 *
	 * @param Config $config The settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct( private readonly Config $config ) {
	}

	/**
	 * Refuse to overwrite a table class that carries an upgrade routine.
	 *
	 * A table class is the only place a schema migration can live --
	 * BerlinDB finds one by the __{version} method name and the $upgrades
	 * list beside it -- so hand-written ALTERs accumulate in a generated file
	 * by design.
	 *
	 * Regenerating would delete every one of them, and nothing would fail:
	 * the tables already have the columns on this machine, so the suite stays
	 * green and the loss only surfaces on somebody else's database, months
	 * later, as a column that never arrived.
	 *
	 * @param string $path Relative to the root.
	 * @param string $body What would be written.
	 *
	 * @return void
	 * @throws GeneratorException When routines would be lost.
	 * @since 1.0.0
	 */
	public function no_migrations( string $path, string $body ): void {
		$existing = $this->read( $path );

		if ( null === $existing || ! str_contains( '/' . $path, '/Tables/' ) ) {
			return;
		}

		$pattern = '/function\s+(__\d+)\s*\(/';

		preg_match_all( $pattern, $existing, $had );
		preg_match_all( $pattern, $body, $kept );

		$lost = array_values( array_diff( array_unique( $had[1] ), $kept[1] ) );
		sort( $lost );

		if ( [] === $lost ) {
			return;
		}

		throw new GeneratorException(
			"{$path} carries " . count( $lost ) . " schema migration(s) this tool does not generate:\n\n"
			. implode( '', array_map( static fn( string $name ): string => "      {$name}()\n", $lost ) )
			. "\n  Overwriting would delete them, and nothing would fail here --\n"
			. "  this database already has the columns. It would fail on a database\n"
			. "  that does not.\n\n"
			. '  Fold them into ' . $this->config->get( 'schema' ) . " so the generated table is already\n"
			. "  right, or leave this table out of the run with --only.\n"
		);
	}

	/**
	 * Refuse to overwrite a functions file somebody has added to.
	 *
	 * @param string $path Relative to the root.
	 * @param string $body What would be written.
	 *
	 * @return void
	 * @throws GeneratorException When functions would be lost.
	 * @since 1.0.0
	 */
	public function no_handwriting( string $path, string $body ): void {
		$existing = $this->read( $path );

		if ( null === $existing ) {
			return;
		}

		$lost = array_values( array_diff( Functions::defined_in( $existing ), Functions::defined_in( $body ) ) );
		sort( $lost );

		if ( [] === $lost ) {
			return;
		}

		throw new GeneratorException(
			"{$path} defines " . count( $lost ) . " function(s) this tool does not generate:\n\n"
			. implode( '', array_map( static fn( string $name ): string => "      {$name}()\n", $lost ) )
			. "\n  Overwriting would delete them. Move them to a hand-written file\n"
			. "  in the same folder -- anything not named after a table is never generated\n"
			. "  -- then run this again.\n"
		);
	}

	/**
	 * Old copies of a table's functions file, elsewhere under functions/.
	 *
	 * A table moved to another folder -- or left over from when the folder
	 * was flat -- would otherwise be declared twice, and PHP stops at the
	 * second declaration with a fatal. Only a file this tool would have
	 * written is returned for removal: same name, and nothing in it that is
	 * not generated.
	 *
	 * @param string $path Relative to the root: where the file now goes.
	 * @param string $body What will be written there.
	 *
	 * @return array<int, string> Paths, relative to the root, to remove.
	 * @throws GeneratorException When an old copy has functions of its own.
	 * @since 1.0.0
	 */
	public function stale_copies( string $path, string $body ): array {
		$root = $this->config->get( 'paths' )['functions'];
		$dir  = $this->config->path( $root );

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$name  = basename( $path );
		$ours  = Functions::defined_in( $body );
		$stale = [];
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $files as $file ) {
			if ( $file->getFilename() !== $name ) {
				continue;
			}

			$relative = $root . substr( $file->getPathname(), strlen( $dir ) );

			if ( $relative === $path ) {
				continue;
			}

			$theirs = Functions::defined_in( (string) $this->read( $relative ) );

			if ( [] !== array_diff( $theirs, $ours ) ) {
				throw new GeneratorException(
					"{$relative} is an old copy of {$path} with functions of its own in it.\n"
					. "  Move them to a hand-written file, delete it, and run this again.\n"
				);
			}

			$stale[] = $relative;
		}

		sort( $stale );

		return $stale;
	}

	/**
	 * A file's contents, or null when there is no file.
	 *
	 * @param string $path Relative to the root.
	 *
	 * @return string|null
	 * @since 1.0.0
	 */
	private function read( string $path ): ?string {
		$file = $this->config->path( $path );

		if ( ! is_file( $file ) ) {
			return null;
		}

		$text = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read by a command-line tool.

		return false === $text ? null : $text;
	}
}
