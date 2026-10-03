<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Admin proxy upload — renders the very same form authors use, with the
 * administrator extras (author account, size-limit waiver).
 */
echo \Athenaeum\Core\View::render('papers/form', get_defined_vars(), null);
