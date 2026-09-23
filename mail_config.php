<?php
function configure_qm_mailer($mail) {
    $smtp_host = getenv('QM_SMTP_HOST');
    $smtp_port = getenv('QM_SMTP_PORT');
    $smtp_username = getenv('QM_SMTP_USERNAME');
    $smtp_password = getenv('QM_SMTP_PASSWORD');
    $smtp_from = getenv('QM_SMTP_FROM') ?: $smtp_username;
    $smtp_from_name = getenv('QM_SMTP_FROM_NAME') ?: 'Online QM';

    if (!$smtp_host || !$smtp_port || !$smtp_username || !$smtp_password || !$smtp_from) {
        throw new RuntimeException('Email service is not configured.');
    }

    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_username;
    $mail->Password = $smtp_password;
    $mail->SMTPSecure = 'tls';
    $mail->Port = (int) $smtp_port;
    $mail->setFrom($smtp_from, $smtp_from_name);
}