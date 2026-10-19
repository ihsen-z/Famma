<?php
/**
 * T1.4 — Normalisation and hashing of the CAPI user_data block, against the Meta rules.
 * Run with: php scripts/tests-unit/test-capi-normalisation.php [--json]
 * The real Capi_User_Data::build() (lot 5, A13), Config, Tunisia and Capi_Signals are exercised; no network.
 *
 * Reference: Meta, Conversions API, Customer Information Parameters
 * https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/customer-information-parameters
 * Synthetic identities only (example.invalid, fictitious names).
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded, see bootstrap.php for the doubles.
require __DIR__ . '/bootstrap-capi.php';

/**
 * User data of a website order (browser known), as sent by Capi.
 *
 * @param WC_Order $order Order.
 * @return array
 */
function l5_user_data( WC_Order $order ): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Test helper of a CLI runner.
	return \Famma\Core\Capi_User_Data::build( $order, true );
}

// Meta rules quoted per field (customer-information-parameters page).
$meta_rules = array(
	'em'      => 'Trim any leading and trailing spaces. Convert all characters to lowercase. Hashing required.',
	'ph'      => 'Remove symbols, letters, and any leading zeros. Phone numbers must include a country code. Hashing required.',
	'fn'      => 'Roman alphabet a-z recommended. Lowercase only with no punctuation. Special characters: UTF-8. Hashing required.',
	'ln'      => 'Same rules as fn. Hashing required.',
	'ct'      => 'Lowercase only with no punctuation, no special characters, and no spaces. Special characters: UTF-8. Hashing required.',
	'st'      => '2-character ANSI code in lowercase; outside the U.S.: lowercase with no punctuation, no special characters, and no spaces. Hashing required.',
	'country' => 'Lowercase, 2-letter country codes in ISO 3166-1 alpha-2. Hashing required.',
	'fbc'     => 'Do not hash. fb.{subdomain_index}.{creation_time}.{fbclid}.',
	'fbp'     => 'Do not hash. fb.{subdomain_index}.{creation_time}.{random_number}.',
	'ip'      => 'client_ip_address must never be hashed; valid IPv4 or IPv6.',
);

/*
 * One case = one order field. `accepted` lists every value the Meta rules allow
 * (UTF-8 form and a-z transliteration); null means the field must be omitted.
 */
$cases = array(
	array( 'ph', 'phone', '22123456', array( '21622123456' ) ),
	array( 'ph', 'phone', '22 123 456', array( '21622123456' ) ),
	array( 'ph', 'phone', '+216 22123456', array( '21622123456' ) ),
	array( 'ph', 'phone', '0021622123456', array( '21622123456' ) ),
	array( 'ph', 'phone', '216 22 123 456', array( '21622123456' ) ),
	array( 'ph', 'phone', '٢٢١٢٣٤٥٦', array( '21622123456' ) ),
	array( 'fn', 'first_name', 'Médenine', array( 'médenine', 'medenine' ) ),
	array( 'fn', 'first_name', 'Élodie', array( 'élodie', 'elodie' ) ),
	array( 'fn', 'first_name', 'ÉLODIE', array( 'élodie', 'elodie' ) ),
	array( 'fn', 'first_name', 'محمد', array( 'محمد' ) ),
	array( 'fn', 'first_name', '  Test  ', array( 'test' ) ),
	array( 'ln', 'last_name', 'Ben-Salah', array( 'bensalah', 'ben salah' ) ),
	array( 'ln', 'last_name', 'الطرابلسي', array( 'الطرابلسي' ) ),
	array( 'ct', 'city', 'Médenine', array( 'médenine', 'medenine' ) ),
	array( 'ct', 'city', 'Ben Arous', array( 'benarous' ) ),
	array( 'ct', 'city', 'مدنين', array( 'مدنين' ) ),
	array( 'ct', 'city', 'Sousse', array( 'sousse' ) ),
	array( 'st', 'state', 'TN-82', array( 'tn82', '82', 'medenine', 'médenine' ) ),
	array( 'st', 'state', 'TN-51', array( 'tn51', '51', 'sousse' ) ),
	array( 'country', 'country', 'TN', array( 'tn' ) ),
	array( 'em', 'email', '', null ),
	array( 'em', 'email', '  Client.Test@Example.INVALID ', array( 'client.test@example.invalid' ) ),
);

/*
 * Recognise which plain value a hash corresponds to, among plausible
 * normalisations (accepted Meta values first). `?` when none matches.
 */
$reverse_hash = static function ( string $hash, string $input, array $known ): string {
	$digits     = (string) preg_replace( '/\D+/', '', $input );
	$candidates = array_merge(
		$known,
		array(
			trim( strtolower( $input ) ),
			(string) preg_replace( '/[^a-z]/', '', strtolower( $input ) ),
			trim( mb_strtolower( $input, 'UTF-8' ) ),
			$digits,
			'216' . $digits,
			ltrim( $digits, '0' ),
		)
	);
	foreach ( $candidates as $candidate ) {
		if ( '' !== $candidate && hash( 'sha256', $candidate ) === $hash ) {
			return $candidate;
		}
	}
	return '?';
};

$rows    = array();
$ecarts  = 0;
$base_id = 1400;
foreach ( $cases as $index => $test_case ) {
	list( $key, $field, $input, $accepted ) = $test_case;
	$test_order                             = new WC_Order( $base_id + $index );
	$test_order->$field                     = $input;
	$test_order->country                    = 'country' === $field ? $input : '';
	$user_data                              = l5_user_data( $test_order );
	$sent                                   = $user_data[ $key ] ?? null;
	$accepted_hashes                        = null === $accepted ? array() : array_map(
		static function ( string $plain ): string {
			return hash( 'sha256', $plain );
		},
		$accepted
	);
	$conforme                               = null === $accepted ? null === $sent : in_array( $sent, $accepted_hashes, true );
	$ecarts                                += $conforme ? 0 : 1;
	$rows[]                                 = array(
		'champ'          => $key,
		'entree'         => $input,
		'attendu_meta'   => null === $accepted ? '(omis)' : implode( ' | ', $accepted ),
		'envoye'         => null === $sent ? '(omis)' : $reverse_hash( (string) $sent, $input, $accepted ?? array() ),
		'hash_envoye'    => null === $sent ? '' : substr( (string) $sent, 0, 16 ) . '…',
		'verdict'        => $conforme ? 'CONFORME' : 'ECART',
		'regle_meta'     => $meta_rules[ $key ],
		'cles_user_data' => array_keys( $user_data ),
	);
}

// Raw signals: real Capi_Signals validation, never hashed.
$signal_order                           = new WC_Order( 1499 );
$signal_order->meta['_famma_fbc']       = 'fb.1.1760000000000.IwQATESTfbclidSynthetic';
$signal_order->meta['_famma_fbp']       = 'fb.1.1760000000000.123456789';
$signal_order->meta['_famma_client_ip'] = '2001:db8::1';
$signal_order->meta['_famma_client_ua'] = 'Mozilla/5.0 (QA synthetic)';
$signals                                = l5_user_data( $signal_order );
foreach ( array(
	'fbc' => array( 'fbc', '_famma_fbc' ),
	'fbp' => array( 'fbp', '_famma_fbp' ),
	'ip'  => array( 'client_ip_address', '_famma_client_ip' ),
) as $rule => $pair ) {
	$conforme = ( $signals[ $pair[0] ] ?? '' ) === $signal_order->meta[ $pair[1] ];
	$ecarts  += $conforme ? 0 : 1;
	$rows[]   = array(
		'champ'          => $pair[0],
		'entree'         => $signal_order->meta[ $pair[1] ],
		'attendu_meta'   => 'valeur brute, non hachée',
		'envoye'         => $signals[ $pair[0] ] ?? '(omis)',
		'hash_envoye'    => '',
		'verdict'        => $conforme ? 'CONFORME' : 'ECART',
		'regle_meta'     => $meta_rules[ $rule ],
		'cles_user_data' => array_keys( $signals ),
	);
}

// Fields Meta recommends that user_data() never builds.
$full_order             = new WC_Order( 1498 );
$full_order->email      = 'client.test@example.invalid';
$full_order->phone      = '22123456';
$full_order->first_name = 'Test';
$full_order->last_name  = 'Client';
$full_order->city       = 'Sousse';
$full_order->state      = 'TN-51';
$full_order->country    = 'TN';
$full_keys              = array_keys( l5_user_data( $full_order ) );
foreach ( array( 'external_id', 'zp' ) as $missing_key ) {
	$present = in_array( $missing_key, $full_keys, true );
	// zp is optional for Meta and the Tunisian checkout collects no postcode: information only.
	$verdict = $present ? 'CONFORME' : ( 'zp' === $missing_key ? 'INFO' : 'ECART' );
	$ecarts += 'ECART' === $verdict ? 1 : 0;
	$rows[]  = array(
		'champ'          => $missing_key,
		'entree'         => 'commande complète (e-mail, téléphone, nom, ville, gouvernorat, pays)',
		'attendu_meta'   => 'external_id: hashing recommended; zp: hashing required when sent',
		'envoye'         => $present ? 'présent' : '(jamais construit)',
		'hash_envoye'    => '',
		'verdict'        => $verdict,
		'regle_meta'     => 'external_id recommandé (identifiant annonceur) ; zp facultatif',
		'cles_user_data' => $full_keys,
	);
}

if ( in_array( '--json', $argv, true ) ) {
	echo json_encode(
		array(
			'test'   => 'T1.4',
			'ecarts' => $ecarts,
			'rows'   => $rows,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	) . PHP_EOL;
	exit( 0 );
}
foreach ( $rows as $row ) {
	echo $row['verdict'] . ': ' . $row['champ'] . ' « ' . $row['entree'] . ' » → envoyé « ' . $row['envoye'] . ' » ; attendu Meta « ' . $row['attendu_meta'] . ' »' . PHP_EOL;
}
echo 'CHECKS=' . count( $rows ) . ' ECARTS=' . $ecarts . PHP_EOL;
echo 'No network requests or database writes were performed.' . PHP_EOL;
