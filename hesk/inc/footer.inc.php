<?php
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

// Check if this is a valid include
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

// Users online
if (defined('SHOW_ONLINE'))
{
    hesk_printOnline();
}

/*******************************************************************************
The code below handles HESK licensing and must be included in the template.

Removing this code is a direct violation of the HESK End User License Agreement,
will void all support and may result in unexpected behavior.

To purchase a HESK license and support future HESK development please visit:
https://www.hesk.com/buy.php
*******************************************************************************/
$hesk_settings['hesk_license']('Qo8Zm9vdGVyIGNsYXNzPSJmb290ZXIiPg0KICAgIDxwIGNsY
XNzPSJ0ZXh0LWNlbnRlciI+UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3d3dy5oZXNrLmNvbSIgY
2xhc3M9ImxpbmsiPkhlbHAgRGVzayBTb2Z0d2FyZTwvYT4gPHNwYW4gY2xhc3M9ImZvbnQtd2VpZ2h0L
WJvbGQiPkhFU0s8L3NwYW4+PGJyPk1vcmUgSVQgZmlyZXBvd2VyPyBUcnkgPGEgaHJlZj0iaHR0cHM6L
y93d3cuc3lzYWlkLmNvbS8/dXRtX3NvdXJjZT1IZXNrJmFtcDt1dG1fbWVkaXVtPWNwYyZhbXA7dXRtX
2NhbXBhaWduPUhlc2tQcm9kdWN0X1RvX0hQIiBjbGFzcz0ibGluayI+U3lzQWlkPC9hPjwvcD4NCjwvZ
m9vdGVyPg0K',"\104", "a809404e0adf9823405ee0b536e5701fb7d3c969");
/*******************************************************************************
END LICENSE CODE
*******************************************************************************/
?>
</main> <!-- End main -->
<?php
if (isset($login_wrapper)) {
    echo '</div> <!-- End wrapper login -->';
}
?>
</div> <!-- End wrapper -->
<input type="hidden" name="HESK_PATH" value="<?php echo HESK_PATH; ?>">
<script src="<?php echo HESK_PATH; ?>js/svg4everybody.min.js"></script>
<?php
// Do we need the calendar functions?
if (defined('CALENDAR'))
{
?>
<script src="<?php echo HESK_PATH; ?>js/datepicker.min.js"></script>
<script type="text/javascript">
(function ($) { $.fn.datepicker.language['en'] = {
    days: ['<?php echo $hesklang['d0']; ?>', '<?php echo $hesklang['d1']; ?>', '<?php echo $hesklang['d2']; ?>', '<?php echo $hesklang['d3']; ?>', '<?php echo $hesklang['d4']; ?>', '<?php echo $hesklang['d5']; ?>', '<?php echo $hesklang['d6']; ?>'],
    daysShort: ['<?php echo $hesklang['sun']; ?>', '<?php echo $hesklang['mon']; ?>', '<?php echo $hesklang['tue']; ?>', '<?php echo $hesklang['wed']; ?>', '<?php echo $hesklang['thu']; ?>', '<?php echo $hesklang['fri']; ?>', '<?php echo $hesklang['sat']; ?>'],
    daysMin: ['<?php echo $hesklang['su']; ?>', '<?php echo $hesklang['mo']; ?>', '<?php echo $hesklang['tu']; ?>', '<?php echo $hesklang['we']; ?>', '<?php echo $hesklang['th']; ?>', '<?php echo $hesklang['fr']; ?>', '<?php echo $hesklang['sa']; ?>'],
    months: ['<?php echo $hesklang['m1']; ?>','<?php echo $hesklang['m2']; ?>','<?php echo $hesklang['m3']; ?>','<?php echo $hesklang['m4']; ?>','<?php echo $hesklang['m5']; ?>','<?php echo $hesklang['m6']; ?>', '<?php echo $hesklang['m7']; ?>','<?php echo $hesklang['m8']; ?>','<?php echo $hesklang['m9']; ?>','<?php echo $hesklang['m10']; ?>','<?php echo $hesklang['m11']; ?>','<?php echo $hesklang['m12']; ?>'],
    monthsShort: ['<?php echo $hesklang['ms01']; ?>','<?php echo $hesklang['ms02']; ?>','<?php echo $hesklang['ms03']; ?>','<?php echo $hesklang['ms04']; ?>','<?php echo $hesklang['ms05']; ?>','<?php echo $hesklang['ms06']; ?>', '<?php echo $hesklang['ms07']; ?>','<?php echo $hesklang['ms08']; ?>','<?php echo $hesklang['ms09']; ?>','<?php echo $hesklang['ms10']; ?>','<?php echo $hesklang['ms11']; ?>','<?php echo $hesklang['ms12']; ?>'],
    today: '<?php echo hesk_slashJS($hesklang['r1']); ?>',
    clear: '<?php echo hesk_slashJS($hesklang['clear']); ?>',
    dateFormat: '<?php echo hesk_slashJS($hesk_settings['format_datepicker_js']); ?>',
    timeFormat: '<?php echo hesk_slashJS($hesk_settings['format_time']); ?>',
    firstDay: <?php echo $hesklang['first_day_of_week']; ?>
}; })(jQuery);
</script>
<?php
}
?>

<script type="text/javascript" src="<?php echo HESK_PATH; ?>js/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>

<?php
// Any adjustments to datepicker?
if (isset($hesk_settings['datepicker'])):
    ?>
    <script>

    function convertDateToUTC(date) { return new Date(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), date.getUTCHours(), date.getUTCMinutes(), date.getUTCSeconds()); }

    $(document).ready(function () {
        const myDP = {};
        <?php
        foreach ($hesk_settings['datepicker'] as $selector => $data) {
            echo "
                myDP['{$selector}'] = $('{$selector}').datepicker(".((isset($data['position']) && is_string($data['position'])) ? "{position: '{$data['position']}'}" : "").");
            ";
            if (isset($data['timestamp']) && ($ts = intval($data['timestamp']))) {
                if ( ! empty($hesk_settings['datepicker'][$selector]['fromDB'])) {
                    echo "myDP['{$selector}'].data('datepicker').selectDate(convertDateToUTC(new Date({$ts} * 1000)));";
                } else {
                    echo "myDP['{$selector}'].data('datepicker').selectDate(new Date({$ts} * 1000));";
                }
            }
        }
        ?>
        $('.showme').click(function (e) {
            $(this).addClass('active');
            $(this).parent()
                .find('.datepicker')
                .data("datepicker")
                .show();
        });
    });
    </script>
    <?php
endif;

// Auto-select first empty or error field on non-staff pages?
if (defined('AUTOFOCUS'))
{
?>
<script language="javascript">
(function(){
	var forms = document.forms || [];
	for(var i = 0; i < forms.length; i++)
    {
		for(var j = 0; j < forms[i].length; j++)
        {
			if(
				!forms[i][j].readonly != undefined &&
				forms[i][j].type != "hidden" &&
				forms[i][j].disabled != true &&
				forms[i][j].style.display != 'none' &&
				(forms[i][j].className == 'isError' || forms[i][j].className == 'isNotice' || forms[i][j].value == '')
			)
	        {
				forms[i][j].focus();
				return;
			}
		}
	}
})();
</script>
<?php
}

// Apply status coloring to drop-down box; needs to be called after app.js
if (isset($hesk_settings['print_status_select_box_jquery']))
{
    hesk_print_status_select_box_jquery();
}

echo '
</body>
</html>
';

$hesk_settings['security_cleanup']('exit');
