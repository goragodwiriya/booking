<?php
/**
 * @filesource modules/index/controllers/linecallback.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Linecallback;

use Gcms\Api as ApiController;
use Kotchasan\Curl;
use Kotchasan\Http\Request;
use Kotchasan\Text;

/**
 * linecallback.php
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * Controller รับค่าการ Login ด้วย LINE
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        $code = $request->get('code', '')->toString();
        $ret_url = base64_decode($request->get('state', '')->toString());

        if ($code !== '') {
            // get refresh token
            $url = "https://api.line.me/oauth2/v2.1/token";
            $curl = new Curl();
            $content = $curl->post($url, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => str_replace('www.', '', WEB_URL.'line/callback.php'),
                'client_id' => self::$cfg->line_channel_id,
                'client_secret' => self::$cfg->line_channel_secret
            ]);
            $result = json_decode($content, true);
            // get user info
            $url = 'https://api.line.me/oauth2/v2.1/verify';
            $curl = new Curl();
            $content = $curl->post($url, [
                'id_token' => $result['id_token'],
                'client_id' => self::$cfg->line_channel_id
            ]);
            $user = json_decode($content, true);
            if (!empty($user['sub'])) {
                // User information
                $data = [
                    'username' => Text::username(empty($user['email']) ? 'LINE'.$user['sub'] : $user['email']),
                    'name' => Text::topic($user['name']),
                    'picture' => Text::username($user['picture']),
                    'line_uid' => $user['sub']
                ];

                // check user
                $existingUser = \Kotchasan\Model::createQuery()
                    ->select()
                    ->from('user')
                    ->where([
                        ['username', $data['username']],
                        ['line_uid', $data['line_uid']]
                    ], 'OR')
                    ->first();

                if (!$existingUser) {
                    // Generate random password for social login
                    $data['password'] = \Kotchasan\Password::uniqid(12);
                    $data['social'] = 'line';

                    // Create user (social login users are always active)
                    \Index\Register\Model::createUser($data, null, [
                        'send_email' => false, // Don't send email for social login
                        'auto_login' => true, // Auto-login after social registration
                        'download_avatar' => !empty($data['picture']) ? $data['picture'] : null
                    ]);

                } elseif ($existingUser->social === 'line') {
                    // Generate tokens for login
                    $loginResult = \Index\Auth\Model::generateTokens($existingUser->id);
                    if (!$loginResult) {
                        // redirect
                        header('Location: '.WEB_URL);
                        exit;
                    }

                    \Index\Auth\Model::updateUserToken(
                        $existingUser->id,
                        $loginResult['access_token'],
                        $loginResult['expires_at']
                    );

                    // Log successful social login
                    $clientIp = $request->getClientIp();
                    \Index\Auth\Model::logLoginActivity(
                        $existingUser->id,
                        'LINE Sign-in: '.$existingUser->username,
                        $clientIp
                    );

                    // Set auth cookie for frontend
                    \Index\Auth\Model::setCookie('auth_token', $loginResult['access_token']);
                } else {
                    // Update line_uid
                    \Kotchasan\DB::create()->update('user', [['id', $existingUser->id]], ['line_uid' => $user['sub']]);
                }

                // redirect
                header('Location: '.$ret_url);
                exit;
            }
        }
        // redirect
        header('Location: '.WEB_URL);
        exit;
    }
}
