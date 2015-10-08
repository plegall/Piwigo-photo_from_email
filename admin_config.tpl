{combine_script id='LocalStorageCache' load='footer' path='admin/themes/default/js/LocalStorageCache.js'}

{combine_script id='jquery.selectize' load='footer' path='themes/default/js/plugins/selectize.min.js'}
{combine_css id='jquery.selectize' path="themes/default/js/plugins/selectize.{$themeconf.colorscheme}.css"}

{combine_script id='jquery.underscore' load='footer' path='themes/default/js/plugins/underscore.js'}

{html_style}{literal}
form p {text-align:left;}
.subOption {margin-left:2em; margin-bottom:20px;}
fieldset {border:none; border-top:1px solid #bbb;}
table.table2 {margin:0;}
.passwordCell {display:none}
form input[type=text] {width:400px}
.loading {display:none;}
#examples {display:none;}
.example {font-style:italic;}
{/literal}{/html_style}

{footer_script}
var pwg_token = "{$PWG_TOKEN}";

var groupsCache = new GroupsCache({
  serverKey: '{$CACHE_KEYS.groups}',
  serverId: '{$CACHE_KEYS._hash}',
  rootUrl: '{$ROOT_URL}'
});

{literal}
jQuery(document).ready(function() {
  groupsCache.selectize(jQuery('[data-selectize=groups]'));

  jQuery("#displayForm").click(function(){
    jQuery("[name=add_mailbox] legend").html("{/literal}{'Add new mailbox'|translate|escape:html}{literal}");
    jQuery("[name=submit_add]").val("{/literal}{'Add'|translate|escape:html}{literal}");

    jQuery("[name=add_mailbox]").trigger("reset");
    jQuery("[name=id]").val(null); // reset on form does not reset hidden fields

    jQuery("[name=add_mailbox]").show();

    return false;
  });

  jQuery(".showPathExamples").click(function(){
    jQuery("#examples").show();
    jQuery(this).hide();
    return false;
  });

  jQuery(".useExample").click(function(){
    jQuery("[name=path]").val(jQuery(this).closest('span').find(".example").text());
    return false;
  });

  jQuery("#cancelForm").click(function(){
    jQuery("[name=add_mailbox]").hide();
    return false;
  });

  /* edit mailbox */
  jQuery(document).on('click', 'a.mailbox-edit', function() {
    jQuery("[name=add_mailbox] legend").html("{/literal}{'Edit mailbox'|translate|escape:html}{literal}");
    jQuery("[name=submit_add]").val("{/literal}{'Save changes'|translate|escape:html}{literal}");

    var data = jQuery(this).data("mailbox");

    // fill the edit form
    jQuery("[name=id]").val(data.id);
    jQuery("[name=path]").val(data.path);
    jQuery("[name=login]").val(data.login);
    jQuery("[name=password]").val(data.password);
    jQuery("[name=category_id]").val(data.category_id);
    jQuery("[name=moderation]").prop("checked", (data.moderated == "true"));

    jQuery("[name=add_mailbox]").show();

    return false;
  });

  jQuery("form[name=add_mailbox]").submit(function() {
    var moderated = false;
    if (jQuery('[name=moderation]').is(':checked')) {
      moderated = true;
    }

    jQuery.ajax({
      url: "ws.php?format=json&method=pfemail.mailbox.save",
      type:"POST",
      data: jQuery(this).serialize()+"&moderated="+moderated+"&pwg_token="+pwg_token,
      beforeSend: function() {
        jQuery("form[name=add_mailbox] .loading").show();
      },
      success:function(data_json) {
        jQuery("form[name=add_mailbox] .loading").hide();

        var data = jQuery.parseJSON(data_json);
        if (data.stat == 'ok') {
          console.log('mailbox id = '+data.result.id);

          jQuery("form[name=add_mailbox]").hide();

		      /* Render the underscore template */
          var mailbox = data.result;

          /* search the album name */
          jQuery("select[name=category_id] option").each(function() {
            if (jQuery(this).val() == mailbox.category_id) {
              mailbox.album = jQuery(this).html();
            }
          });

          mailbox.data = JSON.stringify(mailbox);
          _.templateSettings.variable = "mailbox";
          
          var template = _.template(
            jQuery("script.mailboxLine").html()
		      );

          // is it an edit or a new mailbox?
          jQuery("#mailboxes tr a.mailbox-delete").each(function() {
            if (jQuery(this).data("mailbox_id") == mailbox.id) {
              jQuery(this).closest("tr").remove();
            }
          });
          
          // jQuery("#mailboxes").prepend(template(mailbox));
          jQuery("#mailboxes tr:eq(0)").after(template(mailbox));
        }
        else {
          console.log('oh oh, a problem occured');
          // jQuery("#addUserForm .errors").html('&#x2718; '+data.message).show();
        }
      },
      error:function(XMLHttpRequest, textStatus, errorThrows) {
        jQuery("form[name=add_mailbox] .loading").hide();
      }
    });

    return false;

  });

  /* delete mailbox */
  jQuery(document).on('click', 'a.mailbox-delete', function() {
    if (!confirm("{/literal}{'Are you sure?'|translate|escape:javascript}{literal}")) {
      return false;
    }

    var mailbox_id = jQuery(this).data("mailbox_id");
    var $this = jQuery(this);

    jQuery.ajax({
      url: "ws.php?format=json&method=pfemail.mailbox.delete",
      type:"POST",
      data: {
        pwg_token : pwg_token,
        id : mailbox_id
      },
      success:function(data) {
        var data = jQuery.parseJSON(data);
        if (data.stat == 'ok') {
          $this.closest("tr").remove();
        }
        else {
          console.log('oups, a problem occured');
        }
      },
      error:function(XMLHttpRequest, textStatus, errorThrows) {
        console.log('oups, a bigger problem occured');
      }
    });

    return false;
  });

  /* test mailbox */
  jQuery("#testMailbox").click(function(){
    jQuery.ajax({
      url: "ws.php?format=json&method=pfemail.mailbox.test",
      type:"POST",
      data: jQuery("form[name=add_mailbox]").serialize()+"&pwg_token="+pwg_token,
      beforeSend: function() {
        jQuery("form[name=add_mailbox] .loading").show();
      },
      success:function(data) {
        jQuery("form[name=add_mailbox] .loading").hide();

        try {
          var data = jQuery.parseJSON(data);

          if (typeof data.result.error != 'undefined') {
             alert(data.result.error);
          }
          else {
            alert(data.result.Nmsgs+" messages in mailbox");
          }
        }
        catch (e) {
          alert("an error occured: "+data);
        }
      },
      error:function(XMLHttpRequest, textStatus, errorThrows) {
        jQuery("form[name=add_mailbox] .loading").hide();

        console.log('oups, a bigger problem occured');
      }
    });

    return false;
  });
});
{/literal}{/footer_script}

<div class="titrePage">
  <h2>{'Configuration'|@translate} - {'Community'|@translate}</h2>
</div>

<p style="text-align:left; margin:0 1em;">
  <a id="displayForm" class="icon-plus-circled" href="#">{'Add new mailbox'|@translate}</a>
</p>

<form method="post" name="add_mailbox" action="{$F_ADD_ACTION}" style="display:none">
  <fieldset>
    <legend>{'Add new mailbox'|@translate}</legend>

    <p>
      <strong>{'Path'|@translate}</strong>
      <br>
      <input type="text" name="path" value="{$path}"> <a href="#" class="icon-eye showPathExamples">show examples</a>
      <span id="examples">
        <br><span><span class="example">{literal}{mail.gandi.net:993/imap/ssl}INBOX{/literal}</span> <a href="#" class="useExample icon-pencil">use it</a></span>
        <br><span><span class="example">{literal}{ssl0.ovh.net:993/imap/ssl}INBOX{/literal}</span> <a href="#" class="useExample icon-pencil">use it</a></span>
        <br><span><span class="example">{literal}{imap.gmail.com:993/imap/ssl}INBOX{/literal}</span> <a href="#" class="useExample icon-pencil">use it</a></span>
      </span>
    </p>

    <p>
      <strong>{'Login'|@translate}</strong>
      <br>
      <input type="text" name="login" value="{$login}">
    </p>

    <p>
      <strong>{'Password'|@translate}</strong>
      <br>
      <input type="text" name="password" value="{$path}">
    </p>

    <p>
      <strong>{'Where?'|@translate}</strong>
      <br>
      <select class="categoryDropDown" name="category_id">
        {html_options options=$category_options selected=$category_options_selected}
      </select>
    </p>

    <p>
      <strong>{'Moderation'|translate}</strong>
      <br>
      <label><input type="checkbox" name="moderation"> an administrator must approve each photo submitted</label>
    </p>

    <input type="hidden" name="id" value="">
    
    <p style="margin-top:1.5em;">
      <input class="submit" type="submit" name="submit_add" value="{'Add'|@translate}"/>
      <a href="#" class="icon-cancel-circled" id="cancelForm">{'Cancel'|@translate}</a>
      <a href="#" class="icon-arrows-cw" id="testMailbox">{'Test mailbox'|@translate}</a>
      <img class="loading" src="themes/default/images/ajax-loader-small.gif">
    </p>
  </fieldset>
</form>

<fieldset>
  <legend>{'Mailboxes'|translate}</legend>


  <table class="table2" id="mailboxes">
    <tr class="throw">
      <th>path</th>
      <th>login</th>
      <th class="passwordCell">password</th>
      <th>album</th>
      <th>moderated</th>
      <th>&nbsp;{* action *}</th>
    </tr>

  {foreach from=$mailboxes item=mailbox name=mailbox_loop}
    <tr>
      <td>{$mailbox.path}</td>
      <td>{$mailbox.login}</td>
      <td class="passwordCell">{$mailbox.password}</td>
      <td>{$mailbox.album}</td>
      <td>{$mailbox.moderated}</td>
      <td>
        <a class="mailbox-edit" data-mailbox="{$mailbox.data|escape:html}"><span class="icon-pencil"></span>{'Edit'|@translate}</a>
        <a class="mailbox-delete" data-mailbox_id="{$mailbox.id}"><span class="icon-trash"></span>{'Delete'|@translate}</a>
      </td>
    </tr>
  {/foreach}
  </table>
</fieldset>

<fieldset>
  <legend>{'Notification'|translate}</legend>

<form method="post" action="{$F_ACTION}">

  <p>
{if count($groups) > 0}
    <strong>{'Notify groups on new photos'|@translate}</strong>
    <br>
    <select data-selectize="groups" data-value="{$groups_selected|@json_encode|escape:html}"
      placeholder="{'Type in a search term'|translate}"
      name="groups[]" multiple style="width:600px;"></select>
{else}
    {'There is no group in this gallery.'|@translate} <a href="admin.php?page=group_list" class="externalLink">{'Group management'|@translate}</a>
{/if}
  </p>

{if count($groups) > 0}
  <p class="formButtons">
    <input type="submit" name="submit" value="{'Save Settings'|@translate}">
  </p>
{/if}

</form>

</fieldset>

{* Underscore Template Definition *}
<script type="text/template" class="mailboxLine">
<tr>
  <td><%- mailbox.path %></td>
  <td><%- mailbox.login %></td>
  <td class="passwordCell"><%- mailbox.password %></td>
  <td><%- mailbox.album %></td>
  <td><%- mailbox.moderated %></td>
  <td>
    <a class="mailbox-edit" data-mailbox="<%- mailbox.data %>"><span class="icon-pencil"></span>{'Edit'|@translate}</a>
    <a class="mailbox-delete" data-mailbox_id="<%- mailbox.id %>"><span class="icon-trash"></span>{'Delete'|@translate}</a>
  </td>
</tr>
</script>
