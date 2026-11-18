
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
