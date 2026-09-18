<?php
/**
 * All database access for certificates.
 *
 * Every SQL statement in the plugin lives here, so ownership scoping and
 * placeholder handling can be reviewed in one place instead of being spread
 * across the renderers.
 *
 * @package DeftesePro
 */

declare( strict_types = 1 );

namespace DeftesePro;

defined( 'ABSPATH' ) || exit;

/**
 * Data-access layer for the certificates table.
 */
final class Repository {

	/**
	 * Rows shown per page in the list view.
	 */
	public const PER_PAGE = 50;

	/**
	 * Table name.
	 *
	 * @return string Prefixed table name.
	 */
	private static function table(): string {
		return Schema::table();
	}

	/**
	 * Fetch a single certificate.
	 *
	 * @param string $id Certificate id.
	 * @return array<string,mixed>|null Row, or null when missing.
	 */
	public static function find( string $id ): ?array {
		global $wpdb;

		if ( '' === $id ) {
			return null;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch only the ownership columns of a certificate.
	 *
	 * @param string $id Certificate id.
	 * @return array{id:string,created_by:int}|null Ownership info, or null.
	 */
	public static function find_owner( string $id ): ?array {
		global $wpdb;

		if ( '' === $id ) {
			return null;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, created_by FROM {$table} WHERE id = %s", $id ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return array(
			'id'         => (string) $row['id'],
			'created_by' => (int) $row['created_by'],
		);
	}

	/**
	 * Insert a certificate.
	 *
	 * @param array<string,mixed> $data Column => value.
	 * @return bool True on success.
	 */
	public static function insert( array $data ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->insert( self::table(), $data, self::formats( $data ) );
	}

	/**
	 * Update a certificate by id.
	 *
	 * @param string              $id   Existing certificate id.
	 * @param array<string,mixed> $data Column => value.
	 * @return bool True when the statement executed without error.
	 */
	public static function update( string $id, array $data ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update( self::table(), $data, array( 'id' => $id ), self::formats( $data ), array( '%s' ) );
	}

	/**
	 * Delete one certificate, optionally restricted to an owner.
	 *
	 * @param string   $id    Certificate id.
	 * @param int|null $owner Owner user id, or null for an unrestricted delete.
	 * @return bool True when a row was removed.
	 */
	public static function delete( string $id, ?int $owner = null ): bool {
		global $wpdb;

		$where  = array( 'id' => $id );
		$format = array( '%s' );

		if ( null !== $owner ) {
			$where['created_by'] = $owner;
			$format[]            = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( self::table(), $where, $format );
	}

	/**
	 * Delete several certificates, optionally restricted to an owner.
	 *
	 * @param string[] $ids   Certificate ids.
	 * @param int|null $owner Owner user id, or null for an unrestricted delete.
	 * @return int Number of rows removed.
	 */
	public static function delete_many( array $ids, ?int $owner = null ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'strval', $ids ), 'strlen' ) );

		if ( ! $ids ) {
			return 0;
		}

		$table        = self::table();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
		$args         = $ids;
		$sql          = "DELETE FROM {$table} WHERE id IN ({$placeholders})";

		if ( null !== $owner ) {
			$sql   .= ' AND created_by = %d';
			$args[] = $owner;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Reassign certificates to another user.
	 *
	 * @param string[] $ids         Certificate ids.
	 * @param int      $target_user Destination user id.
	 * @return int Number of rows moved.
	 */
	public static function reassign( array $ids, int $target_user ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'strval', $ids ), 'strlen' ) );

		if ( ! $ids || $target_user <= 0 ) {
			return 0;
		}

		$table        = self::table();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
		$args         = array_merge( array( $target_user ), $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET created_by = %d WHERE id IN ({$placeholders})", $args )
		);
	}

	/**
	 * Query the list view.
	 *
	 * @param array{owner?:int|null,search?:string,orderby?:string,order?:string,page?:int,per_page?:int} $args Query arguments.
	 * @return array{rows:array<int,object>,total:int,pages:int} Result set.
	 */
	public static function paginate( array $args ): array {
		global $wpdb;

		$owner    = $args['owner'] ?? null;
		$search   = trim( (string) ( $args['search'] ?? '' ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, (int) ( $args['per_page'] ?? self::PER_PAGE ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table  = self::table();
		$where  = array( '1 = 1' );
		$params = array();

		if ( null !== $owner ) {
			$where[]  = 'created_by = %d';
			$params[] = (int) $owner;
		}

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(registry_no LIKE %s OR id LIKE %s OR student_name LIKE %s OR school_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$order_sql = self::order_clause( (string) ( $args['orderby'] ?? '' ), (string) ( $args['order'] ?? '' ) );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$rows_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
		$rows_params = array_merge( $params, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ) );

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Build a validated ORDER BY clause.
	 *
	 * Only columns from the allow-list reach the SQL string, so the interpolation
	 * below cannot carry user input.
	 *
	 * @param string $orderby Requested column.
	 * @param string $order   Requested direction.
	 * @return string ORDER BY expression.
	 */
	private static function order_clause( string $orderby, string $order ): string {
		$sortable = Fields::sortable();

		if ( ! isset( $sortable[ $orderby ] ) ) {
			$orderby = 'created_at';
		}

		$order = strtoupper( $order );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$parts = explode( '|', $sortable[ $orderby ] );

		return implode(
			', ',
			array_map(
				static function ( string $expression ) use ( $order ): string {
					return $expression . ' ' . $order;
				},
				$parts
			)
		);
	}

	/**
	 * Find the neighbouring certificate ids for editor navigation.
	 *
	 * The legacy implementation pulled every id the user could see into PHP and
	 * called array_search on it. This asks the database for the two rows it
	 * actually needs, so navigation cost no longer grows with the archive.
	 *
	 * @param string   $id    Current certificate id.
	 * @param int|null $owner Owner user id, or null when the viewer sees everything.
	 * @return array{prev:?string,next:?string} Neighbour ids.
	 */
	public static function neighbours( string $id, ?int $owner ): array {
		global $wpdb;

		$empty = array(
			'prev' => null,
			'next' => null,
		);

		if ( '' === $id ) {
			return $empty;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$current = $wpdb->get_row( $wpdb->prepare( "SELECT id, created_at FROM {$table} WHERE id = %s", $id ), ARRAY_A );

		if ( ! is_array( $current ) ) {
			return $empty;
		}

		$scope  = '';
		$params = array( $current['created_at'], $current['id'] );

		if ( null !== $owner ) {
			$scope    = ' AND created_by = %d';
			$params[] = (int) $owner;
		}

		/*
		 * The list is ordered by created_at DESC, so "previous" is the row that
		 * sorts before the current one in that ordering (a newer row) and "next"
		 * is the row after it (an older row). The id acts as a tiebreaker so
		 * rows sharing a timestamp still have a stable order.
		 */
		$prev_sql = "SELECT id FROM {$table}
			WHERE (created_at > %s OR (created_at = %s AND id < %s)){$scope}
			ORDER BY created_at ASC, id DESC
			LIMIT 1";

		$next_sql = "SELECT id FROM {$table}
			WHERE (created_at < %s OR (created_at = %s AND id > %s)){$scope}
			ORDER BY created_at DESC, id ASC
			LIMIT 1";

		$prev_params = array_merge( array( $current['created_at'] ), $params );
		$next_params = array_merge( array( $current['created_at'] ), $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$prev = $wpdb->get_var( $wpdb->prepare( $prev_sql, $prev_params ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$next = $wpdb->get_var( $wpdb->prepare( $next_sql, $next_params ) );

		return array(
			'prev' => ( null === $prev || '' === $prev ) ? null : (string) $prev,
			'next' => ( null === $next || '' === $next ) ? null : (string) $next,
		);
	}

	/**
	 * Certificate counts grouped by author, for the admin landing screen.
	 *
	 * @return array<int,object> Rows of created_by, cert_count, display_name, user_login.
	 */
	public static function counts_by_user(): array {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT c.created_by, COUNT(c.id) AS cert_count, u.display_name, u.user_login
			 FROM {$table} c
			 LEFT JOIN {$wpdb->users} u ON u.ID = c.created_by
			 GROUP BY c.created_by, u.display_name, u.user_login
			 ORDER BY cert_count DESC"
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Rows for the categorised overview screen.
	 *
	 * Only the columns the overview renders are selected; the legacy query
	 * pulled `c.*`, which dragged every certificate's LONGTEXT grade sheet into
	 * memory to print a summary table.
	 *
	 * @return array<int,array<string,mixed>> Overview rows.
	 */
	public static function overview_rows(): array {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT c.id, c.registry_no, c.student_name, c.school_name, c.school_year,
			        c.class_teacher, c.created_at, c.created_by,
			        u.display_name AS author_name, u.user_login AS author_login
			 FROM {$table} c
			 LEFT JOIN {$wpdb->users} u ON u.ID = c.created_by
			 ORDER BY c.created_at DESC",
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Derive $wpdb format specifiers from the data types being written.
	 *
	 * The legacy code hand-indexed the format array by position, which silently
	 * broke whenever a column was added or reordered.
	 *
	 * @param array<string,mixed> $data Column => value.
	 * @return string[] Format specifiers in the same order as $data.
	 */
	private static function formats( array $data ): array {
		$formats = array();

		foreach ( $data as $value ) {
			$formats[] = is_int( $value ) ? '%d' : '%s';
		}

		return $formats;
	}
}
