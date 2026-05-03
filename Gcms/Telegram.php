<?php
/**
 * @filesource Gcms/Telegram.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Gcms;

/**
 * Telegram Bot and Login API Class
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Telegram extends \Kotchasan\KBase
{
    /**
     * Base URL for Telegram API
     *
     * @var string
     */
    private static $apiUrl = "https://api.telegram.org/bot";

    /**
     * Validity period for login data (default: 24 hours)
     *
     * @var int
     */
    private static $validityPeriod = 86400;

    /**
     * Send request to Telegram API
     *
     * @param string $method Telegram API method
     * @param array $params Parameters to send with the request
     * @param string|null $botToken Bot token (overrides default if provided)
     * @return array|false API response or false on error
     */
    private static function sendRequest($method, $params = [], $botToken = null)
    {
        if ($botToken === null) {
            $botToken = self::$cfg->telegram_bot_token;
        }
        if (empty($botToken)) {
            return 'API key can not be empty';
        }

        $url = self::$apiUrl.$botToken."/".$method;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params)
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Validate login data from Telegram Login Widget
     *
     * @param array $authData Data received from Telegram (e.g., $_GET or $_POST)
     * @param string|null $botToken Bot token (overrides default if provided)
     * @return array|false User data if valid, false if invalid
     */
    public static function validateLogin($authData, $botToken = null)
    {
        // Check if required fields are present
        if (!isset($authData['id']) || !isset($authData['hash']) || !isset($authData['auth_date'])) {
            return false;
        }

        $token = $botToken ? $botToken : self::$cfg->telegram_bot_token;
        if (empty($token)) {
            return false; // Bot token required for validation
        }

        // Extract hash for validation
        $checkHash = $authData['hash'];
        unset($authData['hash']);

        // Build data string for HMAC validation
        $dataCheckArr = [];
        foreach ($authData as $key => $value) {
            $dataCheckArr[] = "$key=$value";
        }
        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);

        // Generate hash for comparison
        $secretKey = hash('sha256', $token, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // Validate hash and timestamp
        if (strcmp($hash, $checkHash) !== 0) {
            return false; // Invalid hash
        }
        if ((time() - $authData['auth_date']) > self::$validityPeriod) {
            return false; // Data expired
        }

        return $authData; // Return validated user data
    }

    /**
     * Send message to a Telegram user
     *
     * @param string|array $ Chatid Chat ID of the recipient can specify multiple items (Aray)
     * @param string $text Message text to send
     * @param string|null $botToken Bot token (overrides default if provided)
     * @return string Message error or emptiness
     */
    public static function sendTo($chatId, $text, $botToken = null)
    {
        if (!empty($chatId) && !empty($text)) {
            $ids = is_array($chatId) ? $chatId : [$chatId];
            $message = self::toText($text);
            $ret = [];

            foreach ($ids as $id) {
                $response = self::sendRequest('sendMessage', [
                    'chat_id' => $id,
                    'text' => $message
                ], $botToken);

                if ($response === false || (isset($response['error_code']) && isset($response['description']))) {
                    $ret[$response['error_code']] = $response['description'];
                }
            }

            if (!empty($ret)) {
                return implode("\n", $ret);
            }
        }

        return '';
    }

    /**
     * Set Telegram webhook
     *
     * @param string $url Webhook URL to set
     * @param string|null $botToken Bot token (overrides default if provided)
     * @return array|false API response or false on error
     */
    public static function setWebhook($url, $botToken = null)
    {
        return self::sendRequest('setWebhook', [
            'url' => $url
        ], $botToken);
    }

    /**
     * Delete Telegram webhook
     *
     * @param string|null $botToken Bot token (overrides default if provided)
     * @return array|false API response or false on error
     */
    public static function deleteWebhook($botToken = null)
    {
        return self::sendRequest('deleteWebhook', [], $botToken);
    }

    /**
     * Convert HTML message to plain text for Telegram
     * - Remove tags, preserve line breaks
     * - Convert <br> to \n
     *
     * @param string $message Input message
     * @return string Processed plain text
     */
    private static function toText($message)
    {
        $message = preg_replace_callback(
            '/<tr\b[^>]*>(.*?)<\/tr>/s',
            function ($matches) {
                $trContent = $matches[1];

                $cleanedTrContent = preg_replace_callback(
                    '/<\/?(td|th)\b[^>]*>(.*?)<\/\2>/s',
                    function ($cellMatches) {
                        return '<td>'.$cellMatches[1].'</td>';
                    },
                    $trContent
                );

                $cleanedTrContent = preg_replace('/\n+/', '', $cleanedTrContent);

                return '<tr>'.$cleanedTrContent.'</tr>';
            },
            str_replace(["\r", "\t"], '', $message)
        );
        $message = str_replace(['<br>', '<br />'], "\n", $message);
        $msg = [];
        foreach (explode("\n", strip_tags($message)) as $row) {
            $row = trim($row);
            if ($row != '') {
                $msg[] = $row;
            }
        }
        return \Kotchasan\Text::unhtmlspecialchars(implode("\n", $msg));
    }

    /**
     * Set validity period for login data
     *
     * @param int $seconds Validity period in seconds
     */
    public static function setValidityPeriod($seconds)
    {
        self::$validityPeriod = $seconds;
    }
}
