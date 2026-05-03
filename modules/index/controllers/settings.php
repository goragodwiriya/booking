<?php
/**
 * @filesource modules/index/controllers/settings.php
 *
 * Website Settings Controller
 * Endpoint for settings.html admin form
 * Only Super Admin (status = 1) can access
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Index\Settings;

use Gcms\Api as ApiController;
use Gcms\Config;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Text;
use Kotchasan\Validator;

class Controller extends ApiController
{
    /**
     * Central method for handling settings GET requests
     * Validates authentication and authorization, then returns response with specified data
     *
     * @param Request $request
     * @param array $data Settings data to return
     * @param array $options Optional dropdown/select options
     * @param string $message Success message
     * @return mixed
     */
    private function getSettingsResponse(Request $request, array $data, array $options = [], string $message = '')
    {
        // Validate request method (GET request doesn't need CSRF token)
        ApiController::validateMethod($request, 'GET');

        // Read user from token (Bearer /X-Access-Token param)
        $login = $this->authenticateRequest($request);
        if (!$login) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $canConfig = ApiController::hasPermission($login, ['can_config']);
        $isSpecialMessage = in_array($message, ['API', 'SMS', 'LINE', 'Telegram']);
        $isSuperAdmin = ApiController::isSuperAdmin($login);
        if ((!$canConfig && !$isSuperAdmin) || (!$isSuperAdmin && $isSpecialMessage)) {
            return $this->errorResponse('Forbidden', 403);
        }

        $response = [
            'data' => (object) $data
        ];

        if (!empty($options)) {
            $response['options'] = $options;
        }

        return $this->successResponse($response, $message.' settings loaded');
    }

    /**
     * Get General settings data
     * @return array
     */
    private function getGeneralData()
    {
        $result = [
            'web_title' => self::$cfg->web_title,
            'web_description' => self::$cfg->web_description,
            'timezone' => self::$cfg->timezone,
            'server_time' => date('d/m/Y H:i:s'),
            'server_version' => 'PHP v'.phpversion(),
            'user_register' => self::$cfg->user_register,
            'user_forgot' => self::$cfg->user_forgot,
            'new_members_active' => self::$cfg->new_members_active,
            'activate_user' => self::$cfg->activate_user,
            'require_terms_acceptance' => self::$cfg->require_terms_acceptance,
            'facebook_appId' => self::$cfg->facebook_appId,
            'google_client_id' => self::$cfg->google_client_id,
            'demo_mode' => self::$cfg->demo_mode,
            'cache_expire' => self::$cfg->cache_expire,
            'default_department' => self::$cfg->default_department
        ];

        // Logo image
        if (file_exists(ROOT_PATH.DATA_FOLDER.'images/logo'.self::$cfg->stored_img_type)) {
            $result['logo'] = [
                [
                    'url' => WEB_URL.DATA_FOLDER.'images/logo'.self::$cfg->stored_img_type.'?'.time(),
                    'name' => 'logo'
                ]
            ];
        } else {
            $result['logo'] = [
                [
                    'url' => WEB_URL.'images/no-image.webp',
                    'name' => 'Choose file'
                ]
            ];
        }

        return $result;
    }

    /**
     * Get Email settings data
     * @return array
     */
    private function getEmailData()
    {
        return [
            'noreply_email' => self::$cfg->noreply_email,
            'email_use_phpMailer' => self::$cfg->email_use_phpMailer,
            'email_Host' => self::$cfg->email_Host,
            'email_Port' => self::$cfg->email_Port,
            'email_SMTPAuth' => self::$cfg->email_SMTPAuth,
            'email_SMTPSecure' => self::$cfg->email_SMTPSecure
        ];
    }

    /**
     * Get API settings data
     * @return array
     */
    private function getApiData()
    {
        return [
            'api_url' => empty(self::$cfg->api_url) ? WEB_URL.'api/' : self::$cfg->api_url,
            'api_token' => empty(self::$cfg->api_tokens['external']) ? \Kotchasan\Password::uniqid(40) : self::$cfg->api_tokens['external'],
            'api_secret' => empty(self::$cfg->api_secret) ? \Kotchasan\Password::uniqid() : self::$cfg->api_secret,
            'api_ips' => !empty(self::$cfg->api_ips) && is_array(self::$cfg->api_ips) ? implode("\n", self::$cfg->api_ips) : '',
            'api_cors' => empty(self::$cfg->api_cors) ? '' : self::$cfg->api_cors
        ];
    }

    /**
     * Get LINE settings data
     * @return array
     */
    private function getLineData()
    {
        return [
            'line_channel_id' => self::$cfg->line_channel_id,
            'line_channel_secret' => self::$cfg->line_channel_secret,
            'line_callback_url' => WEB_URL.'line/callback',
            'line_official_account' => self::$cfg->line_official_account,
            'line_channel_access_token' => self::$cfg->line_channel_access_token,
            'line_webhook_url' => WEB_URL.'line/webhook'
        ];
    }

    /**
     * Get Telegram settings data
     * @return array
     */
    private function getTelegramData()
    {
        return [
            'telegram_bot_username' => self::$cfg->telegram_bot_username,
            'telegram_chat_id' => self::$cfg->telegram_chat_id,
            'telegram_bot_token' => self::$cfg->telegram_bot_token
        ];
    }

    /**
     * Get SMS settings data
     * @return array
     */
    private function getSmsData()
    {
        return [
            'sms_username' => self::$cfg->sms_username ?? '',
            'sms_api_key' => self::$cfg->sms_api_key ?? '',
            'sms_api_secret' => self::$cfg->sms_api_secret ?? '',
            'sms_sender' => self::$cfg->sms_sender ?? '',
            'sms_type' => self::$cfg->sms_type ?? ''
        ];
    }

    /**
     * Get Cookie Policy settings data
     * @return array
     */
    private function getCookiePolicyData()
    {
        return [
            'cookie_policy' => self::$cfg->cookie_policy ?? '',
            'data_controller' => self::$cfg->data_controller ?? ''
        ];
    }

    /**
     * Get Theme settings data
     * @return array
     */
    private function getThemeData()
    {
        $result = [];
        foreach (self::$cfg->theme as $key => $value) {
            $key = str_replace('-', '', ucwords(trim($key, '-'), '-'));
            $result[$key] = $value;
        }
        // Body background image
        if (file_exists(ROOT_PATH.DATA_FOLDER.'images/bg_image'.self::$cfg->stored_img_type)) {
            $result['bg_image'] = [
                [
                    'url' => WEB_URL.DATA_FOLDER.'images/bg_image'.self::$cfg->stored_img_type,
                    'name' => 'bg_image'
                ]
            ];
        } else {
            $result['bg_image'] = [
                [
                    'url' => WEB_URL.'images/no-image.webp',
                    'name' => 'Choose file'
                ]
            ];
        }

        return $result;
    }

    /**
     * Get Company settings data
     * @return array
     */
    private function getCompanyData()
    {
        $company = self::$cfg->company ?? [];

        $result = [
            'company_name' => $company['name'] ?? '',
            'company_name_en' => $company['name_en'] ?? '',
            'company_address' => $company['address'] ?? '',
            'company_phone' => $company['phone'] ?? '',
            'company_fax' => $company['fax'] ?? '',
            'company_email' => $company['email'] ?? '',
            'company_tax_id' => $company['tax_id'] ?? ''
        ];

        // Company logo
        if (file_exists(ROOT_PATH.DATA_FOLDER.'images/company_logo'.self::$cfg->stored_img_type)) {
            $result['company_logo'] = [
                [
                    'url' => WEB_URL.DATA_FOLDER.'images/company_logo'.self::$cfg->stored_img_type,
                    'name' => 'Company logo'
                ]
            ];
        } else {
            $result['company_logo'] = [
                [
                    'url' => WEB_URL.'images/no-image.webp',
                    'name' => 'Choose file'
                ]
            ];
        }

        // Company stamp
        if (file_exists(ROOT_PATH.DATA_FOLDER.'images/company_stamp'.self::$cfg->stored_img_type)) {
            $result['company_stamp'] = [
                [
                    'url' => WEB_URL.DATA_FOLDER.'images/company_stamp'.self::$cfg->stored_img_type,
                    'name' => 'Company stamp'
                ]
            ];
        } else {
            $result['company_stamp'] = [
                [
                    'url' => WEB_URL.'images/no-image.webp',
                    'name' => 'Choose file'
                ]
            ];
        }

        return $result;
    }

    // ==================== Public Endpoints ====================

    /**
     * General settings endpoint
     * @param Request $request
     * @return mixed
     */
    public function general(Request $request)
    {
        return $this->getSettingsResponse(
            $request,
            $this->getGeneralData(),
            [
                'timezone' => $this->getTimezone(),
                'department' => \Gcms\Category::init()->toOptions('department', true, null, ['' => '{LNG_Not specified}'])
            ],
            'General'
        );
    }

    /**
     * Email settings endpoint
     * @param Request $request
     * @return mixed
     */
    public function email(Request $request)
    {
        return $this->getSettingsResponse($request, $this->getEmailData(), [], 'Email');
    }

    /**
     * API settings endpoint
     * @param Request $request
     * @return mixed
     */
    public function api(Request $request)
    {
        return $this->getSettingsResponse($request, $this->getApiData(), [], 'API');
    }

    /**
     * LINE settings endpoint
     * @param Request $request
     * @return mixed
     */
    public function line(Request $request)
    {
        return $this->getSettingsResponse($request, $this->getLineData(), [], 'LINE');
    }

    /**
     * Telegram settings endpoint
     * @param Request $request
     * @return mixed
     */
    public function telegram(Request $request)
    {
        return $this->getSettingsResponse($request, $this->getTelegramData(), [], 'Telegram');
    }

    /**
     * SMS settings endpoint
     * @param Request $request
     * @return mixed
     */
    public function sms(Request $request)
    {
        return $this->getSettingsResponse($request, $this->getSmsData(), ['sms_typies' => $this->getSmsTypies()], 'SMS');
    }

    /**
     * Cookie Policy settings endpoint
     * @param Request $request
     * @return mixed
     */
    public function cookiePolicy(Request $request)
    {
        return $this->getSettingsResponse($request, $this->getCookiePolicyData(), [], 'Cookie Policy');
    }

    /**
     * Theme settings endpoint
     * @param Request $request
     * @return mixed
     */
    public function theme(Request $request)
    {
        return $this->getSettingsResponse($request, $this->getThemeData(), [], 'Theme');
    }

    /**
     * Company settings endpoint
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function company(Request $request)
    {
        return $this->getSettingsResponse($request, $this->getCompanyData(), [], 'Company');
    }

    /**
     * Remove background image
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function removeBgImage(Request $request)
    {
        return $this->removeImage($request, 'bg_image');
    }

    /**
     * Remove logo
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function removeLogo(Request $request)
    {
        return $this->removeImage($request, 'logo');
    }

    /**
     * Remove company logo
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function removeCompanyLogo(Request $request)
    {
        return $this->removeImage($request, 'company_logo');
    }

    /**
     * Remove company stamp
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function removeCompanyStamp(Request $request)
    {
        return $this->removeImage($request, 'company_stamp');
    }

    /**
     * Get timezone list
     * @return array
     */
    private function getTimezone()
    {
        // timezone
        $datas = [];
        foreach (\DateTimeZone::listIdentifiers() as $item) {
            $datas[] = ['text' => $item, 'value' => $item];
        }
        return $datas;
    }

    private function getSmsTypies()
    {
        return [
            ['text' => 'Standard ('.\Thaibluksms\Sms::check_credit(false).')', 'value' => 'standard'],
            ['text' => 'Premium ('.\Thaibluksms\Sms::check_credit(true).')', 'value' => 'premium']
        ];
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            ApiController::validateCsrfToken($request);

            // Authentication check (required)
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }

            // Authorization for saving
            if (!ApiController::canModify($login)) {
                return $this->errorResponse('Permission required', 403);
            }

            // Get data from request
            $body = $request->getParsedBody();

            if (empty($body['module'])) {
                return $this->errorResponse('Module is required', 400);
            }

            // Normalize module name to a safe format for method call
            $module = preg_replace('/[^a-z\-]/', '', (string) $body['module']);
            $module = ucwords($module, '-');
            $className = 'parse'.str_replace('-', '', $module).'Settings';

            // Check method exists
            if (!method_exists($this, $className)) {
                return $this->errorResponse('Module not found', 404);
            }

            // Upload image
            $error = $this->imageUpload($request);
            if (!empty($error)) {
                return $this->formErrorResponse($error);
            }

            // Load config
            $config = Config::load(ROOT_PATH.'settings/config.php');

            // Execute
            $ret = $this->$className($body, $config);

            if (!empty($ret)) {
                return $this->formErrorResponse($ret);
            }

            if (Config::save($config, ROOT_PATH.'settings/config.php')) {
                // Log
                \Index\Log\Model::add(0, 'index', 'Save', 'Save '.str_replace('-', ' ', $module).' Settings', $login->id);

                // Reload page
                return $this->redirectResponse('reload', 'Saved successfully', 200, 1000);
            }
        } catch (\Kotchasan\ApiException $e) {
            // Keep original HTTP code (e.g. 403 CSRF, 405 method)
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        }
        // Error save settings
        return $this->errorResponse('Failed to save settings', 500);
    }

    /**
     * Convert value to boolean
     *
     * @param mixed $value
     *
     * @return int
     */
    private function toBoolean($array, $key)
    {
        $value = $array[$key];
        return !empty($value) && $value !== '0' && $value !== 'false' ? 1 : 0;
    }

    /**
     * General settings
     *
     * @param  array $body
     * @param  object $config
     *
     * @return array
     */
    private function parseGeneralSettings($body, $config)
    {
        $ret = [];
        foreach (['web_title', 'web_description'] as $key) {
            if (isset($body[$key])) {
                // allow em, b, strong, i tags
                $value = Text::htmlText($body[$key]);
                if ($value === '') {
                    $ret[$key] = 'Please fill in';
                } else {
                    $config->$key = $value;
                }
            }
        }

        $boolKeys = ['activate_user', 'cookie_policy', 'demo_mode', 'new_members_active', 'require_terms_acceptance', 'user_forgot', 'user_register'];
        foreach ($boolKeys as $key) {
            if (isset($body[$key])) {
                $config->$key = $this->toBoolean($body, $key);
            }
        }

        $textKeys = ['facebook_appId', 'timezone'];
        foreach ($textKeys as $key) {
            if (isset($body[$key])) {
                $config->$key = Text::topic($body[$key]);
            }
        }

        $textKeys = ['cache_expire'];
        foreach ($textKeys as $key) {
            if (isset($body[$key])) {
                $config->$key = intval($body[$key]);
            }
        }

        if (isset($body['google_client_id'])) {
            $parts = explode('.', $body['google_client_id']);
            $config->google_client_id = !empty($parts) ? $parts[0] : '';
        }

        return $ret;
    }

    /**
     * Email settings
     *
     * @param  array $body
     * @param  object $config
     *
     * @return array
     */
    private function parseEmailSettings($body, $config)
    {
        $ret = [];

        if (!empty($body['noreply_email']) && !Validator::email($body['noreply_email'])) {
            $ret['noreply_email'] = 'Invalid email';
        }

        $config->noreply_email = Text::username($body['noreply_email']);
        if (empty($body['email_Host'])) {
            $config->email_Host = 'localhost';
            $config->email_Port = 25;
            $config->email_SMTPSecure = '';
            $config->email_Username = '';
            $config->email_Password = '';
        } else {
            $config->email_Host = Text::url($body['email_Host']);
            $config->email_Port = (int) $body['email_Port'] ?? 25;
            $config->email_SMTPSecure = Text::filter($body['email_SMTPSecure'], 'a-zA-Z');
            if (!empty($body['email_Username'])) {
                $config->email_Username = Text::username($body['email_Username']);
            }
            if (!empty($body['email_Password'])) {
                $config->email_Password = Text::password($body['email_Password']);
            }
        }
        $config->email_use_phpMailer = (int) $body['email_use_phpMailer'];
        $config->email_SMTPAuth = $this->toBoolean($body, 'email_SMTPAuth');

        return $ret;
    }

    /**
     * API settings
     *
     * @param  array $body
     * @param  object $config
     *
     * @return array
     */
    private function parseApiSettings($body, $config)
    {
        $config->api_url = Text::url($body['api_url']);
        $config->api_tokens['external'] = Text::password($body['api_token']);
        $config->api_secret = Text::password($body['api_secret']);
        $config->api_cors = Text::url($body['api_cors']);
        $config->api_ips = [];
        foreach (explode("\n", $body['api_ips']) as $ip) {
            if (preg_match('/([0-9\.]+)/', $ip, $match)) {
                $config->api_ips[$match[1]] = $match[1];
            }
        }
        $config->api_ips = array_keys($config->api_ips);

        return [];
    }

    /**
     * Line settings
     *
     * @param  array $body
     * @param  object $config
     *
     * @return array
     */
    private function parseLineSettings($body, $config)
    {
        $config->line_channel_id = Text::number($body['line_channel_id']);
        $config->line_channel_secret = Text::topic($body['line_channel_secret']);
        $config->line_channel_access_token = Text::topic($body['line_channel_access_token']);
        $config->line_official_account = Text::topic($body['line_official_account']);

        return [];
    }

    /**
     * Telegram settings
     *
     * @param  array $body
     * @param  object $config
     *
     * @return array
     */
    private function parseTelegramSettings($body, $config)
    {
        $config->telegram_bot_token = Text::topic($body['telegram_bot_token']);
        $config->telegram_chat_id = Text::topic($body['telegram_chat_id']);
        $config->telegram_bot_username = str_replace(['\\', '/', '@'], '', Text::topic($body['telegram_bot_username']));

        return [];
    }
    /**
     * SMS settings
     *
     * @param  array $body
     * @param  object $config
     *
     * @return array
     */
    private function parseSmsSettings($body, $config)
    {
        $config->sms_username = Text::topic($body['sms_username']);
        if (!empty($body['sms_password'])) {
            $config->sms_password = Text::topic($body['sms_password']);
        }
        $config->sms_api_key = Text::topic($body['sms_api_key']);
        $config->sms_api_secret = Text::topic($body['sms_api_secret']);
        $config->sms_sender = Text::topic($body['sms_sender']);
        $config->sms_type = Text::topic($body['sms_type']);

        return [];
    }

    /**
     * Cookie Policy settings
     *
     * @param  array $body
     * @param  object $config
     *
     * @return array
     */
    private function parseCookiePolicySettings($body, $config)
    {
        $ret = [];

        if (!empty($body['data_controller']) && !Validator::email($body['data_controller'])) {
            $ret['data_controller'] = 'Invalid email';
        }
        $config->cookie_policy = $this->toBoolean($body, 'cookie_policy');
        $config->data_controller = Text::username($body['data_controller']);

        return $ret;
    }

    /**
     * Theme settings
     *
     * @param  array $body
     * @param  object $config
     *
     * @return array
     */
    private function parseThemeSettings($body, $config)
    {
        $config->theme = [
            '--color-background' => Text::color($body['ColorBackground']),
            '--color-text' => Text::color($body['ColorText']),
            '--header-color-background' => Text::color($body['HeaderColorBackground']),
            '--header-color-text' => Text::color($body['HeaderColorText']),
            '--sidebar-color-background' => Text::color($body['SidebarColorBackground']),
            '--sidebar-color-text' => Text::color($body['SidebarColorText']),
            '--menu-highlight-bg' => Text::color($body['MenuHighlightBg']),
            '--menu-highlight-text' => Text::color($body['MenuHighlightText']),
            '--footer-color-background' => Text::color($body['FooterColorBackground']),
            '--footer-color-text' => Text::color($body['FooterColorText'])
        ];
        foreach ($config->theme as $key => $value) {
            if (empty($value)) {
                unset($config->theme[$key]);
            }
        }
        return [];
    }

    /**
     * Company settings
     *
     * @param array $body
     * @param object $config
     *
     * @return array
     */
    private function parseCompanySettings($body, $config)
    {
        $ret = [];

        // Initialize company array if not exists
        if (!isset($config->company) || !is_array($config->company)) {
            $config->company = [];
        }

        // Text fields
        $config->company['name'] = Text::topic($body['company_name'] ?? '');
        $config->company['name_en'] = Text::topic($body['company_name_en'] ?? '');
        $config->company['address'] = Text::textarea($body['company_address'] ?? '');
        $config->company['phone'] = Text::topic($body['company_phone'] ?? '');
        $config->company['fax'] = Text::topic($body['company_fax'] ?? '');
        $config->company['email'] = Text::username($body['company_email'] ?? '');
        $config->company['tax_id'] = Text::number($body['company_tax_id'] ?? '');

        // Keep existing logo/stamp paths if they exist
        $logoPath = DATA_FOLDER.'company/logo'.self::$cfg->stored_img_type;
        $stampPath = DATA_FOLDER.'company/stamp'.self::$cfg->stored_img_type;

        if (file_exists(ROOT_PATH.$logoPath)) {
            $config->company['logo'] = $logoPath;
        }
        if (file_exists(ROOT_PATH.$stampPath)) {
            $config->company['stamp'] = $stampPath;
        }

        return $ret;
    }

    /**
     * Send test email
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function testEmail(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $login = $this->authenticateRequest($request);

            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Authorization check
            if (!ApiController::hasPermission($login, ['can_config'])) {
                return $this->errorResponse('Forbidden', 403);
            }

            // Get email from logged-in user
            $toEmail = $login->username ?? '';

            if (empty($toEmail) || !Validator::email($toEmail)) {
                return $this->errorResponse('Your account does not have a valid email address', 400);
            }

            // Send test email
            $subject = self::$cfg->web_title.' - Test Email';
            $message = '<h2>Test Email</h2>';
            $message .= '<p>This is a test email from '.self::$cfg->web_title.'.</p>';
            $message .= '<p>If you received this email, your email configuration is working correctly.</p>';
            $message .= '<hr>';
            $message .= '<p><small>Sent at: '.date('Y-m-d H:i:s').'</small></p>';

            $email = \Kotchasan\Email::send($toEmail, '', $subject, $message);

            if ($email->error()) {
                return $this->errorResponse('Failed to send email: '.$email->getErrorMessage(), 500);
            }

            // Log
            \Index\Log\Model::add(0, 'index', 'Other', 'Test email sent to '.$toEmail, $login->id);

            return $this->successResponse([], 'Test email sent to '.$toEmail);
        } catch (\Kotchasan\ApiException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send test: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function testTelegram(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $login = $this->authenticateRequest($request);

            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Authorization check
            if (!ApiController::hasPermission($login, ['can_config'])) {
                return $this->errorResponse('Forbidden', 403);
            }

            $bot_token = $request->post('bot_token')->topic();
            $chat_id = $request->post('chat_id')->topic();

            // ทดสอบส่งข้อความ Telegram
            echo \Gcms\Telegram::sendTo($chat_id, strip_tags(self::$cfg->web_title), $bot_token);

            return $this->successResponse([], 'Test Telegram success');
        } catch (\Kotchasan\ApiException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send test: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param Request $request
     */
    private function imageUpload(Request $request)
    {
        $errors = [];
        // File storage directory
        $dir = ROOT_PATH.DATA_FOLDER.'images/';
        // อัปโหลดไฟล์
        foreach ($request->getUploadedFiles() as $item => $file) {
            if (in_array($item, ['logo', 'bg_image', 'company_logo', 'company_stamp'])) {
                if (!File::makeDirectory($dir)) {
                    // The directory cannot be created.
                    $errors[$item] = Language::replace('Directory %s cannot be created or is read-only.', DATA_FOLDER.'images/');
                } elseif ($file->hasUploadFile()) {
                    try {
                        $file->resizeImage(self::$cfg->img_typies, $dir, $item.self::$cfg->stored_img_type, self::$cfg->stored_img_size);
                    } catch (\Exception $exc) {
                        // Unable to upload
                        $errors[$item] = Language::get($exc->getMessage());
                    }
                } elseif ($err = $file->getErrorMessage()) {
                    // Upload error
                    $errors[$item] = $err;
                }
            }
        }
        return $errors;
    }

    /**
     * Remove image file (logo or bg_image)
     *
     * @param Request $request
     * @param string $item Image type to remove
     *
     * @return mixed
     */
    private function removeImage(Request $request, $item)
    {
        try {
            // Whitelist allowed image types to prevent path traversal
            $allowedItems = ['logo', 'bg_image', 'company_logo', 'company_stamp'];
            if (!in_array($item, $allowedItems, true)) {
                return $this->errorResponse('Invalid image type', 400);
            }

            ApiController::validateMethod($request, 'POST');
            ApiController::validateCsrfToken($request);
            $login = $this->authenticateRequest($request);

            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }

            // Authorization for remove image
            if (!ApiController::isSuperAdmin($login)) {
                return $this->errorResponse('Permission required', 403);
            }

            $dir = ROOT_PATH.DATA_FOLDER.'images/';
            $filePath = $dir.$item.self::$cfg->stored_img_type;

            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    return $this->errorResponse('Failed to delete image', 500);
                }

                // Log the action
                \Index\Log\Model::add(0, 'index', 'Delete', 'Removed '.$item.' image', $login->id);

                return $this->redirectResponse('reload', ucfirst(str_replace('_', ' ', $item)).' removed successfully', 200, 1000);
            }

            // File doesn't exist, still success (idempotent)
            return $this->redirectResponse('reload', ucfirst(str_replace('_', ' ', $item)).' already removed', 200, 1000);
        } catch (\Kotchasan\ApiException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove image: '.$e->getMessage(), 500);
        }
    }
}
