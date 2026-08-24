<?php
/**
 * Compile un fichier .po en .mo, sans dépendre de gettext.
 *
 * `msgfmt` n'existe ni sur la machine de développement Windows, ni dans l'image
 * Docker du projet. Sans lui, un fichier de traduction reste un fichier texte
 * que WordPress n'ouvre jamais : la traduction serait écrite et invisible.
 *
 * Le format .mo est délibérément simple — un en-tête, deux tables d'offsets,
 * deux blocs de chaînes — et c'est cette simplicité qui rend ce script court
 * plutôt qu'un portage d'outil. Les formes plurielles sont gérées : gettext les
 * stocke comme une seule entrée dont les parties sont séparées par un octet nul.
 *
 * Usage:  php scripts/po-to-mo.php <fichier.po> [fichier.mo]
 *
 * @package famma-child
 */

declare( strict_types = 1 );

/**
 * Retire les guillemets et les échappements d'une chaîne .po.
 *
 * @param string $raw Fragment de ligne, guillemets compris.
 * @return string
 */
function famma_po_unquote( string $raw ): string {
	$raw = trim( $raw );

	if ( '' === $raw || '"' !== $raw[0] ) {
		return '';
	}

	$raw = substr( $raw, 1, -1 );

	return str_replace(
		array( '\\n', '\\t', '\\r', '\\"', '\\\\' ),
		array( "\n", "\t", "\r", '"', '\\' ),
		$raw
	);
}

/**
 * Analyse un .po et retourne les paires msgid => msgstr prêtes pour le .mo.
 *
 * @param string $path Chemin du fichier .po.
 * @return array<string, string>
 */
function famma_po_parse( string $path ): array {
	$lines = file( $path, FILE_IGNORE_NEW_LINES );

	if ( false === $lines ) {
		fwrite( STDERR, "Lecture impossible : {$path}\n" );
		exit( 1 );
	}

	$entries = array();
	$current = array(
		'ctxt'   => null,
		'id'     => null,
		'plural' => null,
		'str'    => array(),
	);
	$field   = null;
	$index   = 0;

	$flush = static function () use ( &$entries, &$current ): void {
		if ( null === $current['id'] ) {
			return;
		}

		$key = $current['id'];

		if ( null !== $current['ctxt'] && '' !== $current['ctxt'] ) {
			$key = $current['ctxt'] . "\4" . $key;
		}

		if ( null !== $current['plural'] ) {
			$key .= "\0" . $current['plural'];
			ksort( $current['str'] );
			$value = implode( "\0", $current['str'] );
		} else {
			$value = $current['str'][0] ?? '';
		}

		// Une entrée non traduite doit être absente du .mo : présente et vide,
		// elle ferait afficher du vide au lieu du texte source.
		if ( '' !== $value ) {
			$entries[ $key ] = $value;
		}

		$current = array(
			'ctxt'   => null,
			'id'     => null,
			'plural' => null,
			'str'    => array(),
		);
	};

	foreach ( $lines as $line ) {
		$trimmed = trim( $line );

		if ( '' === $trimmed || '#' === ( $trimmed[0] ?? '' ) ) {
			continue;
		}

		if ( 0 === strpos( $trimmed, 'msgctxt ' ) ) {
			$flush();
			$current['ctxt'] = famma_po_unquote( substr( $trimmed, 8 ) );
			$field           = 'ctxt';
			continue;
		}

		if ( 0 === strpos( $trimmed, 'msgid_plural ' ) ) {
			$current['plural'] = famma_po_unquote( substr( $trimmed, 13 ) );
			$field             = 'plural';
			continue;
		}

		if ( 0 === strpos( $trimmed, 'msgid ' ) ) {
			$flush();
			$current['id'] = famma_po_unquote( substr( $trimmed, 6 ) );
			$field         = 'id';
			continue;
		}

		if ( 1 === preg_match( '/^msgstr\[(\d+)\]\s+(.*)$/', $trimmed, $m ) ) {
			$index                    = (int) $m[1];
			$current['str'][ $index ] = famma_po_unquote( $m[2] );
			$field                    = 'str';
			continue;
		}

		if ( 0 === strpos( $trimmed, 'msgstr ' ) ) {
			$index             = 0;
			$current['str'][0] = famma_po_unquote( substr( $trimmed, 7 ) );
			$field             = 'str';
			continue;
		}

		// Ligne de continuation : une chaîne entre guillemets, seule.
		if ( '"' === $trimmed[0] ) {
			$piece = famma_po_unquote( $trimmed );

			switch ( $field ) {
				case 'ctxt':
					$current['ctxt'] .= $piece;
					break;
				case 'id':
					$current['id'] .= $piece;
					break;
				case 'plural':
					$current['plural'] .= $piece;
					break;
				case 'str':
					$current['str'][ $index ] = ( $current['str'][ $index ] ?? '' ) . $piece;
					break;
			}
		}
	}

	$flush();

	return $entries;
}

/**
 * Sérialise les entrées au format .mo.
 *
 * @param array<string, string> $entries Paires msgid => msgstr.
 * @return string
 */
function famma_mo_build( array $entries ): string {
	ksort( $entries );

	$count       = count( $entries );
	$ids         = '';
	$strs        = '';
	$id_table    = '';
	$str_table   = '';
	$ids_offset  = 28 + $count * 16;
	$strs_offset = 0;

	foreach ( $entries as $id => $str ) {
		$id_table .= pack( 'VV', strlen( $id ), $ids_offset + strlen( $ids ) );
		$ids      .= $id . "\0";
	}

	$strs_offset = $ids_offset + strlen( $ids );

	foreach ( $entries as $str ) {
		$str_table .= pack( 'VV', strlen( $str ), $strs_offset + strlen( $strs ) );
		$strs      .= $str . "\0";
	}

	/*
	 * `hash_addr` doit pointer la fin des deux tables, même sans table de
	 * hachage. Ce n'est pas une coquetterie : le lecteur de WordPress
	 * (wp-includes/pomo/mo.php) déduit la taille de la table des traductions
	 * par `hash_addr - translations_lengths_addr` et refuse le fichier si le
	 * compte ne tombe pas juste. Un `hash_addr` à zéro donne une taille
	 * négative, et le .mo est rejeté en silence — traduction écrite, jamais
	 * affichée.
	 */
	$hash_addr = 28 + $count * 16;

	return pack(
		'VVVVVVV',
		0x950412de, // Nombre magique, petit-boutien.
		0,          // Révision du format.
		$count,
		28,                 // Offset de la table des msgid.
		28 + $count * 8,    // Offset de la table des msgstr.
		0,                  // Taille de la table de hachage : aucune.
		$hash_addr
	) . $id_table . $str_table . $ids . $strs;
}

$po = $argv[1] ?? '';

if ( '' === $po || ! is_readable( $po ) ) {
	fwrite( STDERR, "Usage : php scripts/po-to-mo.php <fichier.po> [fichier.mo]\n" );
	exit( 1 );
}

$mo      = $argv[2] ?? preg_replace( '/\.po$/', '.mo', $po );
$entries = famma_po_parse( $po );

if ( array() === $entries ) {
	fwrite( STDERR, "Aucune traduction trouvée dans {$po}\n" );
	exit( 1 );
}

file_put_contents( $mo, famma_mo_build( $entries ) );

printf( '%s : %d entrées, %d octets%s', basename( (string) $mo ), count( $entries ), filesize( (string) $mo ), PHP_EOL );
