<?php
/**
 * @filesource modules/index/models/social.php
 *
 * Social Login Model
 *
 * Handles social authentication user management:
 * - Find existing social users
 * - Create new users from social data
 * - Link social accounts to existing users
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Index\Social;

use Kotchasan\Language;

/**
 * Social Authentication Model
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Social provider IDs (matching database column 'social')
     */
    const PROVIDER_IDS = [
        'registered' => 0,
        'facebook' => 1,
        'google' => 2,
        'line' => 3,
        'telegram' => 4
    ];

    /**
     * Find or create user from social login
     *
     * @param string $provider Provider name (google, facebook, line, telegram)
     * @param array $socialUser User data from provider
     *
     * @return array Result with user data
     */
    public static function findOrCreateSocialUser($provider, array $socialUser)
    {
        $socialId = $socialUser['id'] ?? null;

        if (!$socialId) {
            return [
                'success' => false,
                'message' => 'Invalid social user data'
            ];
        }

        // First, try to find by social ID and provider
        $existingUser = self::findBySocialId($provider, $socialId);

        if ($existingUser) {
            // Update user info from social provider
            self::updateSocialUserInfo($existingUser->id, $socialUser);

            return [
                'success' => true,
                'message' => Language::get('Login successful'),
                'user' => self::sanitizeUserData($existingUser),
                'isNew' => false
            ];
        }

        // Try to find by email if available
        if (!empty($socialUser['email'])) {
            $emailUser = self::findByEmail($socialUser['email']);

            if ($emailUser) {
                // Link social account to existing user
                self::linkSocialAccount($emailUser->id, $provider, $socialId, $socialUser);

                return [
                    'success' => true,
                    'message' => Language::get('Account linked successfully'),
                    'user' => self::sanitizeUserData($emailUser),
                    'isNew' => false,
                    'linked' => true
                ];
            }
        }

        // Create new user
        $newUser = self::createFromSocial($provider, $socialUser);

        if (!$newUser) {
            return [
                'success' => false,
                'message' => Language::get('Failed to create account')
            ];
        }

        return [
            'success' => true,
            'message' => Language::get('Account created successfully'),
            'user' => $newUser,
            'isNew' => true
        ];
    }

    /**
     * Find user by social ID
     *
     * @param string $provider
     * @param string $socialId
     *
     * @return object|null
     */
    public static function findBySocialId($provider, $socialId)
    {
        $providerId = self::PROVIDER_IDS[$provider] ?? 0;

        // For LINE, check line_uid column
        if ($provider === 'line') {
            return static::createQuery()
                ->select('*')
                ->from('user')
                ->where([
                    ['line_uid', $socialId],
                    ['social', $providerId]
                ])
                ->first();
        }

        // For others, store social ID in a specific pattern in username or use a social_id column
        // This implementation uses username pattern: {provider}_{socialId}
        $socialUsername = $provider.'_'.$socialId;

        $user = static::createQuery()
            ->select('*')
            ->from('user')
            ->where([
                ['username', $socialUsername],
                ['social', $providerId]
            ])
            ->first();

        return $user ?: null;
    }

    /**
     * Find user by email
     *
     * @param string $email
     *
     * @return object|null
     */
    public static function findByEmail($email)
    {
        return static::createQuery()
            ->select('*')
            ->from('user')
            ->where([['username', $email]])
            ->first();
    }

    /**
     * Create new user from social data
     *
     * @param string $provider
     * @param array $socialUser
     *
     * @return array|null
     */
    public static function createFromSocial($provider, array $socialUser)
    {
        $providerId = self::PROVIDER_IDS[$provider] ?? 0;
        $socialId = $socialUser['id'];

        // Determine username
        $username = $socialUser['email'] ?? ($provider.'_'.$socialId);

        // Ensure username uniqueness; if collision, append random suffix
        if (!\Index\UserRepository\Model::isFieldUnique('username', $username)) {
            $username = $provider.'_'.$socialId.'_'.substr(uniqid(), -6);
        }

        // Prepare user data
        $userData = [
            'username' => $username,
            'password' => '', // No password for social users
            'salt' => '',
            'name' => $socialUser['name'] ?? 'User',
            'phone' => '',
            'phone1' => '',
            'status' => 0,
            'active' => 1, // Social users are auto-activated
            'social' => $providerId,
            'activatecode' => '',
            'token' => null,
            'token_expires' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'avatar' => $socialUser['avatar'] ?? null,
            'permission' => '',
            'sex' => '',
            'id_card' => '',
            'birthday' => null,
            'website' => '',
            'company' => '',
            'icon' => '',
            'visited' => 0,
            'address' => '',
            'address2' => '',
            'provinceID' => 0,
            'province' => '',
            'zipcode' => '',
            'country' => 'TH',
            'tax_id' => '',
            'line_uid' => ($provider === 'line') ? $socialId : null
        ];

        // Insert user through the shared user aggregate owner
        $userId = \Index\UserRepository\Model::createUser($userData);

        if (!$userId) {
            return null;
        }

        $userData['id'] = $userId;

        // Log registration
        self::logSocialRegistration($userId, $provider);

        return self::sanitizeUserData((object) $userData);
    }

    /**
     * Link social account to existing user
     *
     * @param int $userId
     * @param string $provider
     * @param string $socialId
     * @param array $socialUser
     */
    public static function linkSocialAccount($userId, $provider, $socialId, array $socialUser)
    {
        $providerId = self::PROVIDER_IDS[$provider] ?? 0;

        $updateData = [
            'social' => $providerId
        ];

        // Store LINE UID in dedicated column
        if ($provider === 'line') {
            $updateData['line_uid'] = $socialId;
        }

        // Update avatar if not set
        if (!empty($socialUser['avatar'])) {
            $currentUser = static::createQuery()
                ->select('avatar')
                ->from('user')
                ->where([['id', $userId]])
                ->first();

            if ($currentUser && empty($currentUser->avatar)) {
                $updateData['avatar'] = $socialUser['avatar'];
            }
        }

        \Kotchasan\DB::create()->update('user', [['id', $userId]], $updateData);

        // Log linking
        self::logSocialLink($userId, $provider);
    }

    /**
     * Update social user info
     *
     * @param int $userId
     * @param array $socialUser
     */
    public static function updateSocialUserInfo($userId, array $socialUser)
    {
        $updateData = [];

        // Update avatar if changed
        if (!empty($socialUser['avatar'])) {
            $updateData['avatar'] = $socialUser['avatar'];
        }

        // Update name if changed and not manually set
        // (Optionally can be disabled via config)
        if (!empty($socialUser['name']) && empty(self::$cfg->preserve_user_name_on_social_login)) {
            // Only update if current name is generic
            $currentUser = static::createQuery()
                ->select('name')
                ->from('user')
                ->where([['id', $userId]])
                ->first();

            if ($currentUser && (empty($currentUser->name) || $currentUser->name === 'User')) {
                $updateData['name'] = $socialUser['name'];
            }
        }

        if (!empty($updateData)) {
            \Kotchasan\DB::create()->update('user', [['id', $userId]], $updateData);
        }
    }

    /**
     * Unlink social account
     *
     * @param int $userId
     * @param string $provider
     *
     * @return array
     */
    public static function unlinkSocialAccount($userId, $provider)
    {
        // Check if user has password set (can only unlink if has password)
        $user = static::createQuery()
            ->select('password', 'social')
            ->from('user')
            ->where([['id', $userId]])
            ->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => Language::get('No data available')
            ];
        }

        $providerId = self::PROVIDER_IDS[$provider] ?? 0;

        if ($user->social != $providerId) {
            return [
                'success' => false,
                'message' => Language::get('Account is not linked to this provider')
            ];
        }

        if (empty($user->password)) {
            return [
                'success' => false,
                'message' => Language::get('Please set a password before unlinking social account')
            ];
        }

        // Update user
        $updateData = ['social' => 0];
        if ($provider === 'line') {
            $updateData['line_uid'] = null;
        }

        \Kotchasan\DB::create()->update('user', [['id', $userId]], $updateData);

        return [
            'success' => true,
            'message' => Language::get('Social account unlinked successfully')
        ];
    }

    /**
     * Get linked social accounts for user
     *
     * @param int $userId
     *
     * @return array
     */
    public static function getLinkedAccounts($userId)
    {
        $user = static::createQuery()
            ->select('social', 'line_uid')
            ->from('user')
            ->where([['id', $userId]])
            ->first();

        if (!$user) {
            return [];
        }

        $linked = [];
        $providerNames = array_flip(self::PROVIDER_IDS);

        if ($user->social > 0 && isset($providerNames[$user->social])) {
            $linked[] = $providerNames[$user->social];
        }

        return $linked;
    }

    /**
     * Sanitize user data for response
     *
     * @param object $user
     *
     * @return array
     */
    private static function sanitizeUserData($user)
    {
        $data = is_array($user) ? $user : (array) $user;

        return [
            'id' => $data['id'] ?? null,
            'username' => $data['username'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['username'] ?? null, // username is email
            'avatar' => $data['avatar'] ?? null,
            'status' => $data['status'] ?? 0,
            'active' => $data['active'] ?? 1
        ];
    }

    /**
     * Log social registration
     *
     * @param int $userId
     * @param string $provider
     */
    private static function logSocialRegistration($userId, $provider)
    {
        try {
            if (class_exists('\\Index\\Log\\Model')) {
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                \Index\Log\Model::add($userId, 'index', 'Auth', ucfirst($provider)." Registration provider={$provider} IP={$clientIp}", $userId);
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Log social account linking
     *
     * @param int $userId
     * @param string $provider
     */
    private static function logSocialLink($userId, $provider)
    {
        try {
            if (class_exists('\\Index\\Log\\Model')) {
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                \Index\Log\Model::add($userId, 'index', 'Auth', ucfirst($provider)." Linking provider={$provider} IP={$clientIp}", $userId);
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
