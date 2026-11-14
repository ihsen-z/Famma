<?php
/**
 * Écran FAMMA → Nouveau look : une brique et ses textes.
 *
 * Les textes gardent leurs options et leurs noms de champ d'origine
 * (`famma_page_account[crumb_fr]`…), lus et assainis par `Pages_Content`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu d'une brique et de ses champs de texte.
 */
final class New_Look_Admin_Fields {

	/**
	 * Affiche une brique : interrupteur, état, explication, besoins, textes.
	 *
	 * @param string                              $key    Clé de la brique.
	 * @param array<string, mixed>                $brick  Brique normalisée.
	 * @param string                              $state  État réel.
	 * @param array<string, array<string, mixed>> $bricks Toutes les briques.
	 * @return void
	 */
	public static function brick( string $key, array $brick, string $state, array $bricks ): void {
		$page   = New_Look_Registry::text_page( (string) $brick['page'] );
		$fields = self::fields( $page, New_Look_Registry::text_keys( $brick ) );
		$parts  = New_Look::settings()['parts'];
		$labels = New_Look_Admin_State::labels();
		$switch = 'fnl-sw-' . $key;

		$wait = array();
		foreach ( (array) $brick['wait'] as $text ) {
			$wait[] = self::field_id( $page, (string) $text, 'fr' );
		}

		$search = array( $brick['title'], $brick['summary'], $brick['help'], $key );
		foreach ( $fields as $field ) {
			$search[] = (string) $field['label'];
		}
		?>
		<article class="fnl-brick" id="<?php echo esc_attr( 'fnl-b-' . $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>" data-needs="<?php echo esc_attr( implode( ' ', (array) $brick['needs'] ) ); ?>" data-wait="<?php echo esc_attr( implode( ' ', $wait ) ); ?>" data-search="<?php echo esc_attr( self::lower( implode( ' ', $search ) ) ); ?>">
			<input type="checkbox" role="switch" class="fnl-switch" id="<?php echo esc_attr( $switch ); ?>" name="<?php echo esc_attr( New_Look::OPTION . '[parts][' . $key . ']' ); ?>" value="1" aria-describedby="<?php echo esc_attr( 'fnl-s-' . $key ); ?>" <?php checked( ! empty( $parts[ $key ] ) ); ?> />
			<div class="fnl-brick__body">
				<div class="fnl-brick__head">
					<label for="<?php echo esc_attr( $switch ); ?>"><strong><?php echo esc_html( (string) $brick['title'] ); ?></strong></label>
					<span class="fnl-pill <?php echo esc_attr( 'fnl-pill--' . $state ); ?>" data-pill><?php echo esc_html( $labels[ $state ] ?? '' ); ?></span>
					<code class="fnl-brick__key"><?php echo esc_html( $key ); ?></code>
				</div>
				<p class="fnl-brick__summary" id="<?php echo esc_attr( 'fnl-s-' . $key ); ?>"><?php echo esc_html( (string) $brick['summary'] ); ?></p>
				<?php self::needs( $brick, $parts, $bricks ); ?>
				<?php if ( '' !== (string) $brick['help'] ) : ?>
					<details class="fnl-more">
						<summary><?php esc_html_e( 'En savoir plus', 'famma-core' ); ?></summary>
						<p><?php echo esc_html( (string) $brick['help'] ); ?></p>
					</details>
				<?php endif; ?>
				<?php if ( ! empty( $fields ) ) : ?>
					<details class="fnl-texts">
						<summary>
							<?php
							/* translators: %d: nombre de textes. */
							echo esc_html( sprintf( __( 'Textes de ce bloc (%d)', 'famma-core' ), count( $fields ) ) );
							?>
						</summary>
						<div class="fnl-texts__rows">
							<?php self::rows( $page, $fields ); ?>
						</div>
					</details>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	/**
	 * Textes de la page qu'aucune brique n'affiche : ils servent toujours.
	 *
	 * @param string             $page Clé de page du registre.
	 * @param array<int, string> $used Clés déjà rangées sous une brique.
	 * @return void
	 */
	public static function other( string $page, array $used ): void {
		$page = New_Look_Registry::text_page( $page );
		if ( '' === $page ) {
			return;
		}

		$fields = array_values(
			array_filter(
				Pages_Content::schema( $page ),
				static function ( array $field ) use ( $used ): bool {
					return ! in_array( (string) $field['key'], $used, true );
				}
			)
		);

		if ( empty( $fields ) ) {
			return;
		}

		$search = array();
		foreach ( $fields as $field ) {
			$search[] = (string) $field['label'];
		}
		?>
		<details class="fnl-other" data-search="<?php echo esc_attr( self::lower( implode( ' ', $search ) ) ); ?>">
			<summary>
				<?php
				/* translators: %d: nombre de textes. */
				echo esc_html( sprintf( __( 'Autres textes de la page (%d)', 'famma-core' ), count( $fields ) ) );
				?>
			</summary>
			<p class="description"><?php esc_html_e( 'Ils s’affichent toujours, que le nouveau look soit actif ou non. Un champ vidé masque son bloc au lieu d’afficher un trou.', 'famma-core' ); ?></p>
			<div class="fnl-texts__rows">
				<?php self::rows( $page, $fields ); ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Ligne « A besoin de… » avec un lien vers chaque brique requise.
	 *
	 * @param array<string, mixed>                $brick  Brique normalisée.
	 * @param array<string, bool>                 $parts  Cases enregistrées.
	 * @param array<string, array<string, mixed>> $bricks Toutes les briques.
	 * @return void
	 */
	private static function needs( array $brick, array $parts, array $bricks ): void {
		$needs = array_values( array_intersect( (array) $brick['needs'], array_keys( $bricks ) ) );
		if ( empty( $needs ) ) {
			return;
		}

		$missing = false;
		foreach ( $needs as $need ) {
			$missing = $missing || empty( $parts[ $need ] );
		}

		// Quand il manque une case, seules les cases décochées sont nommées.
		$links = array();
		foreach ( $needs as $need ) {
			$links[] = sprintf(
				'<a href="%1$s" data-jump="%2$s"%3$s>%4$s</a>',
				esc_url( '#fnl-b-' . $need ),
				esc_attr( $need ),
				$missing && ! empty( $parts[ $need ] ) ? ' hidden' : '',
				esc_html( (string) $bricks[ $need ]['title'] )
			);
		}
		?>
		<p class="fnl-brick__needs<?php echo $missing ? ' is-missing' : ''; ?>" data-needs-line>
			<span class="fnl-needs__ok" <?php echo $missing ? 'hidden' : ''; ?>><?php esc_html_e( 'A besoin de :', 'famma-core' ); ?></span>
			<span class="fnl-needs__ko" <?php echo $missing ? '' : 'hidden'; ?>><?php esc_html_e( 'Sans effet, à cocher d’abord :', 'famma-core' ); ?></span>
			<?php
			echo wp_kses(
				implode( ' ', $links ),
				array(
					'a' => array(
						'href'      => true,
						'data-jump' => true,
						'hidden'    => true,
					),
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Champs de schéma d'une page, dans l'ordre du schéma.
	 *
	 * @param string             $page Page de textes (`Pages_Content`).
	 * @param array<int, string> $keys Clés retenues.
	 * @return array<int, array<string, string>>
	 */
	private static function fields( string $page, array $keys ): array {
		if ( '' === $page || empty( $keys ) ) {
			return array();
		}

		return array_values(
			array_filter(
				Pages_Content::schema( $page ),
				static function ( array $field ) use ( $keys ): bool {
					return in_array( (string) $field['key'], $keys, true );
				}
			)
		);
	}

	/**
	 * Champs français et derja, côte à côte.
	 *
	 * @param string                            $page   Page de textes.
	 * @param array<int, array<string, string>> $fields Champs du schéma.
	 * @return void
	 */
	private static function rows( string $page, array $fields ): void {
		$option = Pages_Content::option_for( $page );
		$stored = get_option( $option, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$langs  = array(
			'fr' => __( 'Français', 'famma-core' ),
			'ar' => __( 'Derja', 'famma-core' ),
		);

		foreach ( $fields as $field ) {
			$key = (string) $field['key'];
			?>
			<div class="fnl-text" data-search="<?php echo esc_attr( self::lower( (string) $field['label'] ) ); ?>">
				<p class="fnl-text__label"><?php echo esc_html( (string) $field['label'] ); ?></p>
				<div class="fnl-text__langs">
					<?php
					foreach ( $langs as $lang => $label ) {
						$id    = self::field_id( $page, $key, $lang );
						$name  = $option . '[' . $key . '_' . $lang . ']';
						$value = array_key_exists( $key . '_' . $lang, $stored )
							? (string) $stored[ $key . '_' . $lang ]
							: (string) ( $field[ 'default_' . $lang ] ?? '' );
						$dir   = 'ar' === $lang ? 'rtl' : 'ltr';
						?>
						<p class="fnl-text__lang">
							<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
							<?php if ( 'textarea' === ( $field['type'] ?? 'text' ) ) : ?>
								<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3" class="large-text" dir="<?php echo esc_attr( $dir ); ?>" lang="<?php echo esc_attr( $lang ); ?>" data-text><?php echo esc_textarea( $value ); ?></textarea>
							<?php else : ?>
								<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="large-text" dir="<?php echo esc_attr( $dir ); ?>" lang="<?php echo esc_attr( $lang ); ?>" data-text />
							<?php endif; ?>
						</p>
						<?php
					}
					?>
				</div>
				<?php if ( ! empty( $field['help'] ) ) : ?>
					<p class="description"><?php echo esc_html( (string) $field['help'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Identifiant HTML d'un champ de texte.
	 *
	 * @param string $page Page de textes.
	 * @param string $key  Clé du champ.
	 * @param string $lang `fr` ou `ar`.
	 * @return string
	 */
	private static function field_id( string $page, string $key, string $lang ): string {
		return 'fnl-x-' . $page . '-' . $key . '-' . $lang;
	}

	/**
	 * Minuscules, pour la recherche (accents gardés).
	 *
	 * @param string $text Texte.
	 * @return string
	 */
	private static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}
}
