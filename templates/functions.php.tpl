
use {ns}\Queries\{Sing};
use {ns}\Rows\{Sing} as {Sing}Row;

defined( 'ABSPATH' ) || exit;

/**
 * Get {a} {label} by id.
 *
 * @param int $id The {label} id.
 *
 * @return {Sing}Row|false The {label}, or false when there is not one.
 * @since 1.0.0
 */
function {fp}_get_{sing}( int $id = 0 ) {
	$item = $id > 0
		? ( new {Sing}() )->get_item( $id )
		: false;

	/**
	 * Filter {a} {label} as it is read.
	 *
	 * @param {Sing}Row|false $item The {label}.
	 * @param int             $id   The id asked for.
	 *
	 * @since 1.0.0
	 */
	$item = apply_filters( '{hp}_get_{sing}', $item, $id );

	return $item;
}

/**
 * Get {a} {label} by any unique column.
 *
 * @param string $field Column name.
 * @param mixed  $value Value to match.
 *
 * @return {Sing}Row|false
 * @since 1.0.0
 */
function {fp}_get_{sing}_by( string $field = '', $value = '' ) {
	$item = ( '' !== $field && '' !== $value && null !== $value )
		? ( new {Sing}() )->get_item_by( $field, $value )
		: false;

	/**
	 * Filter {a} {label} looked up by column.
	 *
	 * @param {Sing}Row|false $item  The {label}.
	 * @param string          $field Column name.
	 * @param mixed           $value Value matched.
	 *
	 * @since 1.0.0
	 */
	$item = apply_filters( '{hp}_get_{sing}_by', $item, $field, $value );

	return $item;
}

/**
 * Get one field from {a} {label}, without hydrating the whole row.
 *
 * @param int    $id    The {label} id.
 * @param string $field Column name.
 *
 * @return mixed|false
 * @since 1.0.0
 */
function {fp}_get_{sing}_field( int $id = 0, string $field = '' ) {
	$item = {fp}_get_{sing}( $id );

	return ( $item && isset( $item->{$field} ) )
		? $item->{$field}
		: false;
}

/**
 * Query {labels}.
 *
 * @param array $args Query arguments, as understood by BerlinDB.
 *
 * @return array<int, {Sing}Row>
 * @since 1.0.0
 */
function {fp}_get_{plural}( array $args = array() ): array {
	/**
	 * Filter the arguments before {labels} are queried.
	 *
	 * @param array $args Query arguments.
	 *
	 * @since 1.0.0
	 */
	$args = apply_filters( '{hp}_get_{plural}_args', $args );

	// 'number' is left alone when already set: a caller asking for all of
	// them has to say so, but a caller that said nothing gets a bounded
	// query rather than the whole table.
	$defaults = array( 'number' => {number} );
	$args     = wp_parse_args( $args, $defaults );

	$items = ( new {Sing}() )->query( $args );

	/**
	 * Filter the {labels} returned.
	 *
	 * @param array $items The {labels}.
	 * @param array $args  Query arguments.
	 *
	 * @since 1.0.0
	 */
	$items = (array) apply_filters( '{hp}_get_{plural}', $items, $args );

	return $items;
}

/**
 * Count {labels}.
 *
 * @param array $args Query arguments.
 *
 * @return int
 * @since 1.0.0
 */
function {fp}_count_{plural}( array $args = array() ): int {
	$args  = array_merge(
		$args,
		array(
			'count'  => true,
			'number' => 0,
		)
	);
	$count = (int) ( new {Sing}() )->query( $args );

	/**
	 * Filter the {label} count.
	 *
	 * @param int   $count The count.
	 * @param array $args  Query arguments.
	 *
	 * @since 1.0.0
	 */
	$count = (int) apply_filters( '{hp}_count_{plural}', $count, $args );

	return $count;
}

/**
 * Count {labels}, one figure for each value of a column.
 *
 * One query -- `COUNT(*)` grouped by the column -- over the rows
 * {fp}_get_{plural}() would match with the same arguments.
 *
 * @param array  $args   Query arguments.
 * @param string $column The column to count by.
 *
 * @return array<string, int> How many, by the column's value. Empty when
 *                            nothing matched or there is no such column.
 * @since 1.0.0
 */
function {fp}_get_{sing}_counts( array $args = array(), string $column = 'status' ): array {
	$rows = ( new {Sing}() )->query(
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
	 * Filter the {label} counts.
	 *
	 * @param array  $counts How many, by the column's value.
	 * @param array  $args   Query arguments.
	 * @param string $column The column counted by.
	 *
	 * @since 1.0.0
	 */
	$counts = (array) apply_filters( '{hp}_get_{sing}_counts', $counts, $args, $column );

	return $counts;
}

/**
 * One figure over the {labels} a query matches: a sum, an average, the
 * smallest or largest value, or how many values a column has.
 *
 * One query, over the rows {fp}_get_{plural}() would match with the same
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
function {fp}_get_{sing}_stats( string $function, string $column, array $args = array() ) {
	$function = strtolower( $function );
	$groupby  = (string) ( $args['groupby'] ?? '' );
	$query    = new {Sing}();
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
	 * Filter a figure over {labels}.
	 *
	 * @param int|float|string|array $value    The figure, or figures by `groupby`.
	 * @param string                 $function `sum`, `avg`, `min`, `max` or `count`.
	 * @param string                 $column   The column.
	 * @param array                  $args     Query arguments.
	 *
	 * @since 1.0.0
	 */
	$value = apply_filters( '{hp}_get_{sing}_stats', $value, $function, $column, $args );

	return $value;
}

/**
 * Add {a} {label}.
 *
 * @param array $data Column values.
 *
 * @return int|false The new id, or false when it could not be written.
 * @since 1.0.0
 */
function {fp}_add_{sing}( array $data = array() ) {
	/**
	 * Filter the data before {a} {label} is created.
	 *
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	$data = apply_filters( '{hp}_add_{sing}_data', $data );

	if ( empty( $data ) ) {
		return false;
	}

	/**
	 * Fires before {a} {label} is created.
	 *
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( '{hp}_before_add_{sing}', $data );

	$id = ( new {Sing}() )->add_item( $data );

	if ( empty( $id ) ) {
		return false;
	}

	/**
	 * Fires once {a} {label} has been created.
	 *
	 * @param int   $id   The new id.
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( '{hp}_add_{sing}', $id, $data );

	return (int) $id;
}

/**
 * Update {a} {label}.
 *
 * @param int   $id   The {label} id.
 * @param array $data Column values to change.
 *
 * @return bool
 * @since 1.0.0
 */
function {fp}_update_{sing}( int $id = 0, array $data = array() ): bool {
	if ( $id <= 0 ) {
		return false;
	}

	/**
	 * Filter the data before {a} {label} is updated.
	 *
	 * @param array $data Column values.
	 * @param int   $id   The {label} id.
	 *
	 * @since 1.0.0
	 */
	$data = apply_filters( '{hp}_update_{sing}_data', $data, $id );

	if ( empty( $data ) ) {
		return false;
	}

	/**
	 * Fires before {a} {label} is updated.
	 *
	 * @param int   $id   The {label} id.
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( '{hp}_before_update_{sing}', $id, $data );

	$updated = (bool) ( new {Sing}() )->update_item( $id, $data );

	if ( $updated ) {
		/**
		 * Fires once {a} {label} has been updated.
		 *
		 * @param int   $id   The {label} id.
		 * @param array $data Column values.
		 *
		 * @since 1.0.0
		 */
		do_action( '{hp}_update_{sing}', $id, $data );
	}

	return $updated;
}

/**
 * Delete {a} {label}.
 *
 * @param int $id The {label} id.
 *
 * @return bool
 * @since 1.0.0
 */
function {fp}_delete_{sing}( int $id = 0 ): bool {
	if ( $id <= 0 ) {
		return false;
	}

	/**
	 * Fires before {a} {label} is deleted, while it can still be read.
	 *
	 * @param int $id The {label} id.
	 *
	 * @since 1.0.0
	 */
	do_action( '{hp}_before_delete_{sing}', $id );

	$deleted = (bool) ( new {Sing}() )->delete_item( $id );

	if ( $deleted ) {
		/**
		 * Fires once {a} {label} has been deleted.
		 *
		 * @param int $id The id it had.
		 *
		 * @since 1.0.0
		 */
		do_action( '{hp}_delete_{sing}', $id );
	}

	return $deleted;
}
