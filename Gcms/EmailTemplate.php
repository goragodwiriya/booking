<?php
/**
 * @filesource Gcms/EmailTemplate.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Gcms;

use Kotchasan\Language;

/**
 * Email Template Service
 *
 * Provides centralized email template management with:
 * - Variable substitution ({VAR_NAME})
 * - Consistent base layout
 * - Future database support ready
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class EmailTemplate extends \Kotchasan\KBase
{

    /**
     * Get template by code
     *
     * Future: Override this method to fetch from database
     *
     * @param string $code Template code
     * @param string $lang Language code (for future i18n support)
     *
     * @return array|null Template data or null if not found
     */
    public static function get(string $code, string $lang = 'th'): ?array
    {
        return \Kotchasan\Model::createQuery()
            ->select()
            ->from('emailtemplate')
            ->where([['code', $code], ['language', $lang]])
            ->cacheOn()
            ->first();
    }

    /**
     * Render template with variable substitution
     *
     * @param string $template Template string
     * @param array  $variables Variables to substitute
     *
     * @return string Rendered template
     */
    public static function render(string $template, array $variables, bool $escapeHtml = true): string
    {
        // Replace %VAR_NAME% with values. By default the substituted value is
        // HTML-escaped so user-supplied variables cannot inject markup/script
        // into the email body (stored/reflected XSS). Pass $escapeHtml=false
        // only for non-HTML contexts (e.g. the subject line).
        foreach ($variables as $key => $value) {
            $value = is_scalar($value) ? (string) $value : '';
            if ($escapeHtml) {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            $template = str_replace('%'.$key.'%', $value, $template);
        }

        return $template;
    }

    /**
     * Send email using template
     *
     * @param string $code      Template code
     * @param string $to        Recipient email
     * @param array  $variables Template variables
     *
     * @return bool|string True on success, error message on failure
     */
    public static function send(string $code, string $to, array $variables)
    {
        $template = self::get($code);

        if (!$template) {
            return 'Email template not found: '.$code;
        }

        // Add default variables
        $variables['WEBTITLE'] = $variables['WEBTITLE'] ?? self::$cfg->web_title ?? 'Website';
        $variables['WEBURL'] = WEB_URL;
        $variables['TIME'] = date('Y-m-d H:i');

        // Render. Subject is plain text — don't HTML-escape it, but strip CR/LF
        // to prevent mail-header injection (extra Bcc/Cc). Body is HTML-escaped.
        $subject = self::render($template['subject'], $variables, false);
        $subject = str_replace(["\r", "\n"], '', $subject);
        $html = self::render($template['detail'], $variables, true);

        // Send email
        $from = self::$cfg->noreply_email ?? null;
        $mail = \Kotchasan\Email::send($to, $from, $subject, $html);

        if ($mail->error()) {
            return $mail->getErrorMessage();
        }

        return true;
    }
}
