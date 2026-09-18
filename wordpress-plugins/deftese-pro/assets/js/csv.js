/**
 * Dëftesë PRO — CSV parsing and mapping.
 *
 * The browser importer and the PHP importer read the same spreadsheet layout.
 * Rather than keeping two hand-maintained copies of the row and column numbers,
 * the map is defined once in PHP (DeftesePro\Csv_Map) and handed to this module
 * as data, so the two can never disagree about where a value lives.
 */
( function ( window ) {
	'use strict';

	/**
	 * Parse CSV text into a grid, honouring quoted fields and escaped quotes.
	 *
	 * @param {string} text Raw file contents.
	 * @return {string[][]} Rows of cells.
	 */
	function parse( text ) {
		var rows = [];
		var row = [];
		var value = '';
		var inQuotes = false;
		var i;
		var char;

		for ( i = 0; i < text.length; i++ ) {
			char = text[ i ];

			if ( '"' === char && '"' === text[ i + 1 ] ) {
				value += '"';
				i++;
			} else if ( '"' === char ) {
				inQuotes = ! inQuotes;
			} else if ( ',' === char && ! inQuotes ) {
				row.push( value );
				value = '';
			} else if ( ( '\n' === char || '\r' === char ) && ! inQuotes ) {
				if ( '\r' === char && '\n' === text[ i + 1 ] ) {
					i++;
				}

				row.push( value );
				rows.push( row );
				row = [];
				value = '';
			} else {
				value += char;
			}
		}

		if ( row.length || '' !== value ) {
			row.push( value );
			rows.push( row );
		}

		return rows;
	}

	/**
	 * Repair Albanian diacritics mangled by the spreadsheet export.
	 *
	 * Mirrors DeftesePro\Csv_Importer::clean_text().
	 *
	 * @param {string} value Raw text.
	 * @return {string} Repaired text.
	 */
	function cleanText( value ) {
		if ( ! value ) {
			return '';
		}

		return String( value )
			.trim()
			.replace( /[\x91\x92‘’]/g, "'" )
			.replace( /[\x93\x94“”]/g, '"' )
			.replace( /Shk[ëe.?]lqyesh[ëe.?]m/gi, 'Shkëlqyeshëm' )
			.replace( /Shum[ëe.?]\s+mir[ëe.?]/gi, '@@SHUMEMIRE@@' )
			.replace( /(^|[^a-zA-ZëËçÇ])Mir[ëe.?]($|[^a-zA-ZëËçÇ])/gi, '$1Mirë$2' )
			.replace( /Mjaftuesh[ëe.?]m/gi, 'Mjaftueshëm' )
			.replace( /Pamjaftuesh[ëe.?]m/gi, 'Pamjaftueshëm' )
			.replace( /([A-Za-z])\?([A-Za-z]?)/g, '$1ë$2' )
			.replace( /@@SHUMEMIRE@@/g, 'Shumë mirë' )
			.trim();
	}

	/**
	 * Normalise a points value into the "(n)" form used on the certificate.
	 *
	 * Mirrors DeftesePro\Csv_Importer::format_points().
	 *
	 * @param {string} value Raw cell value.
	 * @return {string} Formatted value.
	 */
	function formatPoints( value ) {
		var text = ( value || '' ).trim();
		var match;

		if ( ! text || 'FALSE' === text ) {
			return '';
		}

		match = text.match( /^\(\s*(\d+(?:\.\d+)?)\s*\)$/ ) || text.match( /^(\d+(?:\.\d+)?)$/ );

		if ( match ) {
			return '(' + ( -1 !== match[ 1 ].indexOf( '.' ) ? parseFloat( match[ 1 ] ).toFixed( 2 ) : match[ 1 ] ) + ')';
		}

		return text;
	}

	/**
	 * Canonical certificate id for a registry number.
	 *
	 * @param {string} registryNo Raw registry number.
	 * @return {string} Canonical id.
	 */
	function deriveId( registryNo ) {
		var match = ( registryNo || '' ).match( /(\d+)\s*[/\-\\]\s*(\d+)/ );

		return match ? match[ 1 ] + '/' + match[ 2 ] : ( registryNo || '' ).trim();
	}

	/**
	 * Apply a spreadsheet map to a parsed grid.
	 *
	 * @param {string[][]} grid Parsed CSV.
	 * @param {Object}     map  Map payload from the server.
	 * @return {{scalars: Object, grades: Array}} Mapped certificate data.
	 */
	function applyMap( grid, map ) {
		var scalars = {};
		var grades = [];
		var columns = map.gradeColumns;
		var row;

		grid.forEach( function ( line ) {
			while ( line.length < map.minColumns ) {
				line.push( '' );
			}
		} );

		function cell( r, c ) {
			return grid[ r ] && grid[ r ][ c ] ? String( grid[ r ][ c ] ).trim() : '';
		}

		Object.keys( map.scalars ).forEach( function ( field ) {
			var rule = map.scalars[ field ];
			var raw = cell( rule.row, rule.col );
			var value = null;
			var match;

			if ( rule.pattern ) {
				match = raw.match( new RegExp( rule.pattern, 'i' ) );

				if ( match ) {
					value = match[ 1 ].trim();
				} else if ( rule.strip ) {
					value = raw.replace( new RegExp( rule.strip, 'i' ), '' ).trim();
				}
			} else if ( rule.strip ) {
				value = raw.replace( new RegExp( rule.strip, 'i' ), '' ).trim();
			} else {
				value = raw;
			}

			// A pattern that never matched and has no fallback leaves the field
			// alone, so an unmapped cell does not blank an existing value.
			if ( null === value ) {
				return;
			}

			if ( rule.collapse ) {
				value = value.replace( /\s+/g, '' );
			}

			if ( rule.clean ) {
				value = cleanText( value );
			}

			scalars[ field ] = value;
		} );

		scalars.registry_no = deriveId( scalars.registry_no || '' );

		for ( row = map.firstGradeRow; row <= map.lastGradeRow; row++ ) {
			grades.push( mapGradeRow( cell, row, map, columns ) );
		}

		return { scalars: scalars, grades: grades };
	}

	/**
	 * Build one grade row from the grid.
	 *
	 * @param {Function} cell    Cell accessor.
	 * @param {number}   row     Spreadsheet row index.
	 * @param {Object}   map     Map payload.
	 * @param {Object}   columns Column indices.
	 * @return {Object} Grade row.
	 */
	function mapGradeRow( cell, row, map, columns ) {
		var isCategory = -1 !== map.categoryRows.indexOf( row );
		var subject = cleanText( cell( row, columns.subject ) );
		var test = cell( row, columns.test );
		var entry;

		if ( ! subject ) {
			subject = cleanText( cell( row, map.subjectFallbackColumn ) );
		}

		entry = {
			cat: isCategory,
			bold: -1 !== map.boldRows.indexOf( row ),
			subject: subject
		};

		[ 'vi', 'vii', 'viii', 'ix' ].forEach( function ( grade ) {
			entry[ grade ] = isCategory ? '' : cleanText( cell( row, columns[ grade ] ) );
			entry[ 'grade_' + grade ] = isCategory ? '' : formatPoints( cell( row, columns[ 'grade_' + grade ] ) );
		} );

		entry.test = ( isCategory || 'FALSE' === test ) ? '' : test;

		return entry;
	}

	window.DeftesePro = window.DeftesePro || {};
	window.DeftesePro.csv = {
		parse: parse,
		cleanText: cleanText,
		formatPoints: formatPoints,
		deriveId: deriveId,
		applyMap: applyMap
	};
}( window ) );
