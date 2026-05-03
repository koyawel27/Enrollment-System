<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/mail_config.php';

require_once BASE_PATH . '/PHPMailer/PHPMailer-master/src/PHPMailer.php';
require_once BASE_PATH . '/PHPMailer/PHPMailer-master/src/SMTP.php';
require_once BASE_PATH . '/PHPMailer/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private const BRAND_COLOR = '#2e7d32';
    private const FOOTER_TEXT = 'This is an automated message from BPC iEnroll. Do not reply to this email.';

    // Status accent colors — match dashboard badge hex values
    private const COLOR_GREEN  = '#198754'; // verified, passed, admitted, TESDA accepted
    private const COLOR_RED    = '#dc3545'; // rejected, failed, no show
    private const COLOR_BLUE   = '#1565c0'; // exam scheduled 
    private const COLOR_ORANGE = '#fd7e14'; // under review
    private const COLOR_PURPLE = '#6f42c1'; // re-submitted, interview scheduled
    private const COLOR_AMBER  = '#f9a825'; // awaiting decision, TESDA offer
    private const COLOR_TEAL   = '#0b7285'; // interview completed
    private const COLOR_GRAY   = '#6c757d'; // withdrawn, neutral
    private const COLOR_GOLD   = '#e6a510'; // application submitted

    // ── OTP ───────────────────────────────────────────────────

    public function sendOtpEmail(string $to, string $otp): bool
    {
        $subject = 'BPC iEnroll: Your Email Verification Code';
        $html = $this->buildHtmlTemplate(
            'Applicant',
            'Email Verification',
            "Thank you for registering with BPC iEnroll.<br><br>"
          . "Your one-time verification code is:<br><br>"
          . "<div style=\"text-align:center;margin:24px 0;\">"
          . "<span style=\"font-size:36px;font-weight:900;letter-spacing:12px;"
          . "color:" . self::BRAND_COLOR . ";background:#f0fdf4;"
          . "padding:16px 24px;border-radius:10px;display:inline-block;\">"
          . $this->e($otp)
          . "</span></div>"
          . "This code expires in <strong>10 minutes</strong>.<br><br>"
          . "If you did not register for BPC iEnroll, you can safely ignore this email.",
            self::COLOR_BLUE
        );
        $text = "Your BPC iEnroll verification code is: {$otp}\n\n"
              . "This code expires in 10 minutes.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, 'Applicant', $subject, $html, $text);
    }
    // ── Password Reset ────────────────────────────────────────

    public function sendPasswordResetEmail(string $to, string $name, string $resetLink): bool
    {
        $subject = 'BPC iEnroll: Password Reset Request';
        $html = $this->buildHtmlTemplate(
            $name,
            'Password Reset Request',
            "We received a request to reset your BPC iEnroll account password.<br><br>"
          . "Click the button below to set a new password. "
          . "This link expires in <strong>30 minutes</strong>.<br><br>"
          . "<div style=\"text-align:center;margin:24px 0;\">"
          . "<a href=\"{$this->e($resetLink)}\" "
          . "style=\"background:#006400;color:#ffffff;padding:12px 28px;"
          . "border-radius:6px;text-decoration:none;font-weight:700;"
          . "font-size:15px;display:inline-block;\">Reset My Password</a></div>"
          . "If the button doesn't work, copy and paste this link into your browser:<br>"
          . "<span style=\"font-size:12px;color:#666;word-break:break-all;\">{$this->e($resetLink)}</span><br><br>"
          . "If you did not request a password reset, you can safely ignore this email. "
          . "Your password will not change.",
            self::COLOR_GRAY
        );
        $text = "Hello {$name},\n\n"
              . "Reset your BPC iEnroll password using this link:\n{$resetLink}\n\n"
              . "This link expires in 30 minutes.\n\n"
              . "If you did not request this, ignore this email.\n\n"
              . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    // ── Student emails ────────────────────────────────────────

    public function sendApplicationSubmittedEmail($to, $name, $referenceNumber): bool
    {
        $subject = 'BPC iEnroll: Application Submitted Successfully';
        $html = $this->buildHtmlTemplate(
            $name, 'Application Received',
            "Your application has been successfully submitted.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br><br>"
          . "Please keep this reference number for future tracking.",
            '#0d6efd'
        );
        $text = "Hello {$name},\n\nYour application has been submitted.\nReference Number: {$referenceNumber}\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendDocumentsVerifiedEmail($to, $name, $referenceNumber): bool
    {
        $subject = 'BPC iEnroll: Documents Verified';
        $html = $this->buildHtmlTemplate(
            $name, 'Documents Approved',
            "Your submitted documents have been verified and approved.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br><br>"
          . "Please wait for the next scheduling update.",
            self::COLOR_GREEN
        );
        $text = "Hello {$name},\n\nYour documents have been verified.\nReference Number: {$referenceNumber}\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendDocumentsRejectedEmail($to, $name, $referenceNumber, $reason): bool
    {
        $subject = 'BPC iEnroll: Documents Need Correction';
        $html = $this->buildHtmlTemplate(
            $name, 'Documents Rejected',
            "Your submitted documents require correction before your application can proceed.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br>"
          . "<strong>Reason:</strong> {$this->e($reason)}<br><br>"
          . "Please log in and re-upload your documents.",
            self::COLOR_RED
        );
        $text = "Hello {$name},\n\nYour documents require correction.\nReference Number: {$referenceNumber}\nReason: {$reason}\n\nPlease re-upload your documents.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendDocumentsResubmittedAdminEmail($to, $adminName, $applicantName, $referenceNumber): bool
    {
        $subject = 'BPC iEnroll: Documents Re-submitted by Applicant';
        $html = $this->buildHtmlTemplate(
            $adminName, 'Applicant Re-submitted Documents',
            "An applicant has re-submitted documents for review.<br><br>"
          . "<strong>Applicant:</strong> {$this->e($applicantName)}<br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}",
            self::COLOR_PURPLE
        );
        $text = "Hello {$adminName},\n\nApplicant re-submitted documents.\nApplicant: {$applicantName}\nReference: {$referenceNumber}\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $adminName, $subject, $html, $text);
    }

    public function sendDocumentsResubmittedStudentEmail($to, $name, $referenceNumber): bool
    {
        $subject = 'BPC iEnroll: Documents Re-submitted Successfully';
        $html = $this->buildHtmlTemplate(
            $name,
            'Documents Re-submitted',
            "Your corrected documents have been successfully re-submitted.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br><br>"
          . "The admissions office will review your updated files and notify you once a decision is available.",
            self::COLOR_PURPLE
        );
        $text = "Hello {$name},\n\nYour corrected documents have been re-submitted successfully.\nReference Number: {$referenceNumber}\n\nThe admissions office will review your updated files and notify you once a decision is available.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendExamScheduledEmail($to, $name, $referenceNumber, $date, $time, $venue): bool
    {
        $subject = 'BPC iEnroll: Entrance Exam Scheduled';
        $html = $this->buildHtmlTemplate(
            $name, 'Exam Schedule Notice',
            "Your entrance exam has been scheduled.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br>"
          . "<strong>Date:</strong> {$this->e($date)}<br>"
          . "<strong>Time:</strong> {$this->e($time)}<br>"
          . "<strong>Venue:</strong> {$this->e($venue)}",
            self::COLOR_BLUE
        );
        $text = "Hello {$name},\n\nYour exam is scheduled.\nReference: {$referenceNumber}\nDate: {$date}\nTime: {$time}\nVenue: {$venue}\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendExamPassedEmail($to, $name, $referenceNumber, $score): bool
    {
        $subject = 'BPC iEnroll: Exam Result — Passed';
        $html = $this->buildHtmlTemplate(
            $name, 'Exam Result: Passed ✓',
            "Congratulations! You passed the entrance exam.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br>"
          . "<strong>Score:</strong> {$this->e((string)$score)}/100<br><br>"
          . "Please wait for your interview schedule.",
            self::COLOR_GREEN
        );
        $text = "Hello {$name},\n\nCongratulations! You passed the entrance exam.\nReference: {$referenceNumber}\nScore: {$score}/100\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendExamFailedEmail($to, $name, $referenceNumber, $score): bool
    {
        $subject = 'BPC iEnroll: Exam Result Notice';
        $html = $this->buildHtmlTemplate(
            $name, 'Exam Result: Not Passed',
            "Your exam result has been posted.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br>"
          . "<strong>Score:</strong> {$this->e((string)$score)}/100<br><br>"
          . "Please contact the admissions office for guidance on next steps.",
            self::COLOR_RED
        );
        $text = "Hello {$name},\n\nYour exam result has been posted.\nReference: {$referenceNumber}\nScore: {$score}/100\n\nPlease contact admissions for guidance.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendExamNoShowEmail($to, $name, $referenceNumber): bool
    {
        $subject = 'BPC iEnroll: Exam Attendance Notice';
        $html = $this->buildHtmlTemplate(
            $name, 'Exam: Marked as No Show',
            "Our records indicate you were marked as absent for your scheduled exam.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br><br>"
          . "Please contact the admissions office as soon as possible.",
            self::COLOR_RED
        );
        $text = "Hello {$name},\n\nYou were marked absent for your exam.\nReference: {$referenceNumber}\n\nPlease contact admissions.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendInterviewScheduledEmail($to, $name, $referenceNumber, $date, $time, $venue): bool
    {
        $subject = 'BPC iEnroll: Interview Scheduled';
        $html = $this->buildHtmlTemplate(
            $name, 'Interview Schedule Notice',
            "Your interview has been scheduled.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br>"
          . "<strong>Date:</strong> {$this->e($date)}<br>"
          . "<strong>Time:</strong> {$this->e($time)}<br>"
          . "<strong>Venue:</strong> {$this->e($venue)}",
            self::COLOR_PURPLE
        );
        $text = "Hello {$name},\n\nYour interview is scheduled.\nReference: {$referenceNumber}\nDate: {$date}\nTime: {$time}\nVenue: {$venue}\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendInterviewPassedEmail($to, $name, $referenceNumber): bool
    {
        $subject = 'BPC iEnroll: Interview Completed';
        $html = $this->buildHtmlTemplate(
            $name, 'Interview Completed',
            "You have successfully completed your interview.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br><br>"
          . "Please wait for the final admission decision.",
            self::COLOR_TEAL
        );
        $text = "Hello {$name},\n\nYou have completed your interview.\nReference: {$referenceNumber}\n\nPlease wait for the final admission decision.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendAdmittedEmail($to, $name, $referenceNumber, $program): bool
    {
        $subject = 'BPC iEnroll: Congratulations — You Are Admitted!';
        $html = $this->buildHtmlTemplate(
            $name, 'Congratulations, You Are Admitted!',
            "You have been admitted to Bulacan Polytechnic College.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br>"
          . "<strong>Program:</strong> {$this->e($program)}<br><br>"
          . "Please proceed to the Registrar's office to complete your enrollment. "
          . "Bring your original documents and reference number.",
            self::COLOR_GREEN
        );
        $text = "Hello {$name},\n\nCongratulations! You are admitted to BPC.\nReference: {$referenceNumber}\nProgram: {$program}\n\nPlease proceed with enrollment.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendFinalRejectedEmail($to, $name, $referenceNumber): bool
    {
        $subject = 'BPC iEnroll: Final Admission Decision';
        $html = $this->buildHtmlTemplate(
            $name, 'Admission Decision',
            "Thank you for completing the admission process.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br><br>"
          . "After careful evaluation, your application was not approved at this time. "
          . "You may visit the admissions office for further details or inquiries.",
            self::COLOR_RED
        );
        $text = "Hello {$name},\n\nThank you for completing the admission process.\nReference: {$referenceNumber}\n\nYour application was not approved at this time.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendBulkNotificationEmail($to, $subject, $message): bool
    {
        $safeSubject = trim((string)$subject) !== '' ? trim((string)$subject) : 'BPC iEnroll Notification';
        $html = $this->buildHtmlTemplate(
            'Applicant', $safeSubject,
            nl2br($this->e((string)$message)),
            self::COLOR_BLUE
        );
        $text = (string)$message . "\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, 'Applicant', $safeSubject, $html, $text);
    }

    public function sendTesdaOfferEmail(string $to, string $name, string $referenceNumber, string $tesdaProgram, int $score): bool
    {
        $subject = 'BPC iEnroll — Action Required: TESDA Program Offer';
        $html = $this->buildHtmlTemplate(
            $name, 'Action Required: TESDA Program Offer',
            "Thank you for taking the BPC entrance examination.<br><br>"
          . "<strong>Your exam score:</strong> {$this->e((string)$score)}/100<br><br>"
          . "Unfortunately, your score did not meet the minimum cutoff for your chosen CHED programs. "
          . "However, you listed <strong>{$this->e($tesdaProgram)}</strong> as your TESDA fallback choice.<br><br>"
          . "Please log in to your BPC iEnroll dashboard to <strong>Accept</strong> or <strong>Decline</strong> this offer.<br><br>"
          . "<strong>If you Accept:</strong> Your application continues in the TESDA track and you will be scheduled for an interview.<br>"
          . "<strong>If you Decline:</strong> Your application will be closed. You may re-apply next admission period.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}",
            self::COLOR_AMBER
        );
        $text = "Hello {$name},\n\nExam score: {$score}/100\n\nYou did not meet the CHED cutoff but have a TESDA fallback: {$tesdaProgram}.\nLog in to ACCEPT or DECLINE.\nReference: {$referenceNumber}\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendTesdaAcceptedEmail(string $to, string $name, string $referenceNumber, string $tesdaProgram): bool
    {
        $subject = 'BPC iEnroll — TESDA Offer Accepted';
        $html = $this->buildHtmlTemplate(
            $name, 'TESDA Offer Accepted',
            "You have successfully accepted the TESDA program offer.<br><br>"
          . "<strong>Program:</strong> {$this->e($tesdaProgram)}<br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br><br>"
          . "Your application is now active in the TESDA track. "
          . "Please wait for the Admissions Office to schedule your interview.",
            self::COLOR_GREEN
        );
        $text = "Hello {$name},\n\nYou accepted the TESDA offer: {$tesdaProgram}.\nReference: {$referenceNumber}\n\nPlease wait for your interview schedule.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendTesdaDeclinedEmail(string $to, string $name, string $referenceNumber): bool
    {
        $subject = 'BPC iEnroll — Application Closed';
        $html = $this->buildHtmlTemplate(
            $name, 'Application Closed',
            "You have declined the TESDA program offer. Your application has been closed as per your decision.<br><br>"
          . "<strong>Reference Number:</strong> {$this->e($referenceNumber)}<br><br>"
          . "You are welcome to re-apply during the next admission period.",
            self::COLOR_GRAY
        );
        $text = "Hello {$name},\n\nYou declined the TESDA offer. Your application has been closed.\nReference: {$referenceNumber}\n\nYou may re-apply next admission period.\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $name, $subject, $html, $text);
    }

    public function sendAdminTesdaDecisionEmail(string $to, string $adminName, string $studentName, string $referenceNumber, string $tesdaProgram, string $decision): bool
    {
        $verb    = strtoupper($decision === 'accepted' ? 'ACCEPTED' : 'DECLINED');
        $subject = "BPC iEnroll — Applicant {$verb} TESDA Offer";
        $color   = $decision === 'accepted' ? self::COLOR_GREEN : self::COLOR_GRAY;
        $detail  = $decision === 'accepted'
            ? "The applicant's status has been updated to <strong>Documents Verified (TESDA track)</strong>. They are now eligible for interview scheduling."
            : "The applicant has withdrawn their application.";

        $html = $this->buildHtmlTemplate(
            $adminName, "Applicant {$verb} TESDA Offer",
            "An applicant has responded to their TESDA program offer.<br><br>"
          . "<strong>Applicant:</strong> {$this->e($studentName)}<br>"
          . "<strong>Reference:</strong> {$this->e($referenceNumber)}<br>"
          . "<strong>Program Offered:</strong> {$this->e($tesdaProgram)}<br>"
          . "<strong>Decision:</strong> {$this->e($verb)}<br><br>"
          . $detail,
            $color
        );
        $text = "Hello {$adminName},\n\nApplicant: {$studentName}\nReference: {$referenceNumber}\nProgram: {$tesdaProgram}\nDecision: {$verb}\n\n" . self::FOOTER_TEXT;
        return $this->sendEmail($to, $adminName, $subject, $html, $text);
    }

    // ── Core ──────────────────────────────────────────────────

    private function sendEmail($to, $toName, $subject, $htmlBody, $altBody): bool
    {
        if (!defined('MAIL_HOST') || !defined('MAIL_USERNAME') || !defined('MAIL_PASSWORD')) {
            error_log('MailService: missing mail config constants.');
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('MAIL_PORT') ? MAIL_PORT : 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(MAIL_USERNAME, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'BPC iEnroll');
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $altBody;

            return $mail->send();
        } catch (Exception $e) {
            error_log('MailService PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param string $accentColor  Hex color for the status strip — matches dashboard badge colors.
     *                             Defaults to BPC green if not provided.
     */
    private function buildHtmlTemplate(string $recipientName, string $title, string $messageHtml, string $accentColor = ''): string
    {
        $safeName  = $this->e($recipientName);
        $safeTitle = $this->e($title);
        $accent    = $accentColor ?: self::BRAND_COLOR;

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f4f7f4;font-family:Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f4;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellspacing="0" cellpadding="0"
           style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">

      <!-- BPC Header — always brand green -->
      <tr>
        <td style="background:' . self::BRAND_COLOR . ';color:#ffffff;padding:18px 24px;font-size:17px;font-weight:700;letter-spacing:0.3px;">
          Bulacan Polytechnic College &mdash; iEnroll
        </td>
      </tr>

      <!-- Status accent strip — color matches dashboard status -->
      <tr>
        <td style="background:' . $accent . ';padding:10px 24px;">
          <span style="color:#ffffff;font-size:14px;font-weight:700;letter-spacing:0.5px;">' . $safeTitle . '</span>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:28px 24px 20px;">
          <p style="margin:0 0 16px 0;font-size:15px;color:#374151;">Hello ' . $safeName . ',</p>
          <div style="line-height:1.7;font-size:15px;color:#374151;">' . $messageHtml . '</div>
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="padding:14px 24px 20px;border-top:1px solid #e5e7eb;color:#9ca3af;font-size:12px;">
          ' . self::FOOTER_TEXT . '
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
    }

    private function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}