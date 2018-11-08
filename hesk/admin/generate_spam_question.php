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

define('IN_SCRIPT',1);
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/setup_functions.inc.php');

$spam_question = hesk_generate_SPAM_question();

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header('Content-type: text/plain; charset=utf-8');
?>
<a href="Javascript:void(0)" onclick="Javascript:hesk_rate('generate_spam_question.php','question')"><?php echo $hesklang['genq']; ?></a><br />

<?php echo $hesklang['q_q']; ?>:<br />
<textarea name="s_question_ask" rows="3" cols="40"><?php echo addslashes(hesk_htmlspecialchars($spam_question[0])); ?></textarea><br />

<?php echo $hesklang['q_a']; ?>:<br />
<input type="text" name="s_question_ans" value="<?php echo addslashes(hesk_htmlspecialchars($spam_question[1])); ?>" size="10" />
<?php
exit();
?>
