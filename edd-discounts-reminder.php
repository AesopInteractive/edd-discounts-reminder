<?php
/**
 * Plugin Name:     EDD Discount Reminder
 * Plugin URI:
 * Description:     Send a reminder about discounts about to expire.
 * Version: 0.0.2
 * Author:          Josh Pollock
 * Author URI:      http://JoshPress.net
 * @copyright       Copyright (c) 2015 Josh Pollock
 *
 * Released under the GNU GPL license, version 2 or later.
 *
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

/**
 * Add scheduled event on activation.
 *
 * @since 0.0.1
 */
register_activation_hook( __FILE__, 'edd_discounts_reminder_activation' );
function edd_discounts_reminder_activation() {
	wp_schedule_event( time(), 'twicedaily', 'edd_discounts_reminder_doit_hook' );
}

/**
 * Load class when cron job runs.
 *
 @since 0.0.1
 */
add_action( 'edd_discounts_reminder_doit_hook', 'edd_discounts_reminder_doit' );
function edd_discounts_reminder_doit() {
	new EDD_Discount_Reminder();
}



/**
 * Clear cron job on deactivation
 *
 * @since 0.0.1
 */
register_deactivation_hook( __FILE__, 'edd_discounts_reminder_deactivation' );
function edd_discounts_reminder_deactivation() {
	wp_clear_scheduled_hook( 'edd_discounts_reminder_doit' );
}

class EDD_Discount_Reminder {

	/**
	 * Will hold mailer config, but only if it passes validation.
	 *
	 * @since 0.0.1
	 *
	 * @access protected
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Key used to mark discounts as sent.
	 *
	 * @since 0.0.2
	 *
	 * @var string
	 */
	protected $sent_key = '_edd_discounts_reminder_sent';

	/**
	 * Constructor for class
	 *
	 * @since 0.0.1
	 * 
	 * @param null|array $config Config for mailer. If is null, the default set with 'edd_discounts_reminder_config'
	 */
	public function __construct( $config = null ) {
		$this->set_config( $config );
		if ( is_array( $this->config ) ) {
			$this->maybe_send();
		}

	}

	/**
	 * Send notifications if it is possible to do so.
	 *
	 * @since 0.0.1
	 *
	 * @access protected
	 */
	protected function maybe_send() {
		$discounts = $this->get_discounts_to_notify();
		if ( ! empty( $discounts ) ) {
			$edd_email_class = EDD()->emails;
			foreach( $discounts as $id => $discount ) {
				$sent = $this->send_email( $discount[ 'code' ], $discount[ 'email' ], $edd_email_class );
				if ( $sent ) {
					update_post_meta( $id, $this->sent_key, 'sent' );
				}

			}

		}

	}

	/**
	 * Apply filter, validate and possibly set config parameter for class
	 *
	 * @since 0.0.1
	 *
	 * @access protected
	 * 
	 * @param array|null $config
	 */
	protected function set_config( $config ) {
		/**
		 * Override the config array
		 *
		 * @param array $config
		 */
		$config = apply_filters( 'edd_discounts_reminder_config', $config );
		if (true === $this->validate_config( $config )  ) {
			$this->config = $config;
		}else{
			$this->config = false;
		}


	}

	/**
	 * Validate the config
	 *
	 * @since 0.0.1
	 *
	 * @access protected
	 * 
	 * @param array $config The configuration array to validate
	 *
	 * @return bool
	 */
	protected function validate_config( $config ) {
		if ( is_array( $config ) ) {
			foreach( array(
				'message',
				'subject',
				'from_email',
				'from_name'
			) as $field ) {
				if (! isset( $config[ $field ] ) || ! is_string( $config[ $field ] ) ) {
					return false;

				}

			}

			return true;
		}

	}

	/**
	 * Find discounts to notify
	 *
	 * @since 0.0.1
	 *
	 * @access protected
	 * 
	 * Only returns discounts expiring within 24 hours that have a valid email for the name.
	 *
	 * @return array
	 */
	protected function get_discounts_to_notify() {
		$sends = array();
		$discounts = edd_get_discounts( array( 'active' ) );
		if ( ! empty( $discounts ) ) {
			$now = time();

			foreach ( $discounts as $discount ) {

				$id = $discount->ID;

				if ( 'sent' != get_post_meta( $id, $this->sent_key, true ) ) {
					if ( ! edd_is_discount_expired( $id ) && ! edd_is_discount_maxed_out( $id ) ) {
						$expires = edd_get_discount_expiration( $id );

						if ( $expires && strtotime( $expires ) < $now + DAY_IN_SECONDS ) {
							if ( filter_var( $discount->post_title, FILTER_VALIDATE_EMAIL ) ) {
								$sends[ $id ] = array(
									'code'  => $discount->post_name,
									'email' => $discount->post_title
								);
							}
						}

					}
				}

			}

		}

		return $sends;

	}

	/**
	 * Send an email
	 * 
	 * @since 0.0.1
	 * 
	 * @access protected
	 *
	 * @param string $code Discount code to send
	 * @param string $to_email Email address to send to.
	 * @param object|EDD_Emails $emails Instance of EDD_Emails
	 *
	 * @return bool True if sent.
 	 */
	protected function send_email( $code, $to_email, $emails ) {
		$config = $this->config;
		$emails->__set( 'from_name', $config[ 'from_name' ] );
		$emails->__set( 'from_email', $config[ 'from_email' ] );

		$message = sprintf( $config[ 'message' ], $code );

		$emails->__set( 'headers', $emails->get_headers() );

		$sent = $emails->send( $to_email, $config[ 'subject' ], $message  );

		return $sent;

	}

}


