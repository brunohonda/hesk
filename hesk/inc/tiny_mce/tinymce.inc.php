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

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}


function hesk_tinymce_init($selector='#message', $onKeyUpFunction = '', $onKeyUpTimeout = 3000)
{
    global $hesklang;
    ?>
    <script>
        <?php if ($onKeyUpFunction) {
            $onKeyUpTimer = $onKeyUpFunction . '_Timer';
            echo "var {$onKeyUpTimer};";
        }
        ?>

        tinymce.init({
            selector: '<?php echo $selector; ?>',
            convert_urls: false,
            branding: false,
            promotion: false,
            browser_spellcheck: true,
            contextmenu: 'link useBrowserSpellcheck image table',
            setup: function (editor) {

                <?php if ($onKeyUpFunction): ?>
                editor.on('KeyUp', function (e) {
                    clearTimeout(<?php echo $onKeyUpTimer; ?>);
                    <?php echo $onKeyUpTimer; ?> = setTimeout(<?php echo $onKeyUpFunction; ?>, <?php echo $onKeyUpTimeout; ?>);
                }),
                editor.on('submit', function (e) {
                    clearTimeout(<?php echo $onKeyUpTimer; ?>);
                }),
                <?php endif; ?>

                editor.ui.registry.addMenuItem('useBrowserSpellcheck', {
                text: '<?php echo hesk_slashJS($hesklang['tmce1']); ?>',
                onAction: function () {
                  editor.notificationManager.open({
                    text: '<?php echo hesk_slashJS($hesklang['tmce2']); ?>',
                    type: 'info',
                    timeout: 5000,
                    closeButton: true
                  });
                }
              });

              editor.ui.registry.addContextMenu('useBrowserSpellcheck', {
                update: function (node) {
                  return editor.selection.isCollapsed() ? ['useBrowserSpellcheck'] : [];
                }
              });
            },
            toolbar: 'undo redo | styleselect fontselect fontsizeselect | bold italic underline | alignleft aligncenter alignright alignjustify | forecolor backcolor | bullist numlist outdent indent | link unlink image codesample code',
            plugins: 'charmap code codesample image link lists table autolink',
            height: 350,
            toolbar_mode: 'sliding',
            mobile: {
                toolbar_mode: 'scrolling',
                height: 300
            },
            images_dataimg_filter: function(img) {
                return img.hasAttribute('internal-blob');
            }
        });
    </script>
    <?php
} // END hesk_tinymce_init()
