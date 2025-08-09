<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security audit logger for tracking security events
 */
class SecurityAuditLogger
{
    public function __construct(
        private LoggerInterface $securityLogger
    ) {
    }

    /**
     * Log successful login
     */
    public function logLogin(UserInterface $user, Request $request): void
    {
        $this->securityLogger->info('User logged in successfully', [
            'user_id' => $user->getUserIdentifier(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'event' => 'login_success'
        ]);
    }

    /**
     * Log failed login
     */
    public function logLoginFailure(string $username, Request $request): void
    {
        $this->securityLogger->warning('Failed login attempt', [
            'username' => $username,
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'event' => 'login_failure'
        ]);
    }

    /**
     * Log logout
     */
    public function logLogout(UserInterface $user, Request $request): void
    {
        $this->securityLogger->info('User logged out', [
            'user_id' => $user->getUserIdentifier(),
            'ip' => $request->getClientIp(),
            'event' => 'logout'
        ]);
    }

    /**
     * Log password reset request
     */
    public function logPasswordResetRequest(string $email, Request $request): void
    {
        $this->securityLogger->info('Password reset requested', [
            'email' => $email,
            'ip' => $request->getClientIp(),
            'event' => 'password_reset_request'
        ]);
    }

    /**
     * Log password change
     */
    public function logPasswordChange(UserInterface $user, Request $request): void
    {
        $this->securityLogger->info('Password changed', [
            'user_id' => $user->getUserIdentifier(),
            'ip' => $request->getClientIp(),
            'event' => 'password_change'
        ]);
    }

    /**
     * Log access denied event
     */
    public function logAccessDenied(TokenInterface $token, string $resource, Request $request): void
    {
        $user = $token->getUser();
        $username = $user instanceof UserInterface ? $user->getUserIdentifier() : (string) $user;
        
        $this->securityLogger->warning('Access denied', [
            'user' => $username,
            'resource' => $resource,
            'ip' => $request->getClientIp(),
            'event' => 'access_denied'
        ]);
    }

    /**
     * Log sensitive data access
     */
    public function logSensitiveDataAccess(UserInterface $user, string $dataType, string $dataId, Request $request): void
    {
        $this->securityLogger->info('Sensitive data accessed', [
            'user_id' => $user->getUserIdentifier(),
            'data_type' => $dataType,
            'data_id' => $dataId,
            'ip' => $request->getClientIp(),
            'event' => 'sensitive_data_access'
        ]);
    }
    
    /**
     * Log rate limit exceeded
     */
    public function logRateLimitExceeded(string $identifier, string $limiterName, Request $request): void
    {
        $this->securityLogger->warning('Rate limit exceeded', [
            'identifier' => $identifier,
            'limiter' => $limiterName,
            'ip' => $request->getClientIp(),
            'event' => 'rate_limit_exceeded'
        ]);
    }
}
