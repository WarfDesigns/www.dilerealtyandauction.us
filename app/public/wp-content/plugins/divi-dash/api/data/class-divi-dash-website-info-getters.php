<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Website_Info_Getter
{
    public static function et_get_wordpress_update_version()
    {
        require_once ABSPATH . 'wp-admin/includes/update.php';

        $core_updates = get_core_updates();
        return $core_updates !== null && isset( $core_updates[0] ) && isset( $core_updates[0]->version ) ? $core_updates[0]->version : '';
    }

    public static function et_get_website_update_status()
    {
        require_once ABSPATH . 'wp-admin/includes/update.php';

        $core_updates = get_core_updates();
        return $core_updates !== null && isset( $core_updates[0] ) && isset( $core_updates[0]->response ) ? $core_updates[0]->response : '';
    }

    public static function et_get_theme_info( $theme, $info )
    {
        switch ( $info ) {
            case 'name':
                return null !== $theme->Name ? $theme->Name : '';
                break;
            case 'version':
                return null !== $theme->Version ? $theme->Version : '';
                break;
            case 'description':
                return false !== $theme->get( 'Description' ) ? $theme->get( 'Description' ) : '';
                break;
            case 'author':
                return false !== $theme->get( 'Author' ) ? $theme->get( 'Author' ) : '';
                break;
            case 'author_uri':
                return false !== $theme->get( 'AuthorURI' ) ? $theme->get( 'AuthorURI' ) : '';
                break;
            case 'theme_uri':
                return false !== $theme->get( 'ThemeURI' ) ? $theme->get( 'ThemeURI' ) : '';
                break;
            default:
                return '';
        }
    }

    public static function et_is_theme_allowed_on_multisite( $theme )
    {
        return null !== $theme->is_allowed() ? $theme->is_allowed() : 0;
    }

    public static function et_is_theme_allowed_on_the_network( $theme_slug )
    {
        return in_array( $theme_slug, array_keys( get_site_option( 'allowedthemes' ) ) );
    }

    public static function et_is_theme_active( $theme_slug )
    {
        $current_slug = null !== wp_get_theme() && null !== wp_get_theme()->get_stylesheet() ? wp_get_theme()->get_stylesheet() : '';

        return $current_slug === $theme_slug;
    }

    public static function et_is_divi( $theme_slug )
    {
        return $theme_slug == 'Divi' || $theme_slug == 'Extra';
    }

    /**
     * Returns parent theme if it's a child theme or returns if parent then returns itself
     *
     * @param $theme
     * @return string
     */
    public static function et_get_parent_theme_template( $theme )
    {
        return null !== $theme->get_template() ? $theme->Template : '';
    }

    public static function et_get_theme_slug( $theme )
    {
        return null !== $theme->get_stylesheet() ? $theme->get_stylesheet() : '';
    }

    public static function et_is_child_theme( $theme )
    {
        return self::et_get_parent_theme_template( $theme ) !== self::et_get_theme_slug($theme) ? true : false;
    }

    public static function et_get_theme_update_version( $theme )
    {
        return isset( $theme->update['new_version'] ) ? $theme->update['new_version'] : '';
    }

    public static function et_get_plugin_info( $plugin, $info )
    {
        switch ( $info ) {
            case 'name':
                return isset( $plugin['Name'] ) ? $plugin['Name'] : '';
                break;
            case 'version':
                return isset( $plugin['Version'] ) ? $plugin['Version'] : '';
                break;
            case 'description':
                return isset( $plugin['Description'] ) ? $plugin['Description'] : '';
                break;
            case 'author':
                return isset( $plugin['Author'] ) ? $plugin['Author'] : '';
                break;
            case 'author_uri':
                return isset( $plugin['AuthorURI'] ) ? $plugin['AuthorURI'] : '';
                break;
            case 'plugin_uri':
                return isset( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '';
                break;
            default:
                return '';
        }
    }

    public static function et_get_plugin_update_version( $plugin )
    {
        return isset( $plugin->update->new_version ) ? $plugin->update->new_version : '';
    }

    public static function et_get_active_plugins()
    {
        return null !== get_option( 'active_plugins' ) ? get_option( 'active_plugins' ) : [];
    }

    public static function et_get_subsite_domain( $subsite )
    {
        return isset( $subsite->domain ) ? $subsite->domain : null;
    }

    public static function et_is_plugin_active( $slug )
    {
        return is_plugin_active( $slug );
    }

    public static function et_get_formatted_server_key_info( $label, $value, $recommended_value, $type, $dot_color ): array
    {
        return array (
            'label' => $label,
            'value' => $value,
            'recommended' => $recommended_value,
            'type' => $type,
            'dot' => $dot_color,
        );
    }

    public static function et_get_common_server_data(): array
    {
        global $wpdb;
    
        return array(
            'is_wp_writable' => array(
                'label' => 'Writable wp-content Directory',
                'value' => wp_is_writable( WP_CONTENT_DIR ) ? 'Yes' : 'No',
                'recommended' => 'Yes',
                'type' => 'truthy_falsy',
            ),
            'is_wp_includes_writable' => array(
                'label' => 'Writable wp-includes Directory',
                'value' => wp_is_writable(ABSPATH . WPINC) ? 'Yes' : 'No',
                'recommended' => 'Yes',
                'type' => 'truthy_falsy',
            ),
            'is_wp_admin_writable' => array(
                'label' => 'Writable wp-admin Directory',
                'value' => wp_is_writable(ABSPATH . 'wp-admin') ? 'Yes' : 'No',
                'recommended' => 'Yes',
                'type' => 'truthy_falsy',
            ),
            'is_cache_writable' => array(
                'label' => 'Writable et-cache Directory',
                'value' => wp_is_writable( WP_CONTENT_DIR . '/et-cache' ) ? 'Yes' : 'No',
                'recommended' => 'Yes',
                'type' => 'truthy_falsy',
            ),
            'php_version' => array(
                'label' => 'PHP Version',
                'value' => ( float ) phpversion(),
                'recommended' => '7.4 or higher',
                'type' => 'version',
            ),
            'wp_version' => array(
                'label' => 'WordPress Version',
                'value' => get_bloginfo( 'version' ),
                'recommended' => '5.3 or higher',
                'type' => 'version',
            ),
            'mysql_version' => array(
                'label' => 'MySQL Version',
                'value' => $wpdb->db_version(),
                'recommended' => '5.7 or higher',
                'type' => 'version',
            ),
            'php_memory_limit' => array(
                'label' => 'PHP Memory Limit',
                'value' => ini_get( 'memory_limit' ),
                'recommended' => '128M',
                'type' => 'size',
            ),
            'post_max_size' => array(
                'label' => 'Max Post Size',
                'value' => ini_get( 'post_max_size' ),
                'recommended' => '64M',
                'type' => 'size',
            ),
            'max_execution_time' => array(
                'label' => 'Max Execution Time',
                'value' => ini_get( 'max_execution_time' ),
                'recommended' => '120',
                'type' => 'seconds',
            ),
            'upload_max_filesize' => array(
                'label' => 'Max Upload File Size',
                'value' => ini_get( 'upload_max_filesize' ),
                'recommended' => '64M',
                'type' => 'size',
            ),
            'max_input_time' => array(
                'label' => 'Max Input Time',
                'value' => ini_get( 'max_input_time' ),
                'recommended' => '60',
                'type' => 'seconds',
            ),
            'max_input_vars' => array(
                'label' => 'Max Input Vars',
                'value' => ini_get( 'max_input_vars' ),
                'recommended' => '1000',
                'type' => 'size',
            ),
            'display_errors' => array(
                'label' => 'Display Errors',
                'value' => ini_get( 'display_errors' ) ? 'Yes' : 'No',
                'recommended' => 'No',
                'type' => 'truthy_falsy',
            )
        );
    }

    public static function et_make_non_divi_server_data(): array
    {
        $server_data = self::et_get_common_server_data();

        unset( $server_data['is_cache_writable'] );

        foreach ( $server_data as $key => $data ) {
            $dot_color = self::et_get_dot_color_of_server_data_values( $data );
            $server_data[$key] = self::et_get_formatted_server_key_info( $data['label'], $data['value'], $data['recommended'], $data['type'], $dot_color );
        }

        return $server_data;
    }

    public static function et_make_divi_server_data(): array
    {
        global $shortname;

        $divi_builder_plugin_active = et_is_builder_plugin_active();
        $options = $divi_builder_plugin_active ? get_option( 'et_pb_builder_options', array() ) : array();

        $divi_server_data = [
            'dynamic_css' => array(
                'label' => 'Dynamic CSS',
                'value' => $divi_builder_plugin_active ? ( isset( $options['performance_main_dynamic_css'] ) ? $options['performance_main_dynamic_css'] : 'on' ) : et_get_option( $shortname . '_dynamic_css', 'on' ),
                'recommended' => 'enabled',
                'type' => 'truthy_falsy',
            ),
            'dynamic_framework' => array(
                'label' => 'Dynamic Framework',
                'value' => $divi_builder_plugin_active ? ( isset( $options['performance_main_dynamic_module_framework'] ) ? $options['performance_main_dynamic_module_framework'] : 'on' ) : et_get_option( $shortname . '_dynamic_module_framework', 'on' ),
                'recommended' => 'enabled',
                'type' => 'truthy_falsy',
            ),
            'dynamic_javascript' => array(
                'label' => 'Dynamic JavaScript',
                'value' => $divi_builder_plugin_active ? ( isset( $options['performance_main_dynamic_js_libraries'] ) ? $options['performance_main_dynamic_js_libraries'] : 'on' ) : et_get_option( $shortname . '_dynamic_js_libraries', 'on' ),
                'recommended' => 'enabled',
                'type' => 'truthy_falsy',
            ),
            'critical_css' => array(
                'label' => 'Critical CSS',
                'value' => $divi_builder_plugin_active ? ( isset( $options['performance_main_critical_css'] ) ? $options['performance_main_critical_css'] : 'on' ) : et_get_option( $shortname . '_critical_css', 'on' ),
                'recommended' => 'enabled',
                'type' => 'truthy_falsy',
            ),
            'static_css' => array(
                'label' => 'Static CSS',
                'value' => et_get_option( 'et_pb_static_css_file', 'on' ),
                'recommended' => 'enabled',
                'type' => 'truthy_falsy',
            )
        ];

        foreach ($divi_server_data as $key => $data) {
            $divi_server_data[$key]['value'] = $data['value'] === 'on' ? 'enabled' : 'disabled';
        }

        $server_data = array_merge( self::et_get_common_server_data(), $divi_server_data );

        foreach ( $server_data as $key => $data ) {
            $dot_color = self::et_get_dot_color_of_server_data_values( $data );
            $server_data[$key] = self::et_get_formatted_server_key_info( $data['label'], $data['value'], $data['recommended'], $data['type'], $dot_color );
        }

        return $server_data;
    }

    public static function et_get_dot_color_of_server_data_values( $data )
    {
        require_once ET_DIVI_DASH_PLUGIN_DIR . 'api/class-divi-dash-kernel.php';

        switch ( $data['type'] ) {
            case 'truthy_falsy':
                return self::et_get_dot_color_of_truthy_falsy_values( $data['value'], $data['recommended'] );

            case 'version':
                $recommended = trim( str_replace( 'or higher', '', $data['recommended'] ) );
                return self::et_get_dot_color_by_version_comparison( $data['value'], $recommended );

            case 'size':
            case 'seconds':
                return self::et_get_dot_color_of_comparison_values( $data['value'], $data['recommended'] );
        }

        return Divi_Dash_Kernel::DOT_COLOR['red'];
    }

    public static function et_get_dot_color_of_truthy_falsy_values( $current, $recommended )
    {
        if ( $current == $recommended ) {
            return Divi_Dash_Kernel::DOT_COLOR['green'];
        }

        return Divi_Dash_Kernel::DOT_COLOR['red'];
    }

    public static function et_get_dot_color_by_version_comparison( $current, $recommended )
    {
        if ( version_compare($current, $recommended, '>=') ) {
            return Divi_Dash_Kernel::DOT_COLOR['green'];
        }

        return Divi_Dash_Kernel::DOT_COLOR['red'];
    }

    public static function et_get_dot_color_of_comparison_values( $current, $recommended )
    {
        $current = ( float ) str_replace( 'M', '', $current );
        $recommended = ( float ) str_replace( 'M', '', $recommended );

        if ( $current >= $recommended || (int) $current === -1 ) {
            return Divi_Dash_Kernel::DOT_COLOR['green'];
        }

        return Divi_Dash_Kernel::DOT_COLOR['red'];
    }

    public static function et_get_subsite_urls(): array
    {
        if ( ! is_multisite() ) {
            return [];
        }

        $subsites_urls = [];
        $sites = get_sites();

        if ( empty( $sites ) ) {
            return [];
        }

        foreach ( $sites as $site ) {
            $site_id = $site->blog_id;
            $subsites_urls[] = get_site_url( $site_id );
        }

        return $subsites_urls;
    }
}
