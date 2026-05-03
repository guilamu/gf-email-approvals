<?php

if ( $argc < 3 ) {
	fwrite( STDERR, "Usage: php tools/compile-po-to-mo.php input.po output.mo\n" );
	exit( 1 );
}

/**
 * Unescapes a PO string token.
 *
 * @param string $value Raw PO token.
 *
 * @return string
 */
function gf_email_approvals_po_unescape( $value ) {
	return stripcslashes( $value );
}

/**
 * Stores the currently parsed entry.
 *
 * @param array<string, string>                                            $entries Parsed translation entries.
 * @param array{msgid:string, msgstr:array<int, string>, msgid_plural?:string}|null $entry   Current entry.
 *
 * @return void
 */
function gf_email_approvals_finalize_po_entry( &$entries, &$entry ) {
	if ( null === $entry || ! isset( $entry['msgid'] ) ) {
		$entry = null;

		return;
	}

	/** @var array{msgid:string, msgstr:array<int, string>, msgid_plural?:string} $entry */

	if ( isset( $entry['msgid_plural'] ) ) {
		ksort( $entry['msgstr'] );
		$entries[ $entry['msgid'] . "\0" . $entry['msgid_plural'] ] = implode( "\0", $entry['msgstr'] );
	} else {
		$entries[ $entry['msgid'] ] = isset( $entry['msgstr'][0] ) ? $entry['msgstr'][0] : '';
	}

	$entry = null;
}

$po_file = $argv[1];
$mo_file = $argv[2];
$lines   = file( $po_file, FILE_IGNORE_NEW_LINES );

if ( false === $lines ) {
	fwrite( STDERR, "Unable to read PO file.\n" );
	exit( 1 );
}

$entries      = array();
$entry        = null;
$state        = null;
$msgstr_index = 0;

foreach ( $lines as $line ) {
	$trimmed = trim( $line );

	if ( '' === $trimmed ) {
		gf_email_approvals_finalize_po_entry( $entries, $entry );
		$state        = null;
		$msgstr_index = 0;

		continue;
	}

	if ( 0 === strpos( $trimmed, '#' ) ) {
		continue;
	}

	if ( preg_match( '/^msgid\s+"(.*)"$/', $trimmed, $matches ) ) {
		gf_email_approvals_finalize_po_entry( $entries, $entry );
		$entry = array(
			'msgid'  => gf_email_approvals_po_unescape( $matches[1] ),
			'msgstr' => array(),
		);
		$state = 'msgid';

		continue;
	}

	if ( preg_match( '/^msgid_plural\s+"(.*)"$/', $trimmed, $matches ) ) {
		$entry['msgid_plural'] = gf_email_approvals_po_unescape( $matches[1] );
		$state                 = 'msgid_plural';

		continue;
	}

	if ( preg_match( '/^msgstr(?:\[(\d+)\])?\s+"(.*)"$/', $trimmed, $matches ) ) {
		$msgstr_index              = isset( $matches[1] ) && '' !== $matches[1] ? (int) $matches[1] : 0;
		$entry['msgstr'][ $msgstr_index ] = gf_email_approvals_po_unescape( $matches[2] );
		$state                     = 'msgstr';

		continue;
	}

	if ( preg_match( '/^"(.*)"$/', $trimmed, $matches ) ) {
		$value = gf_email_approvals_po_unescape( $matches[1] );

		if ( 'msgid' === $state ) {
			$entry['msgid'] .= $value;
		} elseif ( 'msgid_plural' === $state ) {
			$entry['msgid_plural'] .= $value;
		} elseif ( 'msgstr' === $state ) {
			$entry['msgstr'][ $msgstr_index ] .= $value;
		}
	}
}

gf_email_approvals_finalize_po_entry( $entries, $entry );
ksort( $entries, SORT_STRING );

$count               = count( $entries );
$originals           = '';
$translations        = '';
$original_offsets    = array();
$translation_offsets = array();

foreach ( $entries as $original => $translation ) {
	$original_offsets[]    = array( strlen( $original ), strlen( $originals ) );
	$translation_offsets[] = array( strlen( $translation ), strlen( $translations ) );
	$originals            .= $original . "\0";
	$translations         .= $translation . "\0";
}

$original_table_offset    = 28;
$translation_table_offset = $original_table_offset + ( $count * 8 );
$original_string_offset   = $translation_table_offset + ( $count * 8 );
$translation_string_offset = $original_string_offset + strlen( $originals );

$mo = pack( 'V7', 0x950412de, 0, $count, $original_table_offset, $translation_table_offset, 0, 0 );

foreach ( $original_offsets as $offset ) {
	$mo .= pack( 'V2', $offset[0], $original_string_offset + $offset[1] );
}

foreach ( $translation_offsets as $offset ) {
	$mo .= pack( 'V2', $offset[0], $translation_string_offset + $offset[1] );
}

$mo .= $originals . $translations;

if ( false === file_put_contents( $mo_file, $mo ) ) {
	fwrite( STDERR, "Unable to write MO file.\n" );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Compiled %s to %s\n", $po_file, $mo_file ) );