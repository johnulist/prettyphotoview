<!-- Module PrettyPhotoView -->
<form id="categoryFilter" action="gallery" method="get">
	<h1>Wallovers gallery</h1>
	Click on an image to enlarge it! <select name="categorySelector" id="categorySelector">
		{foreach from=$categories item=category}
			<option 
			{if $category == $smarty.get.categorySelector}
				selected="selected"
			{/if}	
			 id="{$category}">{$category}</option>
		{/foreach}
	</select>
	<div id="prettyPhotoViewHolder" align="left" style="padding:0 0 10px 0;overflow: hidden; clear:both">
		<div id="wrap">
			<ul id="gallery">
				{foreach from=$xml item=my_item}
					<li>
						{if $my_item->img}
							<a href="{$path}{$my_item->img}" rel="prettyPhoto[pp_gal]" title="{$my_item->text}">
								<input type="hidden" value="&lt;a href=&quot;{$my_item->url}&quot;&gt;{$my_item->$text}&lt;/a&gt;" />
								<img src="{$path}{$my_item->thumb}" alt="{$my_item->$text}" title="{$my_item->$text}" />
							</a>
						{/if}
					</li>
				{/foreach}
			</ul>
		</div>
	</div>
</form>
<script type="text/javascript">
{literal}
	$(document).ready(function(){
	    $("a[rel^='prettyPhoto']").prettyPhoto();
	 });
	 
	$('#categorySelector').change(function() {
	  $('#categoryFilter').submit();
	});
{/literal}
</script>
<!-- /Module prettyPhotoView -->
