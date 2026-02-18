<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_User_Actions_Helper
{
    const FIRST_LAST_NAME_MAX_LENGTH = 100;

    public static function et_add_user_based_on_site_type( $user_data, $add_existing_user, $blog_id )
    {
        if ( is_multisite() && ! $blog_id ) {
            $password = wp_generate_password( 12, false );
            $user_id = wpmu_create_user( $user_data['username'], $password, $user_data['email'] );

            if ( $user_id ) {
                wp_send_new_user_notifications( $user_id );
                return $user_id;
            }

            return new WP_Error( 'create_user', __('Could not create user') );
        }

        if ( $blog_id ) {
            return self::et_handle_subsite_user_addition( $user_data, $add_existing_user, $blog_id );
        }

        return self::et_add_user_to_regular_site( $user_data );
    }

    public static function et_add_user_to_regular_site( $user_data )
    {
        $data = array(
            'user_login' => $user_data['username'],
            'user_pass' => $user_data['password'],
            'user_email' => $user_data['email'],
            'first_name' =>  $user_data['first_name'],
            'last_name' => $user_data['last_name'],
            'display_name' => $user_data['first_name'],
            'description' => $user_data['bio'],
            'user_url' => $user_data['website_url'],
            'role' => $user_data['role']
        );

        $user_id = wp_insert_user( $data );

        if ( ! is_wp_error( $user_id ) && ! empty( $user_data['send_email_notification'] ) ) {
            wp_send_new_user_notifications( $user_id );
        }

        return $user_id;
    }

    public static function et_handle_subsite_user_addition( $user_data, $add_existing_user, $blog_id )
    {
        if ( $blog_id ) {
            switch_to_blog( $blog_id );
        }

        if ( ! empty( $user_data['skip_confirmation_email'] ) ) {
            $user_id = $add_existing_user
                ? self::et_add_existing_user_to_subsite_skip_confirmation_email( $user_data )
                : self::et_create_new_user_for_subsite_skip_confirmation_email($user_data);
        } else {
            $user_id = $add_existing_user
                ? self::et_add_existing_user_to_subsite_with_confirmation_email( $user_data, $blog_id )
                : self::et_create_new_user_for_subsite_with_confirmation_email( $user_data, $blog_id );
        }

        if ( $blog_id ) {
            restore_current_blog();
        }

        return $user_id;
    }

    public static function et_add_existing_user_to_subsite_skip_confirmation_email( $user_data )
    {
        $user_id = add_existing_user_to_blog(
            array(
                'user_id' => $user_data['wordpress_user_id'],
                'role' => $user_data['role'],
            ),
        );

        return is_wp_error( $user_id ) ? $user_id : $user_data['wordpress_user_id'];
    }

    public static function et_add_existing_user_to_subsite_with_confirmation_email( $user_data, $blog_id )
    {
        if ( ! function_exists('get_user_by') ) {
            require_once ABSPATH . WPINC . '/pluggable.php';
        }

        require_once( ABSPATH . 'wp-admin/includes/user.php' );

        $new_user_email = array();
        $user_id = $user_data['wordpress_user_id'];
        $newuser_key = wp_generate_password( 20, false );

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'divi_dash_add_existing_user_to_subsite', __('User not found') );
        }

        add_option(
            'new_user_' . $newuser_key,
            array(
                'user_id' => $user_id,
                'email'   => $user->user_email,
                'role'    => $user_data['role'],
            )
        );

        $roles = get_editable_roles();
        $role  = $roles[ $user_data['role'] ];

        do_action( 'invite_user', $user_id, $role, $newuser_key );

        $switched_locale = switch_to_user_locale( $user_id );

        if ('' !== get_option('blogname')) {
            $site_title = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        } else {
            $site_title = wp_parse_url( home_url(), PHP_URL_HOST );
        }

        /* translators: 1: Site title, 2: Site URL, 3: User role, 4: Activation URL. */
        $message = __(
        'Hi,

You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.

Please click the following link to confirm the invite:
%4$s'
    );

        $new_user_email['to']      = $user->user_email;
        $new_user_email['subject'] = sprintf(
            /* translators: Joining confirmation notification email subject. %s: Site title. */
            __('[%s] Joining Confirmation'),
            $site_title
        );
        $new_user_email['message'] = sprintf(
            $message,
            get_option('blogname'),
            home_url(),
            wp_specialchars_decode( translate_user_role( $role['name'] ) ),
            home_url( "/newbloguser/$newuser_key/" )
        );
        $new_user_email['headers'] = '';

        $new_user_email = apply_filters( 'invited_user_email', $new_user_email, $user_id, $role, $newuser_key );

        wp_mail(
            $new_user_email['to'],
            $new_user_email['subject'],
            $new_user_email['message'],
            $new_user_email['headers']
        );

        if ( $switched_locale ) {
            restore_previous_locale();
        }

        return true;
    }

    public static function et_create_new_user_for_subsite_skip_confirmation_email( $user_data )
    {
        $password = wp_generate_password(12, false);

        return wp_insert_user(
            array(
                'user_login' => $user_data['username'],
                'user_email' => $user_data['email'],
                'user_pass' => $password,
                'role' => $user_data['role'],
            ),
        );
    }

    public static function et_create_new_user_for_subsite_with_confirmation_email( $user_data, $blog_id )
    {
        require_once( ABSPATH . 'wp-admin/includes/user.php' );

        $_REQUEST['role'] = $user_data['role'];

        add_filter('wpmu_signup_user_notification_email', 'admin_created_user_email');

        wpmu_signup_user(
            $user_data['username'],
            $user_data['email'],
            array(
                'add_to_blog' => $blog_id,
                'new_role'    => $user_data['role'],
            )
        );

        return true;
    }

    public static function et_site_users_list( $page = 1 ): array
    {
        if ( ! function_exists('get_user_by') ) {
            require_once ABSPATH . WPINC . '/pluggable.php';
        }

        $users_per_page = Divi_Dash_Kernel::API_RECORDS_PER_PAGE;

        $options = [
            'offset' => $page ? ($page - 1) * $users_per_page : 0,
            'number' => $users_per_page,
        ];

        $users = get_users( $options );

        $user_list = self::_get_users_list( $users );

        $count_total_users = count_users();
        $total_users = $count_total_users['total_users'];

        return [
            'total_pages' => ceil($total_users / $users_per_page),
            'current_page' => max(1, $page),
            'site_users' => $user_list
        ];
    }

    public static function et_site_users_list_all_subsites( $page = 1 ): array
    {
        $users_per_page = Divi_Dash_Kernel::API_RECORDS_PER_PAGE;

        $options = [
            'number'  => $users_per_page,
            'offset'  => ( $page - 1 ) * $users_per_page,
            'blog_id' => 0,
        ];

        $wp_user_search = new WP_User_Query( $options );

        $users = $wp_user_search->get_results();

        $user_list = self::_get_users_list( $users );

        $user_list = self::maybe_add_role_info_for_all_subsite_users( $user_list );

        return [
            'total_pages' => ceil( $wp_user_search->get_total() / $users_per_page ),
            'current_page' => max( 1, $page ),
            'site_users' => $user_list
        ];
    }

    protected static function _get_users_list( $users ): array
    {
        if ( ! function_exists('get_user_by') ) {
            require_once ABSPATH . WPINC . '/pluggable.php';
        }

        $user_list = [];

        foreach( $users as $index => $user ) {
            $user_list[$index]['ID'] = $user->ID;
            $user_list[$index]['avatar'] = Divi_Dash_Helper::et_prepare_url_to_response( get_avatar_url( $user->ID ) );
            $user_list[$index]['first_last_name'] = self::et_get_site_user_name($user);
            $user_list[$index]['roles'] = $user->roles;
        }

        return $user_list;
    }

    public static function maybe_add_role_info_for_all_subsite_users( $user_list ): array
    {
        if ( empty( $user_list ) || empty( get_sites() ) ) {
            return $user_list;
        }

        foreach( $user_list as $index => $user ) {
            if ( ! empty( $user_list[$index]['roles'] ) ) {
                continue;
            }

            $user_roles = self::get_user_roles_from_all_subsites( $user['ID'] );

            if ( ! empty( $user_roles ) ) {
                $user_list[$index]['roles'] = $user_roles;
            }
        }

        return $user_list;
    }

    public static function get_user_roles_from_all_subsites( $user_id ): array
    {
        $sites = get_sites();
        $user_roles = array();

        if ( empty( $sites ) || empty( $user_id ) ) {
            return $user_roles;
        }

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            $user_object = get_userdata( $user_id );

            if ( ! empty( $user_object ) && ! empty( $user_object->roles ) ) {
                $user_roles[] = $user_object->roles;
            }

            restore_current_blog();
        }

        if ( ! empty( $user_roles ) ) {
            return array_merge( ...$user_roles );
        }

        return $user_roles;
    }

    private static function et_get_site_user_name( $user ): string
    {
        $fullName = self::get_trimmed_name( $user->first_name . ' ' . $user->last_name );

        return $fullName ? $fullName : 'Unnamed User';
    }

    public static function et_get_site_user_info( $user_id ): array
    {
        if ( ! function_exists('get_userdata') ) {
            require_once ABSPATH . WPINC . '/pluggable.php';
        }

        $user_object = get_userdata( $user_id );

        if ( empty( $user_object ) ) {
            return [];
        }

        $user_data_object = $user_object->data;
        $first_name = self::get_trimmed_name( get_user_meta( $user_id, 'first_name', true ), self::FIRST_LAST_NAME_MAX_LENGTH/2 );
        $last_name = self::get_trimmed_name( get_user_meta( $user_id, 'last_name', true ), self::FIRST_LAST_NAME_MAX_LENGTH/2 );
        $bio = get_user_meta( $user_id, 'description', true );

        return array(
            'wordpress_user_id' => $user_data_object->ID,
            'username' => $user_data_object->user_login,
            'email' => $user_data_object->user_email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $user_object->roles[0],
            'bio' => $bio,
            'website' => $user_data_object->user_url,
        );
    }

    private static function get_trimmed_name( $name, $max_length = self::FIRST_LAST_NAME_MAX_LENGTH ): string
    {
        return substr( trim( $name ), 0, $max_length );
    }
}
