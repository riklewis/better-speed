jQuery(function() {
  //initialise tab widget
  jQuery("#better-speed-tabs").tabs({
    activate: function(event,ui) {
      var inp = jQuery("[name='_wp_http_referer']");
      var val = inp.val().split("#")[0];
      inp.val(val+"#"+ui.newPanel.attr("id"));
    }
  });
});
