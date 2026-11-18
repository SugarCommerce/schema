<?php
/**
 * A single ticket.
 *
 * @package     Acme\Database\Rows
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

namespace Acme\Database\Rows;

use Acme\Vendor\BerlinDB\Database\Kern\Row;

defined( 'ABSPATH' ) || exit;

/**
 * One ticket.
 *
 * Properties are declared rather than left dynamic: PHP 8.2 deprecates
 * dynamic properties, and an undeclared column would be a notice on every
 * read rather than anything obvious.
 *
 * @since 1.0.0
 */
class Ticket extends Row {

	use Concerns\Ticket;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $id;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $title;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $status;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $urgent;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $customer_id;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $reply_count;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $amount_paid;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $refund_value;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $currency;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $country;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $email;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $ip;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $stripe_charge_id;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $body;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $payload;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $tax_rate;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $weight;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $label_color;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $icon;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $path;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $sku;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $notes;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $card_last4;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $channel_type;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $stage;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $date_closed;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $uuid;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $date_created;

	/**
	 * @var mixed
	 * @since 1.0.0
	 */
	public $date_modified;

	/**
	 * Constructor.
	 *
	 * @param mixed $item Row from the database.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $item ) {
		parent::__construct( $item );

		$this->id = (int) $this->id;
	}
}
