<?php

namespace QuietRent\Services;

use QuietRent\Core\Env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        return self::sendMail($to, $subject, $body);
    }

    public static function sendWithPdf(string $to, string $subject, string $body, string $pdfBytes, string $filename): bool
    {
        return self::sendMail($to, $subject, $body, $pdfBytes, $filename);
    }

    private static function sendMail(string $to, string $subject, string $body, ?string $pdfBytes = null, ?string $filename = null): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = Env::get('MAIL_HOST', 'premium313.web-hosting.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = Env::get('MAIL_USERNAME', 'support@getquietnotify.com');
            $mail->Password   = Env::get('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = (int) Env::get('MAIL_PORT', '465');

            $mail->setFrom(
                Env::get('MAIL_FROM', 'support@getquietnotify.com'),
                Env::get('MAIL_FROM_NAME', 'Support at Quiet Rent')
            );
            $mail->addAddress($to);

            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->isHTML(false);

            if ($pdfBytes !== null && $filename !== null) {
                $mail->addStringAttachment($pdfBytes, $filename, 'base64', 'application/pdf');
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer error: ' . $mail->ErrorInfo);
            return false;
        }
    }

}
