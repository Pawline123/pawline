<?php
/**
 * Nur lokal: alle E-Mails an Mailpit (http://localhost:8025) statt nach außen senden.
 * Wird NICHT auf den Live-Server übertragen (liegt außerhalb von wp-content/plugins).
 */
defined( 'ABSPATH' ) || exit;

if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
	add_action(
		'phpmailer_init',
		static function ( $mailer ) {
			$mailer->isSMTP();
			$mailer->Host        = 'mailpit';
			$mailer->Port        = 1025;
			$mailer->SMTPAuth    = false;
			$mailer->SMTPAutoTLS = false;
		}
	);
}
