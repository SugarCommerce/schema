<?php
/**
 * The public API for audit entries.
 *
 * @package     Acme
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

use Acme\Database\Queries\AuditEntry;
use Acme\Database\Rows\AuditEntry as AuditEntryRow;

defined( 'ABSPATH' ) || exit;

/**
 * Get an audit entry by id.
 *
 * @param int $id The audit entry id.
 *
 * @return AuditEntryRow|false The audit entry, or false when there is not one.
 * @since 1.0.0
 */
function acme_get_audit_entry( int $id = 0 ) {
	$item = $id > 0
		? ( new AuditEntry() )->get_item( $id )
		: false;

	/**
	 * Filter an audit entry as it is read.
	 *
	 * @param AuditEntryRow|false $item The audit entry.
	 * @param int             $id   The id asked for.
	 *
	 * @since 1.0.0
	 */
	$item = apply_filters( 'acme_get_audit_entry', $item, $id );

	return $item;
}

/**
 * Get an audit entry by any unique column.
 *
 * @param string $field Column name.
 * @param mixed  $value Value to match.
 *
 * @return AuditEntryRow|false
 * @since 1.0.0
 */
function acme_get_audit_entry_by( string $field = '', $value = '' ) {
	$item = ( '' !== $field && '' !== $value && null !== $value )
		? ( new AuditEntry() )->get_item_by( $field, $value )
		: false;

	/**
	 * Filter an audit entry looked up by column.
	 *
	 * @param AuditEntryRow|false $item  The audit entry.
	 * @param string          $field Column name.
	 * @param mixed           $value Value matched.
	 *
	 * @since 1.0.0
	 */
	$item = apply_filters( 'acme_get_audit_entry_by', $item, $field, $value );

	return $item;
}

/**
 * Get one field from an audit entry, without hydrating the whole row.
 *
 * @param int    $id    The audit entry id.
 * @param string $field Column name.
 *
 * @return mixed|false
 * @since 1.0.0
 */
function acme_get_audit_entry_field( int $id = 0, string $field = '' ) {
	$item = acme_get_audit_entry( $id );

	return ( $item && isset( $item->{$field} ) )
		? $item->{$field}
		: false;
}

/**
 * Query audit entries.
 *
 * @param array $args Query arguments, as understood by BerlinDB.
 *
 * @return array<int, AuditEntryRow>
 * @since 1.0.0
 */
function acme_get_audit_entries( array $args = array() ): array {
	/**
	 * Filter the arguments before audit entries are queried.
	 *
	 * @param array $args Query arguments.
	 *
	 * @since 1.0.0
	 */
	$args = apply_filters( 'acme_get_audit_entries_args', $args );

	// 'number' is left alone when already set: a caller asking for all of
	// them has to say so, but a caller that said nothing gets a bounded
	// query rather than the whole table.
	$defaults = array( 'number' => ACME_DEFAULT_QUERY_NUMBER );
	$args     = wp_parse_args( $args, $defaults );

	$items = ( new AuditEntry() )->query( $args );

	/**
	 * Filter the audit entries returned.
	 *
	 * @param array $items The audit entries.
	 * @param array $args  Query arguments.
	 *
	 * @since 1.0.0
	 */
	$items = (array) apply_filters( 'acme_get_audit_entries', $items, $args );

	return $items;
}

/**
 * Count audit entries.
 *
 * @param array $args Query arguments.
 *
 * @return int
 * @since 1.0.0
 */
function acme_count_audit_entries( array $args = array() ): int {
	$args  = array_merge(
		$args,
		array(
			'count'  => true,
			'number' => 0,
		)
	);
	$count = (int) ( new AuditEntry() )->query( $args );

	/**
	 * Filter the audit entry count.
	 *
	 * @param int   $count The count.
	 * @param array $args  Query arguments.
	 *
	 * @since 1.0.0
	 */
	$count = (int) apply_filters( 'acme_count_audit_entries', $count, $args );

	return $count;
}

/**
 * Count audit entries, one figure for each value of a column.
 *
 * One query -- `COUNT(*)` grouped by the column -- over the rows
 * acme_get_audit_entries() would match with the same arguments.
 *
 * @param array  $args   Query arguments.
 * @param string $column The column to count by.
 *
 * @return array<string, int> How many, by the column's value. Empty when
 *                            nothing matched or there is no such column.
 * @since 1.0.0
 */
function acme_get_audit_entry_counts( array $args = array(), string $column = 'status' ): array {
	$rows = ( new AuditEntry() )->query(
		array_merge(
			array_diff_key( $args, array_flip( array( 'count', 'fields', 'offset', 'orderby', 'order' ) ) ),
			array(
				'number'    => 0,
				'groupby'   => $column,
				'aggregate' => array( 'count' => array( 'count', '*' ) ),
			)
		)
	);

	$counts = array();

	// Not grouped -- a column the table does not have -- is one figure and
	// not a row per value, so nothing here.
	foreach ( (array) $rows as $row ) {
		if ( is_array( $row ) && array_key_exists( $column, $row ) ) {
			$counts[ (string) $row[ $column ] ] = (int) ( $row['count'] ?? 0 );
		}
	}

	/**
	 * Filter the audit entry counts.
	 *
	 * @param array  $counts How many, by the column's value.
	 * @param array  $args   Query arguments.
	 * @param string $column The column counted by.
	 *
	 * @since 1.0.0
	 */
	$counts = (array) apply_filters( 'acme_get_audit_entry_counts', $counts, $args, $column );

	return $counts;
}

/**
 * One figure over the audit entries a query matches: a sum, an average, the
 * smallest or largest value, or how many values a column has.
 *
 * One query, over the rows acme_get_audit_entries() would match with the same
 * arguments. With `groupby` among them, one figure for each value of that
 * column: money is summed by currency.
 *
 * @param string $function `sum`, `avg`, `min`, `max` or `count`.
 * @param string $column   The column (`*` to count rows); `sum` and `avg`
 *                         need a numeric one.
 * @param array  $args     Query arguments, and `groupby` for one figure a
 *                         value.
 *
 * @return int|float|string|array<string, int|float|string> Nought when
 *                                                          nothing matched,
 *                                                          or the function
 *                                                          or column is not
 *                                                          one there is.
 * @since 1.0.0
 */
function acme_get_audit_entry_stats( string $function, string $column, array $args = array() ) {
	$function = strtolower( $function );
	$groupby  = (string) ( $args['groupby'] ?? '' );
	$query    = new AuditEntry();
	$value    = '' === $groupby ? 0 : array();

	// Refused here and not left to the query: an aggregate it cannot build
	// is dropped, and what is left is an ordinary query for every row.
	$known = in_array( $function, array( 'sum', 'avg', 'min', 'max', 'count' ), true );

	if ( $known && ! ( 'count' === $function && '*' === $column ) ) {
		$found = $query->get_column_by( array( 'name' => $column ) );
		$known = $found && ( ! in_array( $function, array( 'sum', 'avg' ), true ) || $found->is_numeric() );
	}

	if ( $known ) {
		$result = $query->query(
			array_merge(
				array_diff_key( $args, array_flip( array( 'count', 'fields', 'offset', 'orderby', 'order' ) ) ),
				array(
					'number'    => 0,
					'aggregate' => array( 'value' => array( $function, $column ) ),
				)
			)
		);

		$figure = static function ( $raw ) {
			return is_numeric( $raw ) ? $raw + 0 : ( null === $raw ? 0 : (string) $raw );
		};

		if ( '' === $groupby ) {
			$value = is_array( $result ) && array_key_exists( 'value', $result ) ? $figure( $result['value'] ) : 0;
		} else {
			foreach ( (array) $result as $row ) {
				if ( is_array( $row ) && array_key_exists( $groupby, $row ) ) {
					$value[ (string) $row[ $groupby ] ] = $figure( $row['value'] ?? null );
				}
			}
		}
	}

	/**
	 * Filter a figure over audit entries.
	 *
	 * @param int|float|string|array $value    The figure, or figures by `groupby`.
	 * @param string                 $function `sum`, `avg`, `min`, `max` or `count`.
	 * @param string                 $column   The column.
	 * @param array                  $args     Query arguments.
	 *
	 * @since 1.0.0
	 */
	$value = apply_filters( 'acme_get_audit_entry_stats', $value, $function, $column, $args );

	return $value;
}

/**
 * Add an audit entry.
 *
 * @param array $data Column values.
 *
 * @return int|false The new id, or false when it could not be written.
 * @since 1.0.0
 */
function acme_add_audit_entry( array $data = array() ) {
	/**
	 * Filter the data before an audit entry is created.
	 *
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	$data = apply_filters( 'acme_add_audit_entry_data', $data );

	if ( empty( $data ) ) {
		return false;
	}

	/**
	 * Fires before an audit entry is created.
	 *
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_add_audit_entry', $data );

	$id = ( new AuditEntry() )->add_item( $data );

	if ( empty( $id ) ) {
		return false;
	}

	/**
	 * Fires once an audit entry has been created.
	 *
	 * @param int   $id   The new id.
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_add_audit_entry', $id, $data );

	return (int) $id;
}

/**
 * Update an audit entry.
 *
 * @param int   $id   The audit entry id.
 * @param array $data Column values to change.
 *
 * @return bool
 * @since 1.0.0
 */
function acme_update_audit_entry( int $id = 0, array $data = array() ): bool {
	if ( $id <= 0 ) {
		return false;
	}

	/**
	 * Filter the data before an audit entry is updated.
	 *
	 * @param array $data Column values.
	 * @param int   $id   The audit entry id.
	 *
	 * @since 1.0.0
	 */
	$data = apply_filters( 'acme_update_audit_entry_data', $data, $id );

	if ( empty( $data ) ) {
		return false;
	}

	/**
	 * Fires before an audit entry is updated.
	 *
	 * @param int   $id   The audit entry id.
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_update_audit_entry', $id, $data );

	$updated = (bool) ( new AuditEntry() )->update_item( $id, $data );

	if ( $updated ) {
		/**
		 * Fires once an audit entry has been updated.
		 *
		 * @param int   $id   The audit entry id.
		 * @param array $data Column values.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_update_audit_entry', $id, $data );
	}

	return $updated;
}

/**
 * Delete an audit entry.
 *
 * @param int $id The audit entry id.
 *
 * @return bool
 * @since 1.0.0
 */
function acme_delete_audit_entry( int $id = 0 ): bool {
	if ( $id <= 0 ) {
		return false;
	}

	/**
	 * Fires before an audit entry is deleted, while it can still be read.
	 *
	 * @param int $id The audit entry id.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_delete_audit_entry', $id );

	$deleted = (bool) ( new AuditEntry() )->delete_item( $id );

	if ( $deleted ) {
		/**
		 * Fires once an audit entry has been deleted.
		 *
		 * @param int $id The id it had.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_delete_audit_entry', $id );
	}

	return $deleted;
}

/**
 * Add a meta value to an audit entry.
 *
 * @param int    $id     The audit entry id.
 * @param string $key    Meta key.
 * @param mixed  $value  Meta value.
 * @param bool   $unique Whether to fail if the key already exists.
 *
 * @return int|false
 * @since 1.0.0
 */
function acme_add_audit_entry_meta( int $id, string $key, $value, bool $unique = false ) {
	return add_metadata( 'acme_audit_entry', $id, $key, $value, $unique );
}

/**
 * Read meta from an audit entry.
 *
 * @param int    $id     The audit entry id.
 * @param string $key    Meta key. Empty returns everything.
 * @param bool   $single Whether to return a single value.
 *
 * @return mixed
 * @since 1.0.0
 */
function acme_get_audit_entry_meta( int $id, string $key = '', bool $single = false ) {
	return get_metadata( 'acme_audit_entry', $id, $key, $single );
}

/**
 * Update meta on an audit entry.
 *
 * @param int    $id    The audit entry id.
 * @param string $key   Meta key.
 * @param mixed  $value Meta value.
 * @param mixed  $prev  Only update rows matching this previous value.
 *
 * @return int|bool
 * @since 1.0.0
 */
function acme_update_audit_entry_meta( int $id, string $key, $value, $prev = '' ) {
	return update_metadata( 'acme_audit_entry', $id, $key, $value, $prev );
}

/**
 * Delete meta from an audit entry.
 *
 * @param int    $id    The audit entry id.
 * @param string $key   Meta key.
 * @param mixed  $value Only delete rows matching this value.
 *
 * @return bool
 * @since 1.0.0
 */
function acme_delete_audit_entry_meta( int $id, string $key, $value = '' ): bool {
	return delete_metadata( 'acme_audit_entry', $id, $key, $value );
}

/**
 * Delete a meta key from every audit entry.
 *
 * Runs WordPress's delete_metadata() in delete-all mode, so the delete hooks
 * fire and every affected audit entry's meta cache is cleared.
 *
 * @param string $key Meta key.
 *
 * @return bool False when no audit entry had the key.
 * @since 1.0.0
 */
function acme_delete_audit_entry_meta_by_key( string $key ): bool {
	return delete_metadata( 'acme_audit_entry', 0, $key, '', true );
}
