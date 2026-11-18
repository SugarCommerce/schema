<?php
/**
 * A version for each table that moves when its shape does.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

/**
 * Table versions, kept in the lock file.
 *
 * BerlinDB 3.1 upgrades a table by comparing this number with the one it
 * stored: when ours is higher and there is no upgrade routine for it, it
 * reads the live table, compares it with the Schema class and adds whatever
 * columns and indexes are missing. So an upgrade is nothing more than a
 * higher number -- but only for the table that changed, and only if
 * somebody remembers to raise it.
 *
 * Nobody has to. The lock file keeps a fingerprint of every table's columns
 * and indexes beside the version it was last given. A table whose
 * fingerprint has moved gets a new version, today's date and a counter; one
 * that has not keeps the number it had, so regenerating changes nothing.
 * Commit the lock file with the schema.
 *
 * Additive only, which is BerlinDB's default: a column removed from the
 * schema stays in an existing table, and a changed type is not applied.
 * `schema reconcile` still reports both.
 *
 * @since 1.0.0
 */
final class Versions {

	/**
	 * Constructor.
	 *
	 * @param string   $initial The version a table is given the first time.
	 * @param \Closure $today   Today's date as YYYYMMDD; injectable for tests.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		private readonly string $initial,
		private readonly ?\Closure $today = null
	) {
	}

	/**
	 * A table's fingerprint.
	 *
	 * What a column is cleaned with, and what fills it in when a caller
	 * leaves it out, are not part of the table: MySQL never hears of either.
	 * They are left out, so that saying a column is a "key" does not raise a
	 * version.
	 *
	 * @param \stdClass $table The table's definition, as decoded objects.
	 *
	 * @return string Sixteen hex characters.
	 * @since 1.0.0
	 */
	public static function fingerprint( \stdClass $table ): string {
		$shape = new \stdClass();

		foreach ( [ 'columns', 'unique', 'indexes', 'uuid' ] as $key ) {
			$shape->{$key} = $table->{$key} ?? null;
		}

		if ( is_array( $shape->columns ) ) {
			$shape->columns = array_map(
				static function ( $column ) {
					if ( ! $column instanceof \stdClass ) {
						return $column;
					}

					$kept = new \stdClass();

					foreach ( get_object_vars( $column ) as $key => $value ) {
						if ( ! in_array( $key, Config::NOT_DDL, true ) ) {
							$kept->{$key} = $value;
						}
					}

					return $kept;
				},
				$shape->columns
			);
		}

		return substr( hash( 'sha256', Json::encode( $shape, true ) ), 0, 16 );
	}

	/**
	 * Every table's fingerprint and version, given the lock as it stands.
	 *
	 * @param array<string, \stdClass>                                $tables The tables, by plural name.
	 * @param array<string, array{shape?: string, version?: int|string}> $lock   The lock file's contents.
	 *
	 * @return array<string, array{shape: string, version: int}>
	 * @since 1.0.0
	 */
	public function stamp( array $tables, array $lock ): array {
		$today = (int) ( $this->today ? ( $this->today )() : ( new \DateTimeImmutable() )->format( 'Ymd' ) ) * 10;
		$out   = [];

		foreach ( $tables as $plural => $table ) {
			$mark = self::fingerprint( $table );
			$was  = $lock[ $plural ] ?? null;

			if ( ! is_array( $was ) ) {
				$version = (int) $this->initial;
			} elseif ( ( $was['shape'] ?? null ) === $mark ) {
				$version = (int) $was['version'];
			} else {
				$version = max( (int) ( $was['version'] ?? 0 ) + 1, $today );
			}

			$out[ $plural ] = [
				'shape'   => $mark,
				'version' => $version,
			];
		}

		return $out;
	}

	/**
	 * The lock file's text.
	 *
	 * @param array<string, array{shape: string, version: int}> $stamped The versions.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function lock_file( array $stamped ): string {
		return Json::encode( [] === $stamped ? new \stdClass() : $stamped, false, 2 ) . "\n";
	}
}
