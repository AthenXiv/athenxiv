<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Field-level validation errors, if any were flashed.
 * @var string $field
 */
$message = error_for($field);
if ($message === '') {
    return;
}
?>
<p class="field-error" role="alert"><?= e($message) ?></p>
