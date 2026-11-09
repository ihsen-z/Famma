<?php
/**
 * Saisies des visiteurs — exports.
 *
 * 1. Excel (.xlsx) : TOUS les champs de chaque ligne, pour les filtres en
 *    vigueur (statut, recherche, produit, période).
 * 2. CSV « audience Meta » : téléphone +216, prénom, gouvernorat, pays — le
 *    seul format que le Gestionnaire de publicités accepte pour une audience
 *    « liste de clients ».
 *
 * Chaque export : nonce, puis `manage_woocommerce`, puis filtres assainis.
 * Les fichiers contiennent des données personnelles : ils ne sont jamais
 * gardés sur le serveur (fichier temporaire supprimé après l'envoi).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Export Excel complet et export CSV pour audience Meta.
 */
final class Visitor_Inputs_Export {

	/** Action admin-post : export Excel. */
	public const ACTION_XLSX = 'famma_inputs_export';

	/** Action admin-post : export CSV pour audience Meta. */
	public const ACTION_AUDIENCE = 'famma_inputs_audience';

	/** Lignes exportées au plus. */
	private const LIMIT = 5000;

	/**
	 * Instance partagée.
	 *
	 * @var Visitor_Inputs_Export|null
	 */
	private static ?Visitor_Inputs_Export $i = null;

	/**
	 * Renvoie l'instance partagée, crochets posés au premier appel.
	 *
	 * @return Visitor_Inputs_Export
	 */
	public static function instance(): Visitor_Inputs_Export {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_post_' . self::ACTION_XLSX, array( self::$i, 'xlsx' ) );
			add_action( 'admin_post_' . self::ACTION_AUDIENCE, array( self::$i, 'audience' ) );
		}
		return self::$i;
	}

	/**
	 * Nonce vérifié, puis droit ; renvoie les lignes des filtres postés.
	 *
	 * @param string $action Action (nom du nonce).
	 * @return array<int, array<string, string>>
	 */
	private function rows( string $action ): array {
		check_admin_referer( $action );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		return Visitor_Inputs_Rows::rows( Visitor_Inputs_Rows::filters( $_POST ), self::LIMIT ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked just above; filters() sanitizes each key.
	}

	/**
	 * Colonnes de l'export Excel : intitulé et largeur.
	 *
	 * @return array<int, array{0: string, 1: int}>
	 */
	private static function columns(): array {
		return array(
			array( __( 'N° saisie', 'famma-core' ), 9 ),
			array( __( 'Première saisie', 'famma-core' ), 17 ),
			array( __( 'Dernière saisie', 'famma-core' ), 17 ),
			array( __( 'Prénom', 'famma-core' ), 18 ),
			array( __( 'Téléphone tapé', 'famma-core' ), 15 ),
			array( __( 'Téléphone (+216)', 'famma-core' ), 15 ),
			array( __( 'Gouvernorat', 'famma-core' ), 14 ),
			array( __( 'Adresse', 'famma-core' ), 30 ),
			array( __( 'Ville', 'famma-core' ), 16 ),
			array( __( 'Produit', 'famma-core' ), 28 ),
			array( __( 'ID produit', 'famma-core' ), 10 ),
			array( __( 'Taille', 'famma-core' ), 8 ),
			array( __( 'Quantité', 'famma-core' ), 9 ),
			array( __( 'Page', 'famma-core' ), 30 ),
			array( __( 'Source', 'famma-core' ), 12 ),
			array( __( 'Support', 'famma-core' ), 12 ),
			array( __( 'Campagne', 'famma-core' ), 24 ),
			array( __( 'Contenu (pub)', 'famma-core' ), 24 ),
			array( __( 'Appareil', 'famma-core' ), 10 ),
			array( __( 'Statut', 'famma-core' ), 14 ),
			array( __( 'Remarque', 'famma-core' ), 36 ),
			array( __( 'N° commande', 'famma-core' ), 12 ),
			array( __( 'Visiteur', 'famma-core' ), 38 ),
			array( __( 'Session', 'famma-core' ), 38 ),
		);
	}

	/**
	 * Date locale lisible d'une date UTC de la table.
	 *
	 * @param string $utc Date « Y-m-d H:i:s » en UTC.
	 * @return string
	 */
	private static function date( string $utc ): string {
		$ts = strtotime( $utc . ' UTC' );
		return false === $ts ? '' : (string) wp_date( 'd/m/Y H:i', $ts );
	}

	/**
	 * Export Excel de toutes les colonnes.
	 *
	 * @return void
	 */
	public function xlsx(): void {
		$rows    = $this->rows( self::ACTION_XLSX );
		$states  = Visitor_Inputs::states();
		$labels  = Visitor_Inputs_Views::status_labels();
		$columns = self::columns();
		$sheet   = new Xlsx_Writer( __( 'Saisies', 'famma-core' ) );
		$sheet->header( array_column( $columns, 0 ), array_column( $columns, 1 ) );
		foreach ( $rows as $row ) {
			$product = (int) $row['product_id'];
			$sheet->row(
				array(
					(int) $row['id'],
					self::date( $row['created_at'] ),
					self::date( $row['updated_at'] ),
					$row['first_name'],
					$row['phone'],
					'' !== $row['phone_norm'] ? '+216' . $row['phone_norm'] : '',
					$states[ $row['state'] ] ?? $row['state'],
					$row['address'],
					$row['city'],
					$product ? (string) get_post_field( 'post_title', $product, 'raw' ) : '',
					$product ? $product : '',
					$row['size'],
					(int) $row['quantity'] ? (int) $row['quantity'] : '',
					$row['path'],
					$row['source'],
					$row['medium'],
					$row['campaign'],
					$row['content'],
					$row['device'],
					$labels[ $row['status'] ] ?? $row['status'],
					$row['note'] ?? '',
					(int) $row['order_id'] ? (int) $row['order_id'] : '',
					$row['visitor_id'],
					$row['session_id'],
				)
			);
		}
		$sheet->send( 'famma-saisies-' . wp_date( 'Y-m-d-His' ) . '.xlsx' );
	}

	/**
	 * Export CSV pour une audience Meta « liste de clients ». Seules les
	 * lignes avec un numéro tunisien valide sortent.
	 *
	 * @return void
	 */
	public function audience(): void {
		$rows   = $this->rows( self::ACTION_AUDIENCE );
		$states = Visitor_Inputs::states();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=famma-audience-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}
		fputcsv( $out, array( 'phone', 'fn', 'st', 'country' ) );
		foreach ( $rows as $row ) {
			if ( '' === $row['phone_norm'] ) {
				continue;
			}
			// Préfixe « = - + @ » neutralisé : le fichier peut être ouvert dans un tableur.
			$name = preg_replace( '/^[=\-+@]+/', '', (string) strtok( $row['first_name'], ' ' ) );
			fputcsv( $out, array( '+216' . $row['phone_norm'], $name, $states[ $row['state'] ] ?? '', 'TN' ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a file.
		exit;
	}
}
