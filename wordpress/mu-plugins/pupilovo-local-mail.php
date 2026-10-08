<?php
/**
 * Local-only SMTP capture. No credentials and no external delivery.
 */
if (!defined('ABSPATH')) { exit; }
if (getenv('PUPILOVO_LOCAL_SMTP_HOST')) {
    add_filter('wp_mail_from', static fn() => 'pupilovo-test@example.invalid');
    add_filter('wp_mail_from_name', static fn() => 'Pupilovo (TEST)');
}
add_action('phpmailer_init', static function ($mailer) {
    $host = getenv('PUPILOVO_LOCAL_SMTP_HOST');
    if (!$host) { return; }
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->Port = (int) (getenv('PUPILOVO_LOCAL_SMTP_PORT') ?: 1025);
    $mailer->SMTPAuth = false;
    $mailer->SMTPSecure = '';
    $mailer->SMTPAutoTLS = false;
    $mailer->From = 'pupilovo-test@example.invalid';
    $mailer->FromName = 'Pupilovo (TEST)';
});
