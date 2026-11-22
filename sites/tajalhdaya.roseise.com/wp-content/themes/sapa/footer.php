<?php

$footer 	= apply_filters('sapa_tbay_get_footer_layout', 'footer_default');

?>

	</div><!-- .site-content -->
	<?php 
		do_action( 'sapa_before_do_footer' );
	?>
	<?php if (sapa_tbay_active_newsletter_sidebar()) : ?>
		<div id="newsletter-popup" class="newsletter-popup">
			<?php dynamic_sidebar('newsletter-popup'); ?>
		</div>
	<?php endif; ?>
	
	<?php if( !sapa_checkout_optimized() ) : ?>
	<footer id="tbay-footer" <?php sapa_tbay_footer_class();?>>
		<?php if ($footer != 'footer_default'): ?>
			
			<?php sapa_tbay_display_footer_builder(); ?>

		<?php else: ?> 
			
			<?php get_template_part('footers/footer-default'); ?>
			
		<?php endif; ?>			
	</footer><!-- .site-footer -->
	<?php endif; ?>

	<?php 
		do_action( 'sapa_after_do_footer' );
	?>
	
</div><!-- .site -->

<?php wp_footer(); ?>

</body>
</html>