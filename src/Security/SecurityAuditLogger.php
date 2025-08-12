<?php
/**
 * Security Audit Logger
 * 
 * This service provides comprehensive security event logging functionality,
 * creating an audit trail of all security-related activities within the application.
 * It helps with security monitoring, incident response, and compliance requirements.
 * 
 * @package App\Security
 * @author Travel Project Team
 */

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security audit logger for tracking security events
 * 
 * This class records various security events such as login attempts,
 * password changes, access control decisions, and sensitive data access.
 * Each log entry includes relevant contextual information like user identifiers,
 * IP addresses, and timestamps to support security auditing and investigation.
 */
class SecurityAuditLogger
{
    /**
     * Constructor to inject the dedicated security logger service
     * 
     * @param LoggerInterface $securityLogger A dedicated logger instance for security events
     */
    public function __construct(
        private LoggerInterface $securityLogger
    ) {
    }

    /**
     * Log successful login
     * 
     * Records a successful authentication event with user identification,
     * IP address, and browser information. This helps establish a baseline
     * of normal user login behavior for security monitoring.
     * 
     * @param UserInterface $user The user who successfully logged in
     * @param Request $request The current HTTP request
     * @return void
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
     * 
     * Records unsuccessful authentication attempts, which may indicate
     * brute force attacks or credential stuffing. These logs are essential
     * for detecting potential security breaches.
     * 
     * @param string $username The username that failed authentication
     * @param Request $request The current HTTP request
     * @return void
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
     * 
     * Records user logout events to complete the authentication lifecycle tracking.
     * This helps identify unusual session durations or unexpected terminations.
     * 
     * @param UserInterface $user The user who is logging out
     * @param Request $request The current HTTP request
     * @return void
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
     * 
     * Records when a user requests a password reset. This is important for
     * tracking account recovery attempts and potential account takeover attacks.
     * 
     * @param string $email The email address for which the reset was requested
     * @param Request $request The current HTTP request
     * @return void
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
     * 
     * Records successful password changes. This is critical for monitoring 
     * account security changes and can help identify unauthorized access
     * if a password change occurs from an unusual location.
     * 
     * @param UserInterface $user The user who changed their password
     * @param Request $request The current HTTP request
     * @return void
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
     * 
     * Records authorization failures when a user attempts to access a resource
     * they don't have permission for. These logs help identify potential privilege
     * escalation attempts or misconfigurations in access controls.
     * 
     * @param TokenInterface $token The security token containing user information
     * @param string $resource The resource that access was denied for
     * @param Request $request The current HTTP request
     * @return void
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
     * 
     * Records when users access particularly sensitive information in the system.
     * This is important for privacy compliance (such as GDPR) and for detecting
     * potential data exfiltration attempts or misuse of privileged access.
     * 
     * @param UserInterface $user The user accessing the sensitive data
     * @param string $dataType The type or category of sensitive data being accessed
     * @param string $dataId The identifier of the specific data record
     * @param Request $request The current HTTP request
     * @return void
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
     * 
     * Records when a rate limit is triggered, which may indicate an automated attack,
     * API abuse, or a misbehaving client application. These logs help identify
     * sources of potential denial-of-service attacks or credential stuffing.
     * 
     * @param string $identifier The identifier that exceeded the rate limit (username, IP)
     * @param string $limiterName The name of the rate limiter that was triggered
     * @param Request $request The current HTTP request
     * @return void
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
