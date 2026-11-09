<?php
/**
 * Petit générateur de fichier Excel (.xlsx), sans dépendance.
 *
 * Une feuille, une ligne d'en-tête en gras figée, filtres automatiques,
 * largeurs de colonnes. Les textes sont des chaînes « en ligne » : jamais
 * évalués comme formules, même s'ils commencent par « = » (pas d'injection
 * de formule). Les nombres restent des nombres.
 *
 * Archive écrite avec `ZipArchive` si l'extension est là, sinon avec PclZip
 * livré par WordPress : l'export marche sur tout hébergement.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Feuille Excel unique, construite en mémoire puis envoyée au navigateur.
 */
final class Xlsx_Writer {

	/**
	 * Nom de la feuille.
	 *
	 * @var string
	 */
	private string $sheet;

	/**
	 * Lignes déjà converties en XML.
	 *
	 * @var array<int, string>
	 */
	private array $rows = array();

	/**
	 * Nombre de colonnes (en-tête).
	 *
	 * @var int
	 */
	private int $cols = 0;

	/**
	 * Largeurs des colonnes, en caractères.
	 *
	 * @var array<int, int>
	 */
	private array $widths = array();

	/**
	 * Nouvelle feuille.
	 *
	 * @param string $sheet Nom de la feuille (31 caractères au plus).
	 */
	public function __construct( string $sheet ) {
		$this->sheet = mb_substr( (string) preg_replace( '/[\\\\\/?*\[\]:]/', ' ', $sheet ), 0, 31 );
	}

	/**
	 * Ligne d'en-tête (en gras) et largeurs de colonnes.
	 *
	 * @param array<int, string> $labels Intitulés.
	 * @param array<int, int>    $widths Largeurs en caractères.
	 * @return void
	 */
	public function header( array $labels, array $widths = array() ): void {
		$this->cols   = count( $labels );
		$this->widths = $widths;
		$this->add( $labels, 1 );
	}

	/**
	 * Ajoute une ligne de valeurs (texte ou nombre).
	 *
	 * @param array<int, int|float|string> $values Valeurs, dans l'ordre des colonnes.
	 * @return void
	 */
	public function row( array $values ): void {
		$this->add( $values, 0 );
	}

	/**
	 * Convertit une ligne en XML.
	 *
	 * @param array<int, int|float|string> $values Valeurs.
	 * @param int                          $style  Style (0 normal, 1 en-tête).
	 * @return void
	 */
	private function add( array $values, int $style ): void {
		$r     = count( $this->rows ) + 1;
		$cells = '';
		foreach ( array_values( $values ) as $i => $value ) {
			$ref = self::column( $i ) . $r;
			$s   = $style ? ' s="1"' : '';
			if ( is_int( $value ) || is_float( $value ) ) {
				$cells .= '<c r="' . $ref . '"' . $s . '><v>' . $value . '</v></c>';
			} elseif ( '' !== (string) $value ) {
				$cells .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . self::xml( (string) $value ) . '</t></is></c>';
			}
		}
		$this->rows[] = '<row r="' . $r . '">' . $cells . '</row>';
	}

	/**
	 * Lettre(s) d'une colonne : 0 → A, 26 → AA.
	 *
	 * @param int $index Index à partir de 0.
	 * @return string
	 */
	private static function column( int $index ): string {
		$out = '';
		for ( $n = $index + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$out = chr( 65 + ( $n - 1 ) % 26 ) . $out;
		}
		return $out;
	}

	/**
	 * Texte sûr pour XML : caractères interdits retirés, entités échappées.
	 *
	 * @param string $text Texte.
	 * @return string
	 */
	private static function xml( string $text ): string {
		$text = (string) preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text );
		return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Parties XML du classeur : chemin dans l'archive → contenu.
	 *
	 * @return array<string, string>
	 */
	private function parts(): array {
		$last = self::column( max( 0, $this->cols - 1 ) ) . max( 1, count( $this->rows ) );
		$cols = '';
		foreach ( $this->widths as $i => $w ) {
			$cols .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . (int) $w . '" customWidth="1"/>';
		}
		$head  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
		$sheet = self::xml( $this->sheet );
		return array(
			'[Content_Types].xml'        => $head . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
			'_rels/.rels'                => $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
			'xl/workbook.xml'            => $head . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $sheet . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $sheet . '\'!$A$1:$' . (string) preg_replace( '/(\d+)$/', '\$$1', $last ) . '</definedName></definedNames></workbook>',
			'xl/_rels/workbook.xml.rels' => $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
			'xl/styles.xml'              => $head . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF4"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
			'xl/worksheets/sheet1.xml'   => $head . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' . ( '' !== $cols ? '<cols>' . $cols . '</cols>' : '' ) . '<sheetData>' . implode( '', $this->rows ) . '</sheetData><autoFilter ref="A1:' . $last . '"/></worksheet>',
		);
	}

	/**
	 * Écrit le classeur dans un fichier temporaire.
	 *
	 * @return string Chemin du fichier, ou '' en cas d'échec.
	 */
	public function to_file(): string {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$path = wp_tempnam( 'famma-export.xlsx' );
		if ( '' === $path ) {
			return '';
		}
		// Données clients : le fichier temporaire disparaît en fin de requête, quoi qu'il arrive.
		register_shutdown_function( 'wp_delete_file', $path );
		$parts = $this->parts();
		if ( class_exists( \ZipArchive::class ) ) {
			$zip = new \ZipArchive();
			if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				wp_delete_file( $path );
				return '';
			}
			foreach ( $parts as $name => $content ) {
				$zip->addFromString( $name, $content );
			}
			if ( ! $zip->close() ) {
				wp_delete_file( $path );
				return '';
			}
			return $path;
		}
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		wp_delete_file( $path );
		$files = array();
		foreach ( $parts as $name => $content ) {
			$files[] = array(
				PCLZIP_ATT_FILE_NAME    => $name,
				PCLZIP_ATT_FILE_CONTENT => $content,
			);
		}
		$zip = new \PclZip( $path );
		if ( 0 === $zip->create( $files ) ) {
			wp_delete_file( $path );
			return '';
		}
		return $path;
	}

	/**
	 * Envoie le classeur en téléchargement puis arrête la requête.
	 *
	 * @param string $filename Nom proposé au téléchargement.
	 * @return void
	 */
	public function send( string $filename ): void {
		$path = $this->to_file();
		if ( '' === $path ) {
			wp_die( esc_html__( 'Le fichier Excel n’a pas pu être créé.', 'famma-core' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		ignore_user_abort( true );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a temp file we just wrote.
		wp_delete_file( $path );
		exit;
	}
}
