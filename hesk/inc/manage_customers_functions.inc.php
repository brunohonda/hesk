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


function hesk_customer_tab($session_array='new', $customer_verified = false)
{
	global $hesk_settings, $hesklang;

	$errors = hesk_SESSION(array($session_array, 'errors'));
	$errors = is_array($errors) ? $errors : array();
	?>
    <div class="step-slider">
        <div class="step-item step-1">
            <div class="form-group">
                <label for="prof_name"><?php echo $hesklang['name']; ?></label>
                <input type="text" class="form-control <?php echo in_array('name', $errors) ? 'isError' : ''; ?>" id="prof_name" name="name" maxlength="50"
                       value="<?php echo $_SESSION[$session_array]['name']; ?>">
            </div>
            <div class="form-group">
                <label for="prof_email"><?php echo $hesklang['email']; ?></label>
                <input type="text" class="form-control <?php echo in_array('email', $errors) ? 'isError' : ''; ?>" name="email" maxlength="255" id="prof_user"
                       value="<?php echo $_SESSION[$session_array]['email']; ?>">
            </div>
            <?php if ($hesk_settings['customer_accounts']): ?>
            <section class="item--section customer-password">
                <h4>
                    <?php echo $hesklang['pass']; ?>
                </h4>
                <div class="optional">
                    (<?php echo $hesklang['optional']; ?>)
                </div>
                <div class="form-group">
                    <label for="prof_newpass"><?php echo (empty($_SESSION[$session_array]['id']) ? $hesklang['pass'] : $hesklang['new_pass']); ?></label>
                    <input type="password" id="prof_newpass" name="newpass" autocomplete="off" class="form-control <?php echo in_array('passwords', $errors) ? 'isError' : ''; ?>"
                           value="<?php echo isset($_SESSION[$session_array]['cleanpass']) ? $_SESSION[$session_array]['cleanpass'] : ''; ?>"
                           onkeyup="hesk_checkPassword(this.value)">
                </div>
                <div class="form-group">
                    <label for="prof_newpass2"><?php echo (empty($_SESSION[$session_array]['id']) ? $hesklang['confirm_pass'] : $hesklang['confirm_new_pass']); ?></label>
                    <input type="password" class="form-control <?php echo in_array('passwords', $errors) ? 'isError' : ''; ?>" id="prof_newpass2" name="newpass2" autocomplete="off"
                           value="<?php echo isset($_SESSION[$session_array]['cleanpass']) ? $_SESSION[$session_array]['cleanpass'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label><?php echo $hesklang['pwdst']; ?></label>
                    <div style="border: 1px solid #d4d6e3; width: 100%; height: 14px">
                        <div id="progressBar" style="font-size: 1px; height: 12px; width: 0px; border: none;">
                        </div>
                    </div>
                </div>
                <?php if (!$customer_verified) {
                    hesk_show_info($hesklang['customer_account_setting_password_will_verify_user']);
                } ?>
            </section>
            <?php endif; ?>
        </div>
    </div>

    <script>
        if (document.form1.newpass) {
            hesk_checkPassword(document.form1.newpass.value);
        }
    </script>
	<?php
} // END hesk_profile_tab()
