<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Website_Data_Getter
{
    public static function et_get_website_core_data()
    {
        require_once ABSPATH . 'wp-admin/includes/update.php';
    
        $website_core_data['name'] = get_bloginfo('name');
    
        $current_wp_version = get_bloginfo( 'version' );
        if ( !empty($current_wp_version) ) {
            $website_core_data['status'] = Divi_Dash_Kernel::CURRENT_CORE_VERSION_STATUS;
            $website_core_data['installed_version'] = $current_wp_version;
        }
    
        $wp_update_version = Divi_Dash_Website_Info_Getter::et_get_wordpress_update_version();
        if ( !empty($wp_update_version) ) {
            $website_core_data['status'] = Divi_Dash_Kernel::UPGRADE_CORE_VERSION_STATUS;
            $website_core_data['latest_released_version'] = $wp_update_version;
        }
    
        $website_update_status = Divi_Dash_Website_Info_Getter::et_get_website_update_status();
    
        if ( $website_update_status === Divi_Dash_Kernel::UPGRADE_CORE_VERSION_STATUS ) {
            $website_core_data['status'] = Divi_Dash_Kernel::UPGRADE_CORE_VERSION_STATUS;
        }
    
        if( $website_update_status === Divi_Dash_Kernel::CURRENT_CORE_VERSION_STATUS ) {
            $website_core_data['status'] = Divi_Dash_Kernel::CURRENT_CORE_VERSION_STATUS;
        }
    
        if ( is_multisite() ) {
            $website_core_data['is_multisite'] = 1;
            $website_core_data['current_site'] = Divi_Dash_Helper::et_get_site_hostname();
        } else {
            $website_core_data['is_multisite'] = 0;
        }
    
        return $website_core_data;
    }
    
    public static function et_get_themes_data( $website_core_data )
    {
        $website_themes_data = array();
        $all_themes = wp_get_themes();
    
        ksort( $all_themes );
    
        foreach ( $all_themes as $theme_slug => $theme ) {
    
            if ( ! $theme ) {
                continue;
            }
    
            $website_themes_data[$theme_slug]['name'] = Divi_Dash_Website_Info_Getter::et_get_theme_info( $theme, 'name' );
    
            $website_themes_data[$theme_slug]['installed_version'] = Divi_Dash_Website_Info_Getter::et_get_theme_info( $theme, 'version' );
    
            $website_themes_data[$theme_slug]['is_theme_allowed_on_multisite'] = ! empty( $website_core_data['is_multisite'] ) ? Divi_Dash_Website_Info_Getter::et_is_theme_allowed_on_multisite( $theme ) : 0;
    
            $website_themes_data[$theme_slug]['is_active'] = Divi_Dash_Website_Info_Getter::et_is_theme_active( $theme_slug ) ? 1 : 0;
    
            if ( Divi_Dash_Website_Info_Getter::et_is_child_theme( $theme ) ) { // If the template of the theme is not equals to the slug then it's a child theme
                $website_themes_data[$theme_slug]['type'] = 'child';
    
                if ( $parent_theme_template = Divi_Dash_Website_Info_Getter::et_get_parent_theme_template( $theme ) ) {
                    $website_themes_data[$theme_slug]['parent_theme_name'] = $parent_theme_template;
                }
            }
        }
    
        // Preapre data of the themes which have updates
        $themes_with_updates = get_theme_updates();
    
        foreach ( $themes_with_updates as $theme_slug => $theme_with_update ) {
            if ( ! isset( $website_themes_data[$theme_slug] ) ) {
                divi_dash_kernel()->dh_log_error( 'updatable theme not listed in wp_get_themes() : ' . $theme_slug );
                continue;
            }

            $website_themes_data[$theme_slug]['latest_released_version'] = Divi_Dash_Website_Info_Getter::et_get_theme_update_version( $theme_with_update );
        }
    
        foreach ( $website_themes_data as $theme_slug => $data ) {
            if ( empty( $website_themes_data[$theme_slug]['latest_released_version'] ) ) {
                $website_themes_data[$theme_slug]['latest_released_version'] = $website_themes_data[$theme_slug]['installed_version'];
            }
        }
    
        return $website_themes_data;
    }
    
    public static function et_get_plugins_data( $website_core_data )
    {
        $website_plugins_data = array();
        $all_plugins = get_plugins();
    
        foreach ( $all_plugins as $plugin_uri => $plugin ) {
    
            $plugin_slug = Divi_Dash_Helper::et_get_plugin_slug_from_uri( $plugin_uri );
    
            $website_plugins_data[$plugin_slug]['name'] = Divi_Dash_Website_Info_Getter::et_get_plugin_info( $plugin, 'name' );
    
            $website_plugins_data[$plugin_slug]['plugin_file'] = $plugin_uri;
    
            $website_plugins_data[$plugin_slug]['installed_version'] = Divi_Dash_Website_Info_Getter::et_get_plugin_info( $plugin, 'version' );
    
            $website_plugins_data[$plugin_slug]['is_plugin_active_for_network'] = ! empty( $website_core_data['is_multisite'] ) ? is_plugin_active_for_network( $plugin_uri ) : 0;
    
            if ( $is_network_only_plugin = is_network_only_plugin( $plugin_uri ) ) {
                $website_plugins_data[$plugin_slug]['is_network_only'] = $is_network_only_plugin;
            }
        }
    
        $plugin_updates = get_plugin_updates();
    
        foreach ( $plugin_updates as $plugin_uri => $plugin ) {
            $plugin_slug = Divi_Dash_Helper::et_get_plugin_slug_from_uri( $plugin_uri );

            if ( ! isset( $website_plugins_data[$plugin_slug] ) ) {
                divi_dash_kernel()->dh_log_error( 'updatable plugin not listed in get_plugins() : ' . $plugin_slug );
                continue;
            }

            $website_plugins_data[$plugin_slug]['latest_released_version'] = Divi_Dash_Website_Info_Getter::et_get_plugin_update_version( $plugin );
        }
    
        foreach ( $website_plugins_data as $plugin_slug => $data ) {
            if ( empty( $website_plugins_data[$plugin_slug]['latest_released_version'] ) ) {
                $website_plugins_data[$plugin_slug]['latest_released_version'] = $website_plugins_data[$plugin_slug]['installed_version'];
            }
        }
    
        $active_plugins = Divi_Dash_Website_Info_Getter::et_get_active_plugins();
    
        foreach ( $active_plugins as $plugin_uri ) {
            $plugin_slug = Divi_Dash_Helper::et_get_plugin_slug_from_uri( $plugin_uri );

            if ( ! isset( $website_plugins_data[$plugin_slug] ) ) {
                divi_dash_kernel()->dh_log_error( 'active plugin not listed in get_plugins() : ' . $plugin_slug );
                continue;
            }

            $website_plugins_data[$plugin_slug]['is_active'] = 1;
        }
    
        return $website_plugins_data;
    }
    
    public static function et_get_subsites_data( $website_core_data )
    {
        $website_subsites_data = array();

        if ( $website_core_data['is_multisite'] ) {
            // Subsites active theme and plugins
            $subsites = get_sites();
    
            if ( null === $subsites ) {
                return [];
            }
    
            foreach ( $subsites as $subsite ) {
                $subsite_domain = Divi_Dash_Website_Info_Getter::et_get_subsite_domain( $subsite );
    
                if ( null === $subsite_domain ) {
                    continue;
                }
    
                if ( $subsite_domain == Divi_Dash_Helper::et_get_site_hostname() ) {
                    if( ! empty( $subsite->path ) ) {
                        $subsite_domain .= $subsite->path;
                    } else {
                        continue;	
                    }
                }
    
                $blog_id = get_blog_id_from_url( Divi_Dash_Website_Info_Getter::et_get_subsite_domain( $subsite ), $subsite->path );
    
                switch_to_blog( $blog_id );
                $current_subsite_theme                                          = wp_get_theme();
                $active_theme                                                   = $current_subsite_theme->get_stylesheet();
                $website_subsites_data[$subsite_domain]['name']              = get_bloginfo( 'name' );
                $website_subsites_data[$subsite_domain]['active_theme']      = $active_theme;
                $website_subsites_data[$subsite_domain]['active_plugins']    = Divi_Dash_Website_Info_Getter::et_get_active_plugins();
    
                if ( Divi_Dash_Website_Info_Getter::et_is_child_theme( $current_subsite_theme ) ) {
                    $website_subsites_data[$subsite_domain]['is_child'] = 1;
    
                    $parent_theme_template = Divi_Dash_Website_Info_Getter::et_get_parent_theme_template( $current_subsite_theme );
    
                    if ( '' !== $parent_theme_template ) {
                        $website_subsites_data[$subsite_domain]['subsite_parent_theme_slug'] = $parent_theme_template;
                    }
    
                } else {
                    $website_subsites_data[$subsite_domain]['is_child'] = 0;
                }
    
                restore_current_blog();
            }
        }

        return $website_subsites_data;
    }
}
