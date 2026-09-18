<?php
/**
 * RFC 6238 time-based one-time passwords.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal, dependency-free TOTP implementation.
 */
final class Totp {

	/**
	 * RFC 4648 base32 alphabet.
	 */
	private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/**
	 * Code length.
	 */
	public const DIGITS = 6;

	/**
	 * Time step in seconds.
	 */
	public const PERIOD = 30;

	/**
	 * Steps of clock drift accepted either side of the current one.
	 */
	public const WINDOW = 1;

	/**
	 * User meta key holding the active shared secret.
	 */
	public const META_SECRET = '_deftese_2fa_secret';

	/**
	 * User meta key holding the enabled flag.
	 */
	public const META_ENABLED = '_deftese_2fa_enabled';

	/**
	 * User meta key holding a secret awaiting confirmation.
	 */
	public const META_PENDING = '_deftese_2fa_secret_pending';

	/**
	 * Decode a base32 string to raw bytes.
	 *
	 * @param string $base32 Encoded secret.
	 * @return string Raw key bytes.
	 */
	public static function base32_decode( string $base32 ): string {
		$base32    = strtoupper( (string) preg_replace( '/[^A-Za-z2-7]/', '', $base32 ) );
		$buffer    = 0;
		$bits_left = 0;
		$result    = '';
		$length    = strlen( $base32 );

		for ( $i = 0; $i < $length; $i++ ) {
			$value = strpos( self::ALPHABET, $base32[ $i ] );

			if ( false === $value ) {
				continue;
			}

			$buffer     = ( $buffer << 5 ) | $value;
			$bits_left += 5;

			if ( $bits_left >= 8 ) {
				$bits_left -= 8;
				$result    .= chr( ( $buffer >> $bits_left ) & 0xFF );
			}
		}

		return $result;
	}

	/**
	 * Generate a fresh base32 shared secret.
	 *
	 * @param int $length Number of base32 characters.
	 * @return string Secret.
	 */
	public static function generate_secret( int $length = 20 ): string {
		$secret = '';
		$max    = strlen( self::ALPHABET ) - 1;

		for ( $i = 0; $i < $length; $i++ ) {
			$secret .= self::ALPHABET[ random_int( 0, $max ) ];
		}

		return $secret;
	}

	/**
	 * Compute the code for one time step.
	 *
	 * @param string $secret    Base32 secret.
	 * @param int    $timeslice Counter value.
	 * @param int    $digits    Code length.
	 * @return string Zero-padded code.
	 */
	public static function code( string $secret, int $timeslice, int $digits = self::DIGITS ): string {
		$key    = self::base32_decode( $secret );
		$time   = pack( 'N*', 0 ) . pack( 'N*', $timeslice );
		$hash   = hash_hmac( 'sha1', $time, $key, true );
		$offset = ord( substr( $hash, -1 ) ) & 0x0F;
		$part   = substr( $hash, $offset, 4 );
		$value  = unpack( 'N', $part )[1] & 0x7FFFFFFF;

		return str_pad( (string) ( $value % ( 10 ** $digits ) ), $digits, '0', STR_PAD_LEFT );
	}

	/**
	 * Verify a submitted code against the secret.
	 *
	 * @param string $secret Base32 secret.
	 * @param string $code   Submitted code.
	 * @param int    $window Accepted drift in steps.
	 * @param int    $period Time step length.
	 * @return bool True when the code is valid.
	 */
	public static function verify( string $secret, string $code, int $window = self::WINDOW, int $period = self::PERIOD ): bool {
		$code = (string) preg_replace( '/\s+/', '', $code );

		if ( '' === $secret || ! preg_match( '/^\d{' . self::DIGITS . '}$/', $code ) ) {
			return false;
		}

		$slice = (int) floor( time() / $period );
		$valid = false;

		// Every step is checked so verification takes constant time regardless
		// of which one matches.
		for ( $i = -$window; $i <= $window; $i++ ) {
			if ( hash_equals( self::code( $secret, $slice + $i ), $code ) ) {
				$valid = true;
			}
		}

		return $valid;
	}

	/**
	 * Build the otpauth:// provisioning URI for authenticator apps.
	 *
	 * @param string $secret     Base32 secret.
	 * @param string $user_login Account name shown in the app.
	 * @return string Provisioning URI.
	 */
	public static function otpauth_uri( string $secret, string $user_login ): string {
		/**
		 * Filters the issuer name shown in authenticator apps.
		 *
		 * @param string $issuer Issuer label.
		 */
		$issuer = (string) apply_filters( 'deftese_2fa_issuer', 'Deftese PRO - Komuna e Prishtines' );

		return 'otpauth://totp/' . rawurlencode( $issuer . ':' . $user_login )
			. '?secret=' . rawurlencode( $secret )
			. '&issuer=' . rawurlencode( $issuer )
			. '&algorithm=SHA1'
			. '&digits=' . self::DIGITS
			. '&period=' . self::PERIOD;
	}

	/**
	 * Whether a user has confirmed two-factor authentication.
	 *
	 * @param int $user_id User id.
	 * @return bool True when enabled.
	 */
	public static function is_enabled( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$secret = get_user_meta( $user_id, self::META_SECRET, true );

		return ! empty( $secret ) && '1' === get_user_meta( $user_id, self::META_ENABLED, true );
	}
}
