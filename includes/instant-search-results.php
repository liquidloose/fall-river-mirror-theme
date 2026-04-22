<?php
/**
 * Theme override for Codemanas instant search hit templates.
 *
 * Loaded via cm_typesense_locate_template (see typesense-instant-search-ui.php).
 *
 * @package FallRiverMirror
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$placeholder = defined( 'CODEMANAS_TYPESENSE_THUMBNAIL_IMAGE_URL' )
	? CODEMANAS_TYPESENSE_THUMBNAIL_IMAGE_URL
	: '';
?>
<script type="text/html" id="tmpl-cmswt-Result-itemTemplate--default">
	<# if(data.taxonomy === undefined){ #>
	<div class="hit-header">
		<# var imageHTML = '';
		if(data.post_thumbnail_html !== undefined && data.post_thumbnail_html !== ''){
		imageHTML = data.post_thumbnail_html
		}else if(data.post_thumbnail !== undefined && data.post_thumbnail !== ''){
		imageHTML = `<img src="${data.post_thumbnail}"
						  alt="${data.post_title}"
						  class="ais-Hit-itemImage"
		/>`
		}
		else{
		imageHTML = `<img src="<?php echo esc_url( $placeholder ); ?>"
						  alt="${data.post_title}"
						  class="ais-Hit-itemImage"
		/>`
		}
		#>
		<# if(imageHTML !== ''){ #>
		<a href="{{{data._highlightResult.permalink.value}}}" class="hit-header--link" rel="nofollow noopener">{{{imageHTML}}}</a>
		<# } #>
	</div>
	<# } #>
	<div class="hit-content">
		<# if(data._highlightResult.permalink !== undefined ) { #>
		<a href="{{{data._highlightResult.permalink.value}}}" class="hit-contentLink" rel="nofollow noopener"><h5 class="title">
				{{{data.formatted.post_title}}}</h5></a>
		<# } #>
		<# if( data.post_type === 'post' ) { #>
		<div class="hit-meta">
			<span class="posted-by">
				By {{data.post_author}}
			</span>
			<span class="posted-on">
				<time datetime="">{{data.formatted.postedDate}}</time>
			</span>
			<# if ( Object.keys(data.formatted.cats).length > 0 ) { #>
			<div class="hit-cats">
				<# for ( let key in data.formatted.cats ) { #>
				<div class="hit-cat"><a href="{{{data.formatted.cats[key]}}}">{{{key}}}</a>,</div>
				<# } #>
			</div>
			<# } #>
		</div>
		<# } #>
		<div class="hit-description">{{{data.formatted.post_content}}}</div>
		<div class="hit-link">
			<a href="{{data.permalink}}"><?php esc_html_e( 'Read More...', 'search-with-typesense' ); ?></a>
		</div>
	</div>
</script>

<script type="text/html" id="tmpl-cmswt-Result-itemTemplate--article">
	<# if(data.taxonomy === undefined){ #>
	<div class="hit-header">
		<# var imageHTML = '';
		if(data.post_thumbnail_html !== undefined && data.post_thumbnail_html !== ''){
		imageHTML = data.post_thumbnail_html
		}else if(data.post_thumbnail !== undefined && data.post_thumbnail !== ''){
		imageHTML = `<img src="${data.post_thumbnail}"
						  alt="${data.post_title}"
						  class="ais-Hit-itemImage"
		/>`
		}
		else{
		imageHTML = `<img src="<?php echo esc_url( $placeholder ); ?>"
						  alt="${data.post_title}"
						  class="ais-Hit-itemImage"
		/>`
		}
		#>
		<# if(imageHTML !== ''){ #>
		<a href="{{{data._highlightResult.permalink.value}}}" class="hit-header--link" rel="nofollow noopener">{{{imageHTML}}}</a>
		<# } #>
	</div>
	<# } #>
	<div class="hit-content">
		<# if(data._highlightResult.permalink !== undefined ) { #>
		<a href="{{{data._highlightResult.permalink.value}}}" class="hit-contentLink" rel="nofollow noopener"><h5 class="title">
				{{{data.formatted.post_title}}}</h5></a>
		<# } #>
		<# if ( data._article_meeting_date ) { #>
		<div class="hit-meeting-date">{{ data._article_meeting_date }}</div>
		<# } #>
		<div class="hit-description">{{{data.formatted.post_content}}}</div>
		<div class="hit-link">
			<a href="{{data.permalink}}"><?php esc_html_e( 'Read More...', 'search-with-typesense' ); ?></a>
		</div>
	</div>
</script>
