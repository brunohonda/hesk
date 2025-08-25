/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

function hesk_insertTag(tag) {
    var text_to_insert = '%%'+tag+'%%';
    hesk_insertAtCursor(document.form1.msg, text_to_insert);
    document.form1.msg.focus();
}

function hesk_insertAtCursor(myField, myValue) {
    if (document.selection) {
        myField.focus();
        sel = document.selection.createRange();
        sel.text = myValue;
    } else if (myField.selectionStart || myField.selectionStart == '0') {
        var startPos = myField.selectionStart;
        var endPos = myField.selectionEnd;
        myField.value = myField.value.substring(0, startPos) + myValue + myField.value.substring(endPos, myField.value.length);
        myField.selectionStart = startPos + myValue.length; myField.selectionEnd = startPos + myValue.length;
    } else {
        myField.value += myValue;
    }
}

function hesk_changeAll(myID, group = 0) {
    var d = document.form1;
    var setTo = myID.checked ? true : false;

    for (var i = 0; i < d.elements.length; i++)
    {
        if(d.elements[i].type == 'checkbox' && d.elements[i].name != 'checkall')
        {
            if (group == 0)
            {
                d.elements[i].checked = setTo;
            }
            else if (d.elements[i].classList.contains(group))
            {
                d.elements[i].checked = setTo;
            }
    }
  }
}

function hesk_attach_disable(ids) {
 for($i=0;$i<ids.length;$i++) {
      if (ids[$i]=='c11'||ids[$i]=='c21'||ids[$i]=='c31'||ids[$i]=='c41'||ids[$i]=='c51') {
            document.getElementById(ids[$i]).checked=false;
      }
      document.getElementById(ids[$i]).disabled=true;
 }
}

function hesk_attach_enable(ids) {
 for($i=0;$i<ids.length;$i++) {
      document.getElementById(ids[$i]).disabled=false;
 }
}

function hesk_attach_handle(el, ids) {
    for($i=0;$i<ids.length;$i++) {
        document.getElementById(ids[$i]).disabled=!el.checked;
    }
}

function hesk_attach_toggle(control,ids) {
 if (document.getElementById(control).checked) {
     hesk_attach_enable(ids);
 } else {
     hesk_attach_disable(ids);
 }
}

function hesk_window(PAGE,HGT,WDT)
{
 var HeskWin = window.open(PAGE,"Hesk_window","height="+HGT+",width="+WDT+",menubar=0,location=0,toolbar=0,status=0,resizable=1,scrollbars=1");
 HeskWin.focus();
}

function hesk_toggleLayerDisplay(nr, displayType = 'block') {
    if (document.all)
            document.all[nr].style.display = (document.all[nr].style.display == 'none') ? displayType : 'none';
    else if (document.getElementById)
            document.getElementById(nr).style.display = (document.getElementById(nr).style.display == 'none') ? displayType : 'none';
}

function hesk_confirmExecute(myText) {
         if (confirm(myText))
         {
          return true;
         }
         return false;
}

function hesk_deleteIfSelected(myField,myText) {
         if(document.getElementById(myField).checked)
         {
          return hesk_confirmExecute(myText);
         }
}

function hesk_rate(url,element_id)
{
        if (url.length==0)
        {
                return false;
        }

        var element = document.getElementById(element_id);

        xmlHttp=GetXmlHttpObject();
        if (xmlHttp==null)
        {
                alert ("Your browser does not support AJAX!");
                return;
        }

        xmlHttp.open("GET",url,true);

        xmlHttp.onreadystatechange = function()
        {
         if (xmlHttp.readyState == 4 && xmlHttp.status == 200)
         {
          element.innerHTML = xmlHttp.responseText;
         }
        }

        xmlHttp.send(null);
}

function stateChanged()
{
        if (xmlHttp.readyState==4)
        {
                document.getElementById("rating").innerHTML=xmlHttp.responseText;
        }
}

function GetXmlHttpObject()
{
        var xmlHttp=null;
        try
        {
                // Firefox, Opera 8.0+, Safari
                xmlHttp=new XMLHttpRequest();
        }
        catch (e)
        {
                // Internet Explorer
                try
                {
                        xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
                }
                catch (e)
                {
                        xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
                }
        }
        return xmlHttp;
}

var heskKBquery = '';
var heskKBfailed = false;

function hesk_suggestKB()
{
 var d = document.form1;
 var s = d.subject.value;
 var m = d.message.value;
 var element = document.getElementById('kb_suggestions');

 if (s != '' && m != '' && (heskKBquery != s + " " + m || heskKBfailed == true) )
 {
  element.style.display = 'block';
  var params = "p=1&" + "q=" + encodeURIComponent( s + " " + m );
  heskKBquery = s + " " + m;

   xmlHttp=GetXmlHttpObject();
   if (xmlHttp==null)
   {
     return;
   }

   xmlHttp.open('POST','suggest_articles.php',true);
   xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

   xmlHttp.onreadystatechange = function()
   {
      if (xmlHttp.readyState == 4 && xmlHttp.status == 200)
      {
       element.innerHTML = xmlHttp.responseText;
       heskKBfailed = false;
      }
      else
      {
       heskKBfailed = true;
      }
   }

   xmlHttp.send(params);
 }

 setTimeout(function() { hesk_suggestKB(); }, 2000);

}

function hesk_suggestKBsearch(isAdmin)
{
 var d = document.searchform;
 var s = d.search.value;
 var element = document.getElementById('kb_suggestions');

 if (isAdmin)
 {
  var path = 'admin_suggest_articles.php';
 }
 else
 {
  var path = 'suggest_articles.php';
 }

 if (s != '' && (heskKBquery != s || heskKBfailed == true) )
 {
  element.style.display = 'block';
  var params = "q=" + encodeURIComponent( s );
  heskKBquery = s;

   xmlHttp=GetXmlHttpObject();
   if (xmlHttp==null)
   {
     return;
   }

   xmlHttp.open('POST', path, true);
   xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

   xmlHttp.onreadystatechange = function()
   {
      if (xmlHttp.readyState == 4 && xmlHttp.status == 200)
      {
       element.innerHTML = unescape(xmlHttp.responseText);
       heskKBfailed = false;
      }
      else
      {
       heskKBfailed = true;
      }
   }

   xmlHttp.send(params);
 }

 setTimeout(function() { hesk_suggestKBsearch(isAdmin); }, 2000);
}

function hesk_suggestEmail(emailField, displayDiv, padDiv, isAdmin, allowMultiple)
{
 var email = document.getElementById(emailField).value;
 var element = document.getElementById(displayDiv);

 if (isAdmin)
 {
  var path = '../suggest_email.php';
 }
 else
 {
  var path = 'suggest_email.php';
 }

 if (email != '')
 {
  var params = "e=" + encodeURIComponent(email) + "&ef=" + encodeURIComponent(emailField) + "&dd=" + encodeURIComponent(displayDiv) + "&pd=" + encodeURIComponent(padDiv);

  if (allowMultiple)
  {
   params += "&am=1";
  }

   xmlHttp=GetXmlHttpObject();
   if (xmlHttp==null)
   {
     return;
   }

   xmlHttp.open('POST', path, true);
   xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

   xmlHttp.onreadystatechange = function()
   {
       if (xmlHttp.readyState === 4 && xmlHttp.status === 200)
       {
           element.innerHTML = '';
        var suggestFormat = '' +
              '<div class="notification-flash service-message orange" id="{0}" style="display: block; margin-bottom: 10px;">' +
                '<div class="notification--title">{1}</div>' +
                '<div class="notification--text">' +
                    '<a class="link" href="javascript:" onclick="hesk_applyEmailSuggestion(\'{0}\', \'' + emailField + '\', \'{2}\', \'{3}\')">' +
                        '{4}' +
                    '</a> | ' +
                    '<a class="link" href="javascript:void(0);" onclick="document.getElementById(\'{0}\').style.display=\'none\';">' +
                        '{5}' +
                    '</a>' +
                '</div>' +
              '</div>';
          var response = JSON.parse(xmlHttp.responseText);
          for (var i = 0; i < response.length; i++) {
              var suggestion = response[i];
              element.innerHTML += suggestFormat.replace(/\{0}/g, suggestion.id)
                  .replace(/\{1}/g, suggestion.suggestText)
                  .replace(/\{2}/g, suggestion.originalAddress)
                  .replace(/\{3}/g, suggestion.formattedSuggestedEmail)
                  .replace(/\{4}/g, suggestion.yesResponseText)
                  .replace(/\{5}/g, suggestion.noResponseText);
              console.log(response[i]);
          }
        element.style.display = 'block';
       }
   }

   xmlHttp.send(params);
 }
}

function hesk_applyEmailSuggestion(emailTypoId, emailField, originalEmail, formattedSuggestedEmail) {
    var eml = document.getElementById(emailField).value;
    var regex = new RegExp(originalEmail, "gi");
    document.getElementById(emailField).value = eml.replace(regex, formattedSuggestedEmail);
    document.getElementById(emailTypoId).style.display = 'none';
}

function hesk_btn(Elem, myClass)
{
        Elem.className = myClass;
}

function hesk_checkPassword(password, fieldID = "progressBar")
{

    var numbers = "0123456789";
    var lowercase = "abcdefghijklmnopqrstuvwxyz";
    var uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    var punctuation = "!.@$#*()%~<>{}[]";

    var combinations = 0;
    var maxScoreCap = 0;

    if (hesk_contains(password, numbers) > 0) {
        combinations += 10;
        maxScoreCap += 25;
    }

    if (hesk_contains(password, lowercase) > 0) {
        combinations += 26;
        maxScoreCap += 25;
    }

    if (hesk_contains(password, uppercase) > 0) {
        combinations += 26;
        maxScoreCap += 25;
    }

    if (hesk_contains(password, punctuation) > 0) {
        combinations += punctuation.length;
        maxScoreCap += 25;
    }

    var totalCombinations = Math.pow(combinations, password.length);
    var timeInSeconds = (totalCombinations / 200) / 2;
    var timeInDays = timeInSeconds / 86400
    var lifetime = 365000;
    var percentage = timeInDays / lifetime;

    var friendlyPercentage = hesk_cap(Math.round(percentage * 100), maxScoreCap);

    if (friendlyPercentage < (password.length * 5)) {
        friendlyPercentage += password.length * 5;
    }

    var friendlyPercentage = hesk_cap(friendlyPercentage, maxScoreCap);

    var progressBar = document.getElementById(fieldID);
    progressBar.style.width = friendlyPercentage + "%";

    if (percentage > 1 && friendlyPercentage > 75) {
        // strong password
        progressBar.style.backgroundColor = "#3bce08";
        return;
    }

    if (percentage > 0.5 && friendlyPercentage > 50) {
        // reasonable password
        progressBar.style.backgroundColor = "#ffd801";
        return;
    }

    if (percentage > 0.10 && friendlyPercentage > 10) {
        // weak password
        progressBar.style.backgroundColor = "orange";
        return;
    }

    if (percentage <= 0.10 || friendlyPercentage <= 10) {
        // very weak password
        progressBar.style.backgroundColor = "red";
        return;
    }

}

function hesk_cap(number, max) {
    if (number > max) {
        return max;
    } else {
        return number;
    }
}

function hesk_contains(password, validChars) {

    count = 0;

    for (i = 0; i < password.length; i++) {
        var char = password.charAt(i);
        if (validChars.indexOf(char) > -1) {
            count++;
        }
    }

    return count;
}

function setCookie(name, value, expires, path, domain, secure)
{
        document.cookie= name + "=" + escape(value) +
                ((expires) ? "; expires=" + expires.toGMTString() : "") +
                ((path) ? "; path=" + path : "") +
                ((domain) ? "; domain=" + domain : "") +
                ((secure) ? "; secure" : "");
}

function getCookie(name)
{
        var dc = document.cookie;
        var prefix = name + "=";
        var begin = dc.indexOf("; " + prefix);
        if (begin == -1) {
                begin = dc.indexOf(prefix);
                if (begin != 0) return null;
        } else {
                begin += 2;
        }
        var end = document.cookie.indexOf(";", begin);
        if (end == -1) {
                end = dc.length;
        }
        return unescape(dc.substring(begin + prefix.length, end));
}

function deleteCookie(name, path, domain)
{
        if (getCookie(name)) {
                document.cookie = name + "=" +
                        ((path) ? "; path=" + path : "") +
                        ((domain) ? "; domain=" + domain : "") +
                        "; expires=Thu, 01-Jan-70 00:00:01 GMT";
        }
}

// Dropzone
function outputAttachmentIdHolder(value, id) {
    $('#attachment-holder-' + id).append('<input type="hidden" name="attachments[]" value="' + value + '">');
}

function removeAttachment(id, fileKey, isAdmin) {
    if (fileKey === undefined) {
        return;
    }

    var prefix = isAdmin ? '../' : '';
    $('input[name="attachments[]"][value="' + fileKey + '"]').remove();
    $.ajax({
        url: prefix + 'upload_attachment.php?action=delete&fileKey=' + encodeURIComponent(fileKey),
        method: 'GET'
    });
}

function hesk_updateDeleteCategoryUrl(modalIndex) {
    var selectedCategory = $('#targetCat' + modalIndex).val();
    var confirmButton = $('a[data-confirm-button]');
    var existingLink = confirmButton[modalIndex];
    var regex = /&targetCategory=\d+/i;
    existingLink.href = existingLink.href.replace(regex, '&targetCategory=' + selectedCategory);
}

jQuery.fn.preventDoubleSubmission = function() {
  $(this).on('submit',function(e){
    var $form = $(this);

    if ($form.data('submitted') === true) {
      // Previously submitted - don't submit again
      e.preventDefault();
    } else {
      // Mark it so that the next submit can be ignored
      $form.data('submitted', true);
    }
  });

  // Keep chainability
  return this;
};

function hesk_loadNoResultsSelectizePlugin(noResultsFoundText) {
    /*
          https://github.com/brianreavis/selectize.js/issues/470
          Selectize doesn't display anything to let the user know there are no results.
          This plugin allows us to render a no results message when there are no
          results are found to select for.
        */

    Selectize.define('no_results', function(options) {
        var self = this;

        options = $.extend({
            message: noResultsFoundText,

            html: function(data) {
                return (
                    '<div class="selectize-dropdown ' + data.classNames + '">' +
                    '<div class="selectize-dropdown-content">' +
                    '<div class="no-results">' + data.message + '</div>' +
                    '</div>' +
                    '</div>'
                );
            }
        }, options );

        self.displayEmptyResultsMessage = function () {
            this.$empty_results_container.css('top', this.$control.outerHeight());
            this.$empty_results_container.css('width', this.$control.outerWidth());
            this.$empty_results_container.show();
            this.$control.addClass("dropdown-active");
        };

        self.refreshOptions = (function () {
            var original = self.refreshOptions;

            return function () {
                original.apply(self, arguments);
                if (this.hasOptions || !this.lastQuery) {
                    this.$empty_results_container.hide()
                } else {
                    this.displayEmptyResultsMessage();
                }
            }
        })();

        self.onKeyDown = (function () {
            var original = self.onKeyDown;

            return function ( e ) {
                original.apply( self, arguments );
                if ( e.keyCode === 27 ) {
                    this.$empty_results_container.hide();
                }
            }
        })();

        self.onBlur = (function () {
            var original = self.onBlur;

            return function () {
                original.apply( self, arguments );
                this.$empty_results_container.hide();
                this.$control.removeClass("dropdown-active");
            };
        })();

        self.setup = (function() {
            var original = self.setup;
            return function() {
                original.apply(self, arguments);
                self.$empty_results_container = $(options.html($.extend({
                    classNames: self.$input.attr('class')
                }, options)));
                self.$empty_results_container.insertBefore(self.$dropdown);
                self.$empty_results_container.hide();
            };
        })();
    });
}

/*
    hesk_selectizeAddCustomAddEntryToDropdown USAGE:
    When initializing a selectize instance with:
    $domRef.selectize({ customOptionsHere })

    Simply add the following call with customized behaviour/display for the "Add Entry" button:
    Then deconstruct the function so all behaviours are copied over to the selectize instance.
    $domRef.selectize({
        ...anyCustomOptionsHere,
        ...hesk_selectizeAddCustomAddEntryToDropdown(
            {
                newEntryTextPrefix: 'Custom Prefix to new entry option, i.e. "Add User"',
                onAddEntryClickedFunction: function(selectizeInstance, selectizeSearchValue) {
                    // define any custom logic here that happen when Add Entry is clicked

                    // I.e. call new-customer-modal popup and overwrite it's email/user text etc.
                }
            }
        )
    });
 */
function hesk_selectizeAddCustomAddEntryToDropdown(config) {
    return {
        /*
        Below selectize options are added for adding support of a Sticky "Add Entry" option at the bottom of dropdown.
        Note: Make sure to also remove any create: false, options from above selectize settings as well!
            <-- Correction on 15th August: Actually no longer specifically necessary, as deconstruction of this function will properly override any pre-set create anyway
        */
        newEntryTextPrefix: config.newEntryTextPrefix,
        create: function(input) {
            /* We don't really need this, other than to override default behaviour of showing "Add" option directly at top,
            as we want to customize it and always show it sticky at the bottom instead. But required for everything to work properly.
            */
            return {};
        },
        showAddOptionOnCreate: true,
        onInitialize: function() {
            var self = this;
            // Handling/updating of "Add Entry" option as user types.
            self.on('type', function(input) {
                var $dropdown = $(self.$dropdown);
                var hasCreateOption = self.settings.create;
                if (hasCreateOption) {
                    // Check if "Add Entry" is already in the dropdown, if not add it
                    var $addEntryOption = $dropdown.find('.add-entry-option');
                    if ($addEntryOption.length === 0) {
                        // Nothing needed to be done here, as DOM is already added in onDropdownOpen below - leaving for reference for future understanding
                    } else {
                        // Update the "Add Entry" option text to reflect the current input
                        // when typing and there's input, add an extra ": " at the end for neater formatting
                        $addEntryOption.html(self.settings.newEntryTextPrefix + ': ' + input);

                        // Check if there are other visible options in dropdown:
                        // If yes, add an extra class so we can adjust the styling accordingly (i.e. add an extra top border or not)
                        var visibleOptions = $dropdown.find('.option:not(.add-entry-option):visible').length;

                        // Toggle the with-results-above class if other options exist
                        $addEntryOption.toggleClass('with-results-above', visibleOptions > 0);
                    }
                }
                if (self.currentResults.items.length === 1) {
                    // For some reason, if just one search result, selectize will not properly automatically set is as active (so i.e. using Enter would not work)
                    // This fixes that issue.
                    self.setActiveOption($dropdown.find('[data-selectable'));
                } else if (hasCreateOption && self.currentResults.items.length === 0) {
                    var $addEntryOption = $dropdown.find('.add-entry-option');
                    if ($addEntryOption.length > 0) {
                        self.setActiveOption($addEntryOption);
                    }

                }
            });


            self.$control_input.on('keydown', function(e) {
                var key = e.keyCode || e.which;

                var $dropdown = $(self.$dropdown);
                var hasCreateOption = self.settings.create;
                // If we have a custom "add entry" option, and no other results are shown, we have to make custom handling of pressing Enter key to trigger it.
                if (hasCreateOption) {
                    // Check if Enter is pressed, no results, and dropdown is open
                    if (key === 13 && self.currentResults.items.length === 0 && self.isOpen) {
                        e.preventDefault(); // Prevent default Enter key behavior

                        // Trigger the custom event tied to your add-entry-option
                        var $addEntryOption = $dropdown.find('.add-entry-option');
                        if ($addEntryOption.length > 0) {
                            $addEntryOption.click();
                        }
                        return false;
                    }
                }
            });
        },
        // Handles appending a fixed/sticky "Add Entry" option at the bottom of dropdown, which is visible always
        onDropdownOpen: function($dropdown) {
            var self = this;

            // Remove any previous "Add Entry" option to avoid duplicates
            $dropdown.find('.add-entry-option').remove();

            // Check if there are other visible options
            var visibleOptions = $dropdown.find('.option:not(.add-entry-option):visible').length;

            // Append a static "Add Entry" option to the dropdown
            var $addEntryOption = $('<div class="option add-entry-option">' + self.settings.newEntryTextPrefix + '</div>');

            // Toggle add the with-results-above class if other options exist
            $addEntryOption.toggleClass('with-results-above', visibleOptions > 0);

            // Add a click handler to the "Add Entry" option
            $addEntryOption.on('click', function() {
                // You can trigger any custom logic here when "Add Entry" is clicked
                let selectizeSearchValue = self.$control_input.val();

                /* quick simple test of triggering the modal popup for new customer (NOTE: assumes that new-customer-link IS existing & visible)
                If we'll want to remove it, will need to somehow override/extend how to open modals
                */

                config.onAddEntryClickedFunction(self, selectizeSearchValue);

                // Finally close the dropdown
                self.close();
            });

            // Append the "Add Entry" option to the dropdown
            $dropdown.append($addEntryOption);
        },
        render: {
            option_create: function(data, escape) {
                // render nothing, as it's already rendered by a static option always in the bottom inside onDropdownOpen.
                // BUT, it still requires create() to be set above, as otherwise it wouldn't show a dropdown properly
                return '';
            }
        }
    }
}

// https://stackoverflow.com/a/24004942
function hesk_debounce(func, wait, immediate) {
    // 'private' variable for instance
    // The returned function will be able to reference this due to closure.
    // Each call to the returned function will share this common timer.
    var timeout;

    // Calling debounce returns a new anonymous function
    return function() {
        // reference the context and args for the setTimeout function
        var context = this,
            args = arguments;

        // Should the function be called now? If immediate is true
        //   and not already in a timeout then the answer is: Yes
        var callNow = immediate && !timeout;

        // This is the basic debounce behaviour where you can call this
        //   function several times, but it will only execute once
        //   (before or after imposing a delay).
        //   Each time the returned function is called, the timer starts over.
        clearTimeout(timeout);

        // Set the new timeout
        timeout = setTimeout(function() {

            // Inside the timeout function, clear the timeout variable
            // which will let the next execution run when in 'immediate' mode
            timeout = null;

            // Check if the function already ran with the immediate flag
            if (!immediate) {
                // Call the original function with apply
                // apply lets you define the 'this' object as well as the arguments
                //    (both captured before setTimeout)
                func.apply(context, args);
            }
        }, wait);

        // Immediate mode and no wait timer? Execute the function...
        if (callNow) func.apply(context, args);
    }
}

function hesk_showLoadingMessage(disableButtonID) {
    document.getElementById('loading-overlay').style.display = 'block';
    document.getElementById(disableButtonID).disabled = true;
}

function hesk_toggleShowPassword(id) {
    var x = document.getElementById(id);
    if (x.type === "password") {
        x.type = "text";
    } else {
        x.type = "password";
    }
}