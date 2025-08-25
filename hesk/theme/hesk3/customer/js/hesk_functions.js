var HESK_FUNCTIONS;
if (!HESK_FUNCTIONS) {
    HESK_FUNCTIONS = {};
}

var heskKBfailed = false;
var heskKBquery = '';
HESK_FUNCTIONS.getKbSearchSuggestions = function($input, callback) {
    var d = document.form1;
    var s = $input.val();

    if (s !== '' && (heskKBquery !== s || heskKBfailed === true) )
    {
        var params = "q=" + encodeURIComponent(s);
        heskKBquery = s;

        $.ajax({
            url: 'suggest_articles.php',
            method: 'POST',
            dataType: 'json',
            contentType: 'application/x-www-form-urlencoded',
            data: params,
            success: function(data) {
                heskKBfailed = false;
                callback(data);
            },
            error: function(jqXHR, status, err) {
                console.error(err);
                heskKBfailed = true;
            }
        });
    }

    setTimeout(function() { HESK_FUNCTIONS.getKbSearchSuggestions($input, callback); }, 2000);
};

HESK_FUNCTIONS.getKbTicketSuggestions = function($subject, $message, callback) {
    var d = document.form1;
    var s = $subject.val();
    var m = $message.val();
    var query = s + " " + m;

    if (s !== '' && m !== '' && (heskKBquery !== query || heskKBfailed === true) )
    {
        var params = "q=" + encodeURIComponent(query);
        heskKBquery = query;

        $.ajax({
            url: 'suggest_articles.php',
            method: 'POST',
            dataType: 'json',
            contentType: 'application/x-www-form-urlencoded',
            data: params,
            success: function(data) {
                heskKBfailed = false;
                callback(data);
            },
            error: function(jqXHR, status, err) {
                console.error(err);
                heskKBfailed = true;
            }
        });
    }

    setTimeout(function() { HESK_FUNCTIONS.getKbTicketSuggestions($subject, $message, callback); }, 2000);
};

HESK_FUNCTIONS.openWindow = function(PAGE,HGT,WDT) {
    var heskWin = window.open(PAGE,"Hesk_window","height="+HGT+",width="+WDT+",menubar=0,location=0,toolbar=0,status=0,resizable=1,scrollbars=1");
    heskWin.focus();
};

HESK_FUNCTIONS.suggestEmail = function(emailField, displayDiv, isAdmin, allowMultiple) {
    var email = document.getElementById(emailField).value;
    var element = document.getElementById(displayDiv);
    var path = isAdmin ? '../suggest_email.php' : 'suggest_email.php';

    if (email !== '') {
        var params = "e=" + encodeURIComponent(email) + "&ef=" + encodeURIComponent(emailField) + "&dd=" + encodeURIComponent(displayDiv);

        if (allowMultiple) {
            params += "&am=1";
        }


        /*
        {0}: Div ID
        {1}: Suggestion message (i.e. "Did you mean hesk@example.com?")
        {2}: Original email
        {3}: Suggested email (pre-escaped)
        {4}: "Yes, fix it"
        {5}: "No, leave it"
         */
        var responseFormat =
            '<div class="alert warning" id="{0}" style="display: block">' +
                '<div class="alert__inner">' +
                    '<p>' +
                        '<p>{1}</p>' +
                        '<a class="link" href="javascript:" onclick="HESK_FUNCTIONS.applyEmailSuggestion(\'{0}\', \'' + emailField + '\', \'{2}\', \'{3}\')">' +
                            '{4}' +
                        '</a> | ' +
                        '<a class="link" href="javascript:void(0);" onclick="document.getElementById(\'{0}\').style.display=\'none\';">' +
                            '{5}' +
                        '</a>' +
                    '</p>' +
                '</div>' +
            '</div>';

        $.ajax({
            url: path,
            method: 'POST',
            dataType: 'json',
            contentType: 'application/x-www-form-urlencoded',
            data: params,
            success: function(data) {
                var $displayDiv = $('#' + displayDiv);
                $displayDiv.html('');
                if (!data.length) {
                    $displayDiv.hide();
                } else {
                    $displayDiv.show();
                }
                $.each(data, function() {
                    $displayDiv.append(responseFormat
                        .replace(/\{0}/g, this.id)
                        .replace(/\{1}/g, this.suggestText)
                        .replace(/\{2}/g, this.originalAddress)
                        .replace(/\{3}/g, this.formattedSuggestedEmail)
                        .replace(/\{4}/g, this.yesResponseText)
                        .replace(/\{5}/g, this.noResponseText));
                });
            },
            error: function(jqXHR, status, err) {
                console.error(err);
            }
        });
    }
};

HESK_FUNCTIONS.applyEmailSuggestion = function(emailTypoId, emailField, originalEmail, formattedSuggestedEmail) {
    var eml = document.getElementById(emailField).value;
    var regex = new RegExp(originalEmail, "gi");
    document.getElementById(emailField).value = eml.replace(regex, formattedSuggestedEmail);
    document.getElementById(emailTypoId).style.display = 'none';
};

HESK_FUNCTIONS.rate = function(url, elementId) {
    if (url.length === 0) {
        return false;
    }

    var element = document.getElementById(elementId);

    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'text',
        success: function(resp) {
            element.innerHTML = resp;
        },
        error: function(jqXHR, statusText, err) {
            console.error(err);
        }
    });
}

HESK_FUNCTIONS.checkPasswordStrength = function(password, fieldId = 'progressBar') {
    var numbers = "0123456789";
    var lowercase = "abcdefghijklmnopqrstuvwxyz";
    var uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    var punctuation = "!.@$#*()%~<>{}[]";

    var combinations = 0;
    var maxScoreCap = 0;

    if (HESK_FUNCTIONS._contains(password, numbers) > 0) {
        combinations += 10;
        maxScoreCap += 25;
    }

    if (HESK_FUNCTIONS._contains(password, lowercase) > 0) {
        combinations += 26;
        maxScoreCap += 25;
    }

    if (HESK_FUNCTIONS._contains(password, uppercase) > 0) {
        combinations += 26;
        maxScoreCap += 25;
    }

    if (HESK_FUNCTIONS._contains(password, punctuation) > 0) {
        combinations += punctuation.length;
        maxScoreCap += 25;
    }

    var totalCombinations = Math.pow(combinations, password.length);
    var timeInSeconds = (totalCombinations / 200) / 2;
    var timeInDays = timeInSeconds / 86400
    var lifetime = 365000;
    var percentage = timeInDays / lifetime;

    var friendlyPercentage = Math.min(Math.round(percentage * 100), maxScoreCap);

    if (friendlyPercentage < (password.length * 5)) {
        friendlyPercentage += password.length * 5;
    }

    friendlyPercentage = Math.min(friendlyPercentage, maxScoreCap);

    var progressBar = document.getElementById(fieldId);
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

HESK_FUNCTIONS._contains = function(password, validChars) {

    count = 0;

    for (i = 0; i < password.length; i++) {
        var char = password.charAt(i);
        if (validChars.indexOf(char) > -1) {
            count++;
        }
    }

    return count;
}

HESK_FUNCTIONS.toggleLayerDisplay = function(element, displayType = 'block') {
    document.getElementById(element).style.display = (document.getElementById(element).style.display === 'none') ? displayType : 'none';
}

//region Drag/Drop attachments
function outputAttachmentIdHolder(value, id) {
    $('#attachment-holder-' + id).append('<input type="hidden" name="attachments[]" value="' + value + '">');
}

function removeAttachment(id, fileKey, isAdmin) {
    var prefix = isAdmin ? '../' : '';
    $('input[name="attachments[]"][value="' + fileKey + '"]').remove();
    $.ajax({
        url: prefix + 'upload_attachment.php?action=delete&fileKey=' + encodeURIComponent(fileKey),
        method: 'GET'
    });
}

if (typeof originalSubmitText === 'undefined') {
    originalSubmitText = $('button[type=\"submit\"]').text();
}
let attachmentQueueDelayedTimeout = null
if (typeof pleaseWaitMessage === 'undefined') {
    pleaseWaitMessage = 'Please Wait...'; // fallback if not defined
}
function attachmentQueueComplete() {
    clearTimeout(attachmentQueueDelayedTimeout); // make sure to cancel delayed timeout if it was set, as no longer needed
    $('button[type=\"submit\"]').attr('disabled', false).text(originalSubmitText);
}
function attachmentQueueProcessing() {
    // store copy of original submit text, in case it gets changed
    originalSubmitText = $('button[type=\"submit\"]').text();
    $('button[type=\"submit\"]').attr('disabled', true);

    // Change main form submit button to "please wait" if the uplaod takes more than a second
    clearTimeout(attachmentQueueDelayedTimeout); // make sure to clear itmeout, to prevent multiple fires
    attachmentQueueDelayedTimeout = setTimeout(setPleaseWaitText, 1000);
}
function attachmentError() {
    // does the same as complete (it already should do the same, but secondary safety measure, just in case)
    attachmentQueueComplete();
}
function setPleaseWaitText() {
    $('button[type=\"submit\"]').text(pleaseWaitMessage);
}
//endregion

//region jQuery
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
//endregion

function hesk_showLoadingMessage(formID) {
    document.getElementById('loading-overlay').style.display = 'block';
    document.getElementById(formID).disabled = true;
}

// start selectize no results plugins */
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
// end selectize no results plugins */