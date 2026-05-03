<?php
/**
 * @filesource modules/index/controllers/social.php
 *
 * Social Login Controller - OAuth Integration
 *
 * Supports:
 * - Google OAuth 2.0
 * - Facebook Login
 * - Telegram Login
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Index\Social;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * Social Authentication Controller
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET /index/social
     * Handle social login requests
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        try {
            $this->validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            // Check rate limiting (same as registration)
            $clientIp = $request->getClientIp();
            if (!\Index\Register\Model::checkRegistrationRateLimit($clientIp)) {
                return $this->errorResponse('Please wait a moment before trying again', 429);
            }

            $provider = $request->post('provider')->filter('a-z');

            if ($provider === 'google') {
                return $this->google($request);
            } elseif ($provider === 'facebook') {
                return $this->facebook($request);
            } elseif ($provider === 'telegram') {
                return $this->telegram($request);
            }
            return $this->errorResponse('Invalid social provider '.$provider, 400);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to initiate social login: '.$e->getMessage(), 500);
        }
    }

    /**
     * Handle Google login
     *
     * @param Request $request
     */
    private function google(Request $request)
    {
        $data = [
            'username' => $request->post('username')->email(),
            'name' => $request->post('name')->topic(),
            'picture' => $request->post('picture')->url(),
            'social' => 'google'
        ];

        return $this->handleSocialLogin(
            $request,
            'Google',
            $data,
            $request->post('intended_url')->toString()
        );
    }

    /**
     * Handle Facebook login
     *
     * @param Request $request
     */
    private function facebook(Request $request)
    {
        $data = [
            'username' => $request->post('username')->number(),
            'name' => $request->post('name')->topic(),
            'picture' => $request->post('picture')->url(),
            'social' => 'facebook'
        ];

        return $this->handleSocialLogin(
            $request,
            'Facebook',
            $data,
            $request->post('intended_url')->toString()
        );
    }

    /**
     * Handle Telegram login
     *
     * @param Request $request
     */
    private function telegram(Request $request)
    {
        $data = [
            'username' => $request->post('username')->username(),
            'name' => $request->post('name')->topic(),
            'picture' => $request->post('picture')->url(),
            'telegram_id' => $request->post('id')->number(),
            'social' => 'telegram'
        ];

        return $this->handleSocialLogin(
            $request,
            'Telegram',
            $data,
            $request->post('intended_url')->toString()
        );

    }

    /**
     * Shared social login handler
     *
     * @param Request $request
     * @param string $provider Provider name (for logging)
     * @param array $data
     * @param string $intended_url Intended URL after login
     *
     * @return mixed
     */
    private function handleSocialLogin(Request $request, $provider, $data, $intended_url)
    {
        // Check if user already exists
        $existingUser = \Index\UserRepository\Model::findByUsername($data['username']);

        if ($existingUser) {
            // Verify social provider matches
            if ($existingUser->social === $data['social']) {
                return $this->loginExistingUser($existingUser, $provider, $intended_url, $request);
            }

            return $this->errorResponse('An account with this email already exists. Please use email login.', 401);
        }

        // New user - register via social login
        return $this->registerNewSocialUser($data, $intended_url);
    }

    /**
     * Login existing social user
     *
     * @param object $existingUser
     * @param string $provider
     * @param string $intended_url
     * @param Request $request
     *
     * @return mixed
     */
    private function loginExistingUser($existingUser, string $provider, string $intended_url, Request $request)
    {
        // Generate tokens for login
        $loginResult = \Index\Auth\Model::generateTokens($existingUser->id);
        if (!$loginResult) {
            return $this->errorResponse('Login failed', 401);
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
            $provider.' Sign-in: '.$existingUser->username,
            $clientIp
        );

        // Set auth cookie for frontend
        \Index\Auth\Model::setCookie('auth_token', $loginResult['access_token']);

        // Sanitize user data for response (remove sensitive fields)
        $user = \Index\Auth\Model::sanitizeUserData($existingUser);

        // Return standardized response with actions
        return $this->successResponse([
            'user' => $user,
            'token' => $loginResult['access_token'],
            'refresh_token' => $loginResult['refresh_token'],
            'expires_in' => \Index\Auth\Model::TOKEN_EXPIRY,
            'token_type' => 'Bearer',
            'actions' => [
                [
                    'type' => 'notification',
                    'message' => 'Login successful'
                ],
                [
                    'type' => 'redirect',
                    'url' => $intended_url
                ]
            ]
        ], 'Login successful');
    }

    /**
     * Register new social user
     *
     * @param array $data
     * @param string $intended_url
     *
     * @return mixed
     */
    private function registerNewSocialUser($data, string $intended_url)
    {
        // Validate social data
        $result = \Index\Register\Model::registerFromSocial($data, $intended_url);
        if (!$result['success']) {
            return $this->errorResponse($result['message'], $result['code'] ?? 400);
        }

        // Set auth cookie for new user
        if (!empty($result['token'])) {
            \Index\Auth\Model::setCookie('auth_token', $result['token']);
        }

        // Return standardized response with actions
        return $this->successResponse([
            'user' => $result['user'],
            'token' => $result['token'],
            'actions' => [
                [
                    'type' => 'notification',
                    'message' => $result['message'],
                    'variant' => 'success'
                ],
                [
                    'type' => 'redirect',
                    'url' => $intended_url ?: '/'
                ]
            ]
        ], $result['message']);
    }
}
