{footer_script require='jquery'}
jQuery().ready(function() {
  jQuery.ajax({
    url: "ws.php?format=json&method=pwg.pfemail.check"
  });
});
{/footer_script}