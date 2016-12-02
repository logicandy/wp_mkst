jQuery(document).ready(function($) {
  $('#toggle_add').accordion({
  	active: false,
  	collapsible: true
  });
  $('#toggle_track').accordion({
    active: false,
    collapsible: true
  });
  $('#toggle_phone').accordion({
    active: false,
    collapsible: true
  })
  $('#phone_masked').mask("+7 (999) 999-9999");
  $('#ship_provider').change(function() {
	$('#ship_desc').nextAll().remove();
  	htmlstr = '';
  	if (null != mkst_provider_options) {
	  	$.each(mkst_provider_options[$('#ship_provider').val()], function(key, value) {
	  		htmlstr = '<input type="text" name="'+key+'" placeholder="'+value+'"/>';
	  	});
	  	htmlstr += '<input type="submit" name="submit" class="button mini" value="'+mkst_l10n.add_button+'" />';
	}
  	$('#ship_desc').after(htmlstr);
  });
});