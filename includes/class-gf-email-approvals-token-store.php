<?php

defined( 'ABSPATH' ) || exit;

/**
 * Stores one-time approval links for entries.
 */
class GFEmailApprovalsTokenStore {
	/**
	 * Returns the full token table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'gf_email_approval_tokens';
	}

	/**
	 * Creates or updates the token table.
	 *
	 * @return void
	 */
	public static function maybe_create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) unsigned NOT NULL,
			form_id bigint(20) unsigned NOT NULL,
			notification_id varchar(40) NOT NULL,
			action varchar(20) NOT NULL,
			recipient_email text NOT NULL,
			token_hash char(64) NOT NULL,
			created_at datetime NOT NULL,
			used_at datetime NULL,
			invalidated_at datetime NULL,
			used_ip varchar(100) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY entry_id (entry_id),
			KEY form_id (form_id),
			KEY notification_id (notification_id),
			KEY action (action)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Issues a new token for an entry and action.
	 *
	 * @param array  $entry           The entry object.
	 * @param string $notification_id The notification identifier.
	 * @param string $action          The target status.
	 * @param string $recipient_email The resolved recipient email.
	 *
	 * @return string
	 */
	public static function generate_token( $entry, $notification_id, $action, $recipient_email ) {
		global $wpdb;

		$entry_id   = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;
		$form_id    = isset( $entry['form_id'] ) ? absint( $entry['form_id'] ) : 0;
		$token      = bin2hex( random_bytes( 32 ) );
		$token_hash = hash( 'sha256', $token );
		$table_name = self::get_table_name();

		$wpdb->insert(
			$table_name,
			array(
				'entry_id'         => $entry_id,
				'form_id'          => $form_id,
				'notification_id'  => (string) $notification_id,
				'action'           => (string) $action,
				'recipient_email'  => (string) $recipient_email,
				'token_hash'       => $token_hash,
				'created_at'       => current_time( 'mysql', true ),
				'used_at'          => null,
				'invalidated_at'   => null,
				'used_ip'          => null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $token;
	}

	/**
	 * Finds a token record from its raw token value.
	 *
	 * @param string $token       The raw token.
	 * @param bool   $active_only Whether only active tokens should be returned.
	 *
	 * @return object|null
	 */
	public static function get_token_record( $token, $active_only = false ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$token_hash = hash( 'sha256', $token );
		$sql        = "SELECT * FROM {$table_name} WHERE token_hash = %s";

		if ( $active_only ) {
			$sql .= ' AND used_at IS NULL AND invalidated_at IS NULL';
		}

		$sql .= ' LIMIT 1';

		return $wpdb->get_row( $wpdb->prepare( $sql, $token_hash ) );
	}

	/**
	 * Marks a token as used.
	 *
	 * @param int    $token_id The token id.
	 * @param string $ip       The caller IP.
	 *
	 * @return void
	 */
	public static function mark_token_used( $token_id, $ip ) {
		global $wpdb;

		$wpdb->update(
			self::get_table_name(),
			array(
				'used_at' => current_time( 'mysql', true ),
				'used_ip' => $ip,
			),
			array( 'id' => absint( $token_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Invalidates all still-active tokens for an entry.
	 *
	 * @param int $entry_id    The entry id.
	 * @param int $exclude_id  Optional token id to keep untouched.
	 *
	 * @return void
	 */
	public static function invalidate_entry_tokens( $entry_id, $exclude_id = 0 ) {
		global $wpdb;

		$table_name   = self::get_table_name();
		$entry_id     = absint( $entry_id );
		$exclude_id   = absint( $exclude_id );
		$invalidated  = current_time( 'mysql', true );
		$sql          = "UPDATE {$table_name} SET invalidated_at = %s WHERE entry_id = %d AND used_at IS NULL AND invalidated_at IS NULL";
		$parameters   = array( $invalidated, $entry_id );

		if ( $exclude_id > 0 ) {
			$sql         .= ' AND id <> %d';
			$parameters[] = $exclude_id;
		}

		$wpdb->query( $wpdb->prepare( $sql, $parameters ) );
	}
}