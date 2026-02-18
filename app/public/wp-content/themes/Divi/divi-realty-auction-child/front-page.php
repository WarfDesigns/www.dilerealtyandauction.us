<?php
/**
 * Front page template for Diler Realty & Auction.
 *
 * This template intentionally renders the page content so the entire homepage
 * is editable with Divi Builder / imported Divi JSON layouts.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main-content">
<?php
if (have_posts()) {
    while (have_posts()) {
        the_post();
        the_content();
    }
} else {
    ?>
    <section class="et_pb_section dra-hero" style="--dra-hero-image: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1800&q=80');">
        <div class="et_pb_row">
            <div class="et_pb_column et_pb_column_4_4">
                <div class="et_pb_text et_pb_text_align_center">
                    <h1>Trusted Real Estate. Results-Driven Auctions.</h1>
                    <p>Build your homepage with Divi Builder, then import your JSON layout template for complete visual control.</p>
                    <div class="dra-btn-wrap">
                        <a class="dra-btn dra-btn--gold" href="/listings">View Listings</a>
                        <a class="dra-btn dra-btn--ghost" href="/auctions">View Auctions</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
}
?>
</main>

<?php
get_footer();
