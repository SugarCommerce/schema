<?php
/**
 * Registering this plugin's own components.
 *
 * @package     Acme\Database\Components
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

namespace Acme\Database\Components;

use Acme\Database\Queries;

defined( 'ABSPATH' ) || exit;
use Acme\Database\Rows;
use Acme\Database\Schemas;
use Acme\Database\Tables;

/**
 * The components Acme ships with.
 *
 * Registered on the same action an add-on would use, deliberately: if the
 * core components took a private path, the public one would be the part
 * nobody tested. Anything that breaks for an add-on breaks here first.
 *
 * Generated from bin/schema.json.
 *
 * @since 1.0.0
 */
final class Core {

	/**
	 * Hook registration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'acme_register_components', array( self::class, 'register' ) );
	}

	/**
	 * Register every component.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register(): void {
		acme_register_component(
			'ticket',
			array(
				'schema' => Schemas\Tickets::class,
				'table'  => Tables\Tickets::class,
				'query'  => Queries\Ticket::class,
				'row'    => Rows\Ticket::class,
				'meta'   => Tables\TicketMeta::class,
			)
		);

		acme_register_component(
			'ticket_reply',
			array(
				'schema' => Schemas\TicketReplies::class,
				'table'  => Tables\TicketReplies::class,
				'query'  => Queries\TicketReply::class,
				'row'    => Rows\TicketReply::class,
				'meta'   => false,
			)
		);

		acme_register_component(
			'audit_entry',
			array(
				'schema' => Schemas\AuditEntries::class,
				'table'  => Tables\AuditEntries::class,
				'query'  => Queries\AuditEntry::class,
				'row'    => Rows\AuditEntry::class,
				'meta'   => Tables\AuditEntryMeta::class,
			)
		);
	}
}
