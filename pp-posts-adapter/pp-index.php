<?php
/*
Template Name: Auctions Index
*/
/**
 * The main template file for marketplace listings.
 *
 * @package Prospress
 * @subpackage Theme
 * @since 0.7
 */
global $bid_system;
?>
<link rel="stylesheet" type="text/css" media="all" href="<?php echo PP_CORE_URL . '/prospress.css'; ?>">
<?php get_header(); ?>
	<div id="container">
		<div id="content">
		<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
			<h1><?php the_title(); ?></h1>
			<p><?php the_content(); ?></p>
			<?php $loop = new WP_Query( array( 'post_type' => $bid_system->name ) ); ?>
			<?php if ( $loop->have_posts() ) : while ( $loop->have_posts() ) : $loop->the_post(); ?>

				<h2 class="pp-title"><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h2>
				<div class="pp-price"><?php $bid_system->the_winning_bid_value(); ?></div>
				<div class="pp-end-date"><?php the_post_end_date(); ?></div>
				<!-- Display the date (November 16th, 2009 format) and a link to other posts by this posts author. -->
				<div class="publish-date"><?php the_time('F jS, Y') ?> by <?php the_author_posts_link() ?></div>

				<?php the_content(); ?>

				<p class="postmetadata">Posted in <?php the_category(', '); ?></p>

			<?php endwhile; else: ?>

				<p>No marketplace listings yet.</p>

			<?php endif; ?>
		<?php endwhile; ?>
		</div>
	</div>

	<div id="pp-sidebar" class="pp-sidebar">
		<ul class="xoxo">
			<?php dynamic_sidebar( 'prospress-index-sidebar' ); ?>
		</ul>
	</div>

<?php //get_sidebar(); ?>
<?php get_footer(); ?>
