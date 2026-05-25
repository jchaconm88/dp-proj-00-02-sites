<?php
/**
 * Custom Blocks Registration
 *
 * Registers 5 server-side rendered blocks that enforce the design system
 * by using only brand palette and typography from theme.json.
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register custom blocks with render callbacks.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_register_custom_blocks() {

    // 1. Brand Card block.
    register_block_type(
        'mi-cliente-theme/brand-card',
        array(
            'api_version'     => 2,
            'attributes'      => array(
                'imageUrl' => array( 'type' => 'string', 'default' => '' ),
                'title'    => array( 'type' => 'string', 'default' => '' ),
                'text'     => array( 'type' => 'string', 'default' => '' ),
            ),
            'render_callback' => 'mi_cliente_theme_render_brand_card',
        )
    );

    // 2. Price Table block.
    register_block_type(
        'mi-cliente-theme/price-table',
        array(
            'api_version'     => 2,
            'attributes'      => array(
                'plans' => array(
                    'type'    => 'array',
                    'default' => array(),
                ),
            ),
            'render_callback' => 'mi_cliente_theme_render_price_table',
        )
    );

    // 3. Feature List block.
    register_block_type(
        'mi-cliente-theme/feature-list',
        array(
            'api_version'     => 2,
            'attributes'      => array(
                'features' => array(
                    'type'    => 'array',
                    'default' => array(),
                ),
            ),
            'render_callback' => 'mi_cliente_theme_render_feature_list',
        )
    );

    // 4. Banner block.
    register_block_type(
        'mi-cliente-theme/banner',
        array(
            'api_version'     => 2,
            'attributes'      => array(
                'heading'   => array( 'type' => 'string', 'default' => '' ),
                'text'      => array( 'type' => 'string', 'default' => '' ),
                'buttonText' => array( 'type' => 'string', 'default' => '' ),
                'buttonUrl'  => array( 'type' => 'string', 'default' => '#' ),
            ),
            'render_callback' => 'mi_cliente_theme_render_banner',
        )
    );

    // 5. Team Member block.
    register_block_type(
        'mi-cliente-theme/team-member',
        array(
            'api_version'     => 2,
            'attributes'      => array(
                'photoUrl' => array( 'type' => 'string', 'default' => '' ),
                'name'     => array( 'type' => 'string', 'default' => '' ),
                'role'     => array( 'type' => 'string', 'default' => '' ),
                'bio'      => array( 'type' => 'string', 'default' => '' ),
            ),
            'render_callback' => 'mi_cliente_theme_render_team_member',
        )
    );
}
add_action( 'init', 'mi_cliente_theme_register_custom_blocks' );


/**
 * Render callback: Brand Card.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function mi_cliente_theme_render_brand_card( $attributes ) {
    $image_url = esc_url( $attributes['imageUrl'] );
    $title     = esc_html( $attributes['title'] );
    $text      = esc_html( $attributes['text'] );

    ob_start();
    ?>
    <div class="mi-cliente-brand-card" style="
        background-color: var(--wp--preset--color--surface);
        border-radius: var(--wp--custom--border-radius--DEFAULT);
        overflow: hidden;
        font-family: var(--wp--preset--font-family--body);
    ">
        <?php if ( $image_url ) : ?>
            <img src="<?php echo $image_url; ?>" alt="<?php echo $title; ?>" style="width:100%;height:auto;display:block;" />
        <?php endif; ?>
        <div style="padding: var(--wp--preset--spacing--40);">
            <?php if ( $title ) : ?>
                <h3 style="
                    color: var(--wp--preset--color--primary);
                    font-family: var(--wp--preset--font-family--heading);
                    font-size: var(--wp--preset--font-size--large);
                    margin: 0 0 var(--wp--preset--spacing--20) 0;
                "><?php echo $title; ?></h3>
            <?php endif; ?>
            <?php if ( $text ) : ?>
                <p style="
                    color: var(--wp--preset--color--on-surface);
                    font-size: var(--wp--preset--font-size--medium);
                    margin: 0;
                "><?php echo $text; ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render callback: Price Table.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function mi_cliente_theme_render_price_table( $attributes ) {
    $plans = $attributes['plans'];

    ob_start();
    ?>
    <div class="mi-cliente-price-table" style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: var(--wp--preset--spacing--40);
        font-family: var(--wp--preset--font-family--body);
    ">
        <?php foreach ( $plans as $plan ) :
            $plan_name  = esc_html( $plan['name'] ?? '' );
            $plan_price = esc_html( $plan['price'] ?? '' );
            $plan_features = $plan['features'] ?? array();
        ?>
            <div style="
                background-color: var(--wp--preset--color--surface);
                border-radius: var(--wp--custom--border-radius--DEFAULT);
                padding: var(--wp--preset--spacing--40);
                text-align: center;
            ">
                <h3 style="
                    color: var(--wp--preset--color--primary);
                    font-family: var(--wp--preset--font-family--heading);
                    font-size: var(--wp--preset--font-size--large);
                    margin: 0 0 var(--wp--preset--spacing--20) 0;
                "><?php echo $plan_name; ?></h3>
                <p style="
                    color: var(--wp--preset--color--accent);
                    font-size: var(--wp--preset--font-size--xx-large);
                    font-weight: bold;
                    margin: 0 0 var(--wp--preset--spacing--30) 0;
                "><?php echo $plan_price; ?></p>
                <?php if ( ! empty( $plan_features ) ) : ?>
                    <ul style="
                        list-style: none;
                        padding: 0;
                        margin: 0 0 var(--wp--preset--spacing--30) 0;
                        color: var(--wp--preset--color--on-surface);
                        font-size: var(--wp--preset--font-size--small);
                    ">
                        <?php foreach ( $plan_features as $feature ) : ?>
                            <li style="padding: var(--wp--preset--spacing--10) 0;">✓ <?php echo esc_html( $feature ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render callback: Feature List.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function mi_cliente_theme_render_feature_list( $attributes ) {
    $features = $attributes['features'];

    ob_start();
    ?>
    <div class="mi-cliente-feature-list" style="
        font-family: var(--wp--preset--font-family--body);
        padding: var(--wp--preset--spacing--40) 0;
    ">
        <?php foreach ( $features as $feature ) :
            $icon  = esc_html( $feature['icon'] ?? '✓' );
            $label = esc_html( $feature['label'] ?? '' );
            $desc  = esc_html( $feature['description'] ?? '' );
        ?>
            <div style="
                display: flex;
                align-items: flex-start;
                gap: var(--wp--preset--spacing--30);
                margin-bottom: var(--wp--preset--spacing--30);
            ">
                <span style="
                    font-size: var(--wp--preset--font-size--x-large);
                    color: var(--wp--preset--color--accent);
                    flex-shrink: 0;
                "><?php echo $icon; ?></span>
                <div>
                    <strong style="
                        color: var(--wp--preset--color--primary);
                        font-size: var(--wp--preset--font-size--medium);
                    "><?php echo $label; ?></strong>
                    <?php if ( $desc ) : ?>
                        <p style="
                            color: var(--wp--preset--color--on-surface);
                            font-size: var(--wp--preset--font-size--small);
                            margin: var(--wp--preset--spacing--10) 0 0 0;
                        "><?php echo $desc; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render callback: Banner.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function mi_cliente_theme_render_banner( $attributes ) {
    $heading     = esc_html( $attributes['heading'] );
    $text        = esc_html( $attributes['text'] );
    $button_text = esc_html( $attributes['buttonText'] );
    $button_url  = esc_url( $attributes['buttonUrl'] );

    ob_start();
    ?>
    <div class="mi-cliente-banner" style="
        background-color: var(--wp--preset--color--primary);
        padding: var(--wp--preset--spacing--60) var(--wp--preset--spacing--40);
        text-align: center;
        border-radius: var(--wp--custom--border-radius--lg);
        font-family: var(--wp--preset--font-family--body);
        width: 100%;
    ">
        <?php if ( $heading ) : ?>
            <h2 style="
                color: var(--wp--preset--color--on-primary);
                font-family: var(--wp--preset--font-family--heading);
                font-size: var(--wp--preset--font-size--xx-large);
                margin: 0 0 var(--wp--preset--spacing--20) 0;
            "><?php echo $heading; ?></h2>
        <?php endif; ?>
        <?php if ( $text ) : ?>
            <p style="
                color: var(--wp--preset--color--on-primary);
                font-size: var(--wp--preset--font-size--large);
                margin: 0 0 var(--wp--preset--spacing--40) 0;
            "><?php echo $text; ?></p>
        <?php endif; ?>
        <?php if ( $button_text ) : ?>
            <a href="<?php echo $button_url; ?>" style="
                display: inline-block;
                background-color: var(--wp--preset--color--accent);
                color: var(--wp--preset--color--background);
                padding: var(--wp--preset--spacing--20) var(--wp--preset--spacing--40);
                border-radius: var(--wp--custom--border-radius--DEFAULT);
                text-decoration: none;
                font-size: var(--wp--preset--font-size--medium);
                font-weight: bold;
            "><?php echo $button_text; ?></a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render callback: Team Member.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function mi_cliente_theme_render_team_member( $attributes ) {
    $photo_url = esc_url( $attributes['photoUrl'] );
    $name      = esc_html( $attributes['name'] );
    $role      = esc_html( $attributes['role'] );
    $bio       = esc_html( $attributes['bio'] );

    ob_start();
    ?>
    <div class="mi-cliente-team-member" style="
        background-color: var(--wp--preset--color--surface);
        border-radius: var(--wp--custom--border-radius--DEFAULT);
        padding: var(--wp--preset--spacing--40);
        text-align: center;
        font-family: var(--wp--preset--font-family--body);
    ">
        <?php if ( $photo_url ) : ?>
            <img src="<?php echo $photo_url; ?>" alt="<?php echo $name; ?>" style="
                width: 120px;
                height: 120px;
                border-radius: var(--wp--custom--border-radius--full);
                object-fit: cover;
                margin-bottom: var(--wp--preset--spacing--30);
            " />
        <?php endif; ?>
        <?php if ( $name ) : ?>
            <h3 style="
                color: var(--wp--preset--color--primary);
                font-family: var(--wp--preset--font-family--heading);
                font-size: var(--wp--preset--font-size--large);
                margin: 0 0 var(--wp--preset--spacing--10) 0;
            "><?php echo $name; ?></h3>
        <?php endif; ?>
        <?php if ( $role ) : ?>
            <p style="
                color: var(--wp--preset--color--accent);
                font-size: var(--wp--preset--font-size--small);
                font-weight: bold;
                margin: 0 0 var(--wp--preset--spacing--20) 0;
            "><?php echo $role; ?></p>
        <?php endif; ?>
        <?php if ( $bio ) : ?>
            <p style="
                color: var(--wp--preset--color--on-surface);
                font-size: var(--wp--preset--font-size--small);
                margin: 0;
            "><?php echo $bio; ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
