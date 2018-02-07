<?php

class Orbis_Neighborhood_Plugin extends Orbis_Plugin {
	/**
	 * Post type
	 */
	const POST_TYPE = 'orbis_house';

	public function __construct( $file ) {
		parent::__construct( $file );

		$this->set_name( 'orbis_neighborhood' );
		$this->set_db_version( '1.1.0' );

		// Load text domain
		$this->load_textdomain( 'orbis-neighborhood', '/languages/' );

		// Actions
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'p2p_init', array( $this, 'p2p_init' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_post_house' ) );

		// @see https://github.com/WordPress/WordPress/blob/4.4.1/wp-admin/edit-tag-form.php#L200-L211
		add_action( 'house_contribution_edit_form_fields', array( $this, 'house_contribution_edit_form_fields' ) );
		// @see http://sabramedia.com/blog/how-to-add-custom-fields-to-custom-taxonomies
		// @see https://github.com/WordPress/WordPress/blob/4.4.1/wp-includes/taxonomy.php#L3329-L3340
		add_action( 'edited_house_contribution', array( $this, 'edited_house_contribution' ) );

		// Filters
		add_filter( 'manage_edit-' . self::POST_TYPE . '_columns', array( $this, 'edit_columns' ) );

		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );
	}

	public function house_contribution_edit_form_fields( $term ) {
		$price = get_term_meta( absint( $term->term_id ), 'price', true );
?>
		<tr class="form-field term-price-wrap">
			<th scope="row"><label for="price"><?php esc_html_e( 'Price', 'orbis-neighborhood' ); ?></label></th>
			<td><input name="price" id="price" type="text" value="<?php echo esc_attr( $price ); ?>" size="40" />
			</td>

		</tr>
<?php
	}

	public function edited_house_contribution( $term_id ) {
		if ( filter_has_var( INPUT_POST, 'price' ) ) {
			$price = filter_input( INPUT_POST, 'price', FILTER_VALIDATE_FLOAT );

			update_term_meta( $term_id, 'price', $price );
		}
	}

	//////////////////////////////////////////////////

	/**
	 * Edit columns
	 */
	public function edit_columns( $columns ) {
		$columns['house_is_member'] = __( 'Is Member', 'orbis-neighborhood' );

		$columns_new = array();

		foreach ( $columns as $name => $label ) {
			$columns_new[ $name ] = $label;

			if ( 'title' === $name ) {
				$columns_new['house_is_member'] = $columns['house_is_member'];
			}
		}

		return $columns_new;
	}

	/**
	 * Custom columns
	 */
	public function custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'house_is_member':
				$is_member = get_post_meta( $post_id, '_house_is_member', true );

				printf(
					'<span class="dashicons dashicons-%s"></span>',
					$is_member ? 'yes' : 'no'
				);

				break;
		}
	}

	public function init() {
		register_post_type( 'orbis_house', array(
			'label'       => __( 'Houses', 'orbis-neighborhood' ),
			'public'      => true,
			'menu_icon'   => 'dashicons-admin-home',
			'supports'    => array( 'title', 'editor', 'author', 'thumbnail', 'custom-fields', 'comments', 'revisions' ),
			'has_archive' => true,
			'rewrite'     => array( 'slug' => 'woningen' ),
		) );

		register_taxonomy(
			'orbis_house_group',
			'orbis_house',
			array(
				'label'        => __( 'Group', 'orbis-neighborhood' ),
				'rewrite'      => array( 'slug' => 'house-groups' ),
				'hierarchical' => true,
				'meta_box_cb'  => false,
				'show_admin_column' => true,
			)
		);

		register_taxonomy(
			'orbis_house_payment_method',
			'orbis_house',
			array(
				'label'        => __( 'Payment Method', 'orbis-neighborhood' ),
				'rewrite'      => array( 'slug' => 'payment-methods' ),
				'hierarchical' => true,
				'meta_box_cb'  => false,
				'show_admin_column' => true,
			)
		);

		register_taxonomy(
			'orbis_house_contribution',
			'orbis_house',
			array(
				'label'        => __( 'Contribution', 'orbis-neighborhood' ),
				'rewrite'      => array( 'slug' => 'contributions' ),
				'hierarchical' => true,
				'meta_box_cb'  => false,
				'show_admin_column' => true,
			)
		);
	}

	public function p2p_init() {
		p2p_register_connection_type( array(
			'name'         => 'orbis_persons_to_houses',
			'from'         => 'orbis_person',
			'to'           => 'orbis_house',
			//'cardinality'  => 'many-to-one',
			'admin_column' => 'any',
			'fields'       => array(
				'current' => array(
					'title' => __( 'Current', 'orbis-neighborhood' ),
					'type'  => 'checkbox',
				),
			),
		) );
	}

	public function add_meta_boxes( $post_type ) {
		if ( 'orbis_house' === $post_type ) {
			add_meta_box(
				'house-details',
				__( 'House Details', 'orbis-neighborhood' ),
				array( $this, 'meta_box_house_details' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	private function print_fields( $fields, $post ) {
		?>
		<table class="form-table">
			<tbody>
				<?php foreach ( $fields as $meta_key => $label ) : ?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $meta_key ); ?>">
								<?php

								if ( is_array( $label ) ) {
									echo esc_html( $label['label'] );
								} else {
									echo esc_html( $label );
								}

								?>
							</label>
						</th>
						<td>
							<?php

							$field = $label;

							if ( is_array( $field ) && 'wp_dropdown_categories' === $field['type'] ) {
								$taxonomy = $field['taxonomy'];
								$name     = sprintf( 'tax_input[%s][]', $taxonomy );
								$ids      = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
								$selected = array_shift( $ids );

								$defaults = array(
									'name'       => $name,
									'id'         => $field['id'],
									'taxonomy'   => $taxonomy,
									'selected'   => $selected,
									'hide_empty' => false,
								);

								$args = wp_parse_args( $field['args'], $defaults );

								wp_dropdown_categories( $args );
							} elseif ( is_array( $field ) && 'checkbox' === $field['type'] ) {
								?>
								<?php $field['value'] = get_post_meta( $post->ID, $meta_key, true ); ?>
								<fieldset>
									<legend class="screen-reader-text"><span><?php echo esc_html( $field['label'] ); ?></span></legend><label for="<?php echo esc_attr( $field['meta_key'] ); ?>">
									<input name="<?php echo esc_attr( $field['meta_key'] ); ?>" type="checkbox" id="<?php echo esc_attr( $field['meta_key'] ); ?>" value="1" <?php checked( $field['value'], 1 ); ?> /> <?php echo esc_html( $field['description'] ); ?></label>
								</fieldset>
								<?php
							} else {
								?>
								<?php $value = get_post_meta( $post->ID, $meta_key, true ); ?>
								<input type="text" id="<?php echo esc_attr( $meta_key ); ?>" name="<?php echo esc_attr( $meta_key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
								<?php
							}

							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function save_fields( $fields, $post_id ) {
		foreach ( $fields as $meta_key => $label ) {
			$value = filter_input( INPUT_POST, $meta_key, FILTER_SANITIZE_STRING );

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	public function get_house_details_fields() {
		return array(
			'_house_address' => __( 'Address', 'orbis-neighborhood' ),
			'_house_number'  => __( 'Number', 'orbis-neighborhood' ),
			'_house_is_member' => array(
				'id'          => '_house_is_member',
				'label'       => __( 'Member', 'orbis-neighborhood' ),
				'type'        => 'checkbox',
				'description' => __( 'House is member.', 'orbis-neighborhood' ),
				'meta_key'    => '_house_is_member',
			),
			'house_group'    => array(
				'id'       => 'house_group',
				'label'    => __( 'Group', 'orbis-neighborhood' ),
				'type'     => 'wp_dropdown_categories',
				'taxonomy' => 'orbis_house_group',
				'args'     => array(
					'show_option_none' => __( '- Select Group -', 'orbis-neighborhood' ),
				),
			),
			'house_payment_method'    => array(
				'id'       => 'house_payment_method',
				'label'    => __( 'Payment Method', 'orbis-neighborhood' ),
				'type'     => 'wp_dropdown_categories',
				'taxonomy' => 'orbis_house_payment_method',
				'args'     => array(
					'show_option_none' => __( '- Select Payment Method -', 'orbis-neighborhood' ),
				),
			),
			'house_contribution'    => array(
				'id'       => 'house_contribution',
				'label'    => __( 'Contribution', 'orbis-neighborhood' ),
				'type'     => 'wp_dropdown_categories',
				'taxonomy' => 'orbis_house_contribution',
				'args'     => array(
					'show_option_none' => __( '- Select Contribution -', 'orbis-neighborhood' ),
				),
			),
		);
	}

	public function meta_box_house_details( $post ) {
		wp_nonce_field( 'save_house_details', 'save_house_details_nonce' );

		$fields = $this->get_house_details_fields();

		$this->print_fields( $fields, $post );
	}

	public function save_post_house( $post_id ) {
		if ( ! filter_has_var( INPUT_POST, 'save_house_details_nonce' ) ) {
			return $post_id;
		}

		check_admin_referer( 'save_house_details', 'save_house_details_nonce' );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		$fields = $this->get_house_details_fields();

		$this->save_fields( $fields, $post_id );
	}
}
