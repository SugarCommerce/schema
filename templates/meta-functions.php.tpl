
/**
 * Add a meta value to {a} {label}.
 *
 * @param int    $id     The {label} id.
 * @param string $key    Meta key.
 * @param mixed  $value  Meta value.
 * @param bool   $unique Whether to fail if the key already exists.
 *
 * @return int|false
 * @since 1.0.0
 */
function {fp}_add_{sing}_meta( int $id, string $key, $value, bool $unique = false ) {
	return add_metadata( '{meta_type}', $id, $key, $value, $unique );
}

/**
 * Read meta from {a} {label}.
 *
 * @param int    $id     The {label} id.
 * @param string $key    Meta key. Empty returns everything.
 * @param bool   $single Whether to return a single value.
 *
 * @return mixed
 * @since 1.0.0
 */
function {fp}_get_{sing}_meta( int $id, string $key = '', bool $single = false ) {
	return get_metadata( '{meta_type}', $id, $key, $single );
}

/**
 * Update meta on {a} {label}.
 *
 * @param int    $id    The {label} id.
 * @param string $key   Meta key.
 * @param mixed  $value Meta value.
 * @param mixed  $prev  Only update rows matching this previous value.
 *
 * @return int|bool
 * @since 1.0.0
 */
function {fp}_update_{sing}_meta( int $id, string $key, $value, $prev = '' ) {
	return update_metadata( '{meta_type}', $id, $key, $value, $prev );
}

/**
 * Delete meta from {a} {label}.
 *
 * @param int    $id    The {label} id.
 * @param string $key   Meta key.
 * @param mixed  $value Only delete rows matching this value.
 *
 * @return bool
 * @since 1.0.0
 */
function {fp}_delete_{sing}_meta( int $id, string $key, $value = '' ): bool {
	return delete_metadata( '{meta_type}', $id, $key, $value );
}
