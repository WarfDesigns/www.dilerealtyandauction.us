<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Divi_Dash_Optimization_Actions_Helper
{
    public static function et_delete_post_revisions( $blog_id = null )
    {
        if ( $blog_id ) {
            switch_to_blog( $blog_id );
        }

        global $wpdb;

        $post_revision_ids = $wpdb->get_col( "
            SELECT r.ID
            FROM $wpdb->posts r
            JOIN $wpdb->posts p ON r.post_parent = p.ID
            WHERE r.post_type = 'revision'
            AND r.post_status = 'inherit'
            AND p.post_type = 'post'
            AND p.post_status != 'trash'
        " );
                
        $deleted = empty( $post_revision_ids );

        if ( ! empty( $post_revision_ids ) ) {
            foreach ( $post_revision_ids as $revision_id ) {
                if ( wp_delete_post( $revision_id, true ) ) {
                    $deleted = true;
                }
            }
        }
    
        if ( $blog_id ) {
            restore_current_blog();
        }
    
        if ( $deleted ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::DELETED );
        }
    
        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
    }
    
    public static function et_delete_spammed_comments( $blog_id = null )
    {
        if ( $blog_id ) {
            switch_to_blog( $blog_id );
        }
    
        global $wpdb;

        $spam_comment_ids = $wpdb->get_col( "
            SELECT comment_ID 
            FROM $wpdb->comments 
            WHERE comment_approved = 'spam'
        " );

        $deleted = empty( $spam_comment_ids );

        if ( ! empty( $spam_comment_ids ) ) {
            foreach ( $spam_comment_ids as $comment_id ) {
                if ( wp_delete_comment( $comment_id, true ) ) {
                    $deleted = true;
                }
            }
        }
    
        if ( $blog_id ) {
            restore_current_blog();
        }
    
        if ( $deleted ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::DELETED );
        }
    
        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
    }
    
    public static function et_delete_page_revisions( $blog_id = null )
    {
        if ( $blog_id ) {
            switch_to_blog( $blog_id );
        }
    
        global $wpdb;

        $page_revision_ids = $wpdb->get_col("
            SELECT r.ID 
            FROM $wpdb->posts r
            JOIN $wpdb->posts p ON r.post_parent = p.ID
            WHERE r.post_type = 'revision' 
            AND r.post_status = 'inherit'
            AND p.post_type = 'page' 
            AND p.post_status != 'trash'
        ");

        $deleted = empty( $page_revision_ids );

        if ( ! empty( $page_revision_ids ) ) {
            foreach ( $page_revision_ids as $revision_id ) {
                if ( wp_delete_post( $revision_id, true ) ) {
                    $deleted = true;
                }
            }
        }
    
        if ( $blog_id ) {
            restore_current_blog();
        }
    
        if ( $deleted ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::DELETED );
        }
    
        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
    }
    
    public static function et_delete_page_trash( $blog_id = null )
    {
        if ( $blog_id ) {
            switch_to_blog( $blog_id );
        }

        global $wpdb;

        $trashed_page_ids = $wpdb->get_col("
            SELECT ID 
            FROM $wpdb->posts 
            WHERE post_type = 'page' 
            AND post_status = 'trash'
        ");
    
        $deleted = empty( $trashed_page_ids );
        
        if ( ! empty( $trashed_page_ids ) ) {
            foreach ( $trashed_page_ids as $page_id ) {
                if ( wp_delete_post( $page_id, true ) ) {
                    $deleted = true;
                }
            }
        }
    
        if ( $blog_id ) {
            restore_current_blog();
        }
    
        if ( $deleted ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::DELETED );
        }
    
        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
    }
    
    public static function et_delete_post_trash( $blog_id = null )
    {
        if ( $blog_id ) {
            switch_to_blog( $blog_id );
        }

        global $wpdb;

        $trashed_post_ids = $wpdb->get_col( "
            SELECT ID 
            FROM $wpdb->posts 
            WHERE post_type = 'post' 
            AND post_status = 'trash'
        " );

        $deleted = empty( $trashed_post_ids );

        if ( ! empty( $trashed_post_ids ) ) {
            foreach ( $trashed_post_ids as $post_id ) {
                if ( wp_delete_post( $post_id, true ) ) {
                    $deleted = true;
                }
            }
        }

        if ( $blog_id ) {
            restore_current_blog();
        }
    
        if ( $deleted ) {
            divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::DELETED );
        }
    
        divi_dash_kernel()->send_response_with_status( Divi_Dash_API_Response_Status::NOT_DELETED );
    }

    public static function et_get_post_revisions_count()
    {
        global $wpdb;

        return $wpdb->get_var("
            SELECT COUNT(r.ID)
            FROM $wpdb->posts r
            JOIN $wpdb->posts p ON r.post_parent = p.ID
            WHERE r.post_type = 'revision'
            AND r.post_status = 'inherit'
            AND p.post_type = 'post'
            AND p.post_status != 'trash'
        ");
    }

    public static function et_get_page_revisions_count()
    {
        global $wpdb;

        return $wpdb->get_var("
            SELECT COUNT(r.ID)
            FROM $wpdb->posts r
            JOIN $wpdb->posts p ON r.post_parent = p.ID
            WHERE r.post_type = 'revision'
            AND r.post_status = 'inherit'
            AND p.post_type = 'page'
            AND p.post_status != 'trash'
        ");
    }

    public static function et_get_page_trash_count(): int
    {
        global $wpdb;

        return $wpdb->get_var("
            SELECT COUNT(ID) 
            FROM $wpdb->posts 
            WHERE post_type = 'page' 
            AND post_status = 'trash'
        ");
    }

    public static function et_get_post_trash_count(): int
    {
        global $wpdb;

        return $wpdb->get_var("
            SELECT COUNT(ID) 
            FROM $wpdb->posts 
            WHERE post_type = 'post' 
            AND post_status = 'trash'
        ");
    }

    public static function et_get_spammed_comments_count(): int
    {
        $comments = wp_count_comments();

        return $comments->spam;
    }

}
