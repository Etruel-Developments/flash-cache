function hooks_cookie() {
  jQuery(".delete").click(function (e) {
    jQuery(this).parent().parent().remove();
  });
}
jQuery(document).ready(function () {
  hooks_cookie();
  jQuery("#add_new_dont_cache_cookie").click(function (e) {
    jQuery("#table_dont_cache_cookie").append(
      '<tr valign="top" class="tr_item_dont_cache_cookie"><td scope="row"><input type="text" name="flash_cache_advanced[dont_cache_cookie][]" value=""/><label title="" data-id="1" class="delete"><span class="dashicons dashicons-trash"></span></label></td></tr>'
    );
    hooks_cookie();
  });

  jQuery(".wpm_menu_close").click(function (e) {
    jQuery(".wpm_container").toggleClass("show_menu");
    return false;
  });

  jQuery(".btn_reset_to_default").click(function (e) {
    if (!confirm("Are you sure you want to reset to defaults?")) {
      e.preventDefault();
    }
  });
});
