<?php
/**
 * Theme functions for Diler Realty & Auction Child.
 */

if (!defined('ABSPATH')) {
    exit;
}

function dra_child_theme_setup()
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
}
add_action('after_setup_theme', 'dra_child_theme_setup');

function dra_child_enqueue_assets()
{
    wp_enqueue_style('divi-parent-style', get_template_directory_uri() . '/style.css', array(), wp_get_theme('Divi')->get('Version'));
    wp_enqueue_style('dra-child-style', get_stylesheet_uri(), array('divi-parent-style'), wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'dra_child_enqueue_assets');
