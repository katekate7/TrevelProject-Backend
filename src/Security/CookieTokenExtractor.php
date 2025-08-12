<?php
/**
 * Cookie Token Extractor
 * 
 * This class extracts JWT tokens from HTTP cookies, providing a more secure
 * alternative to storing tokens in local storage or sending them in headers.
 * It's designed to work with the LexikJWTAuthenticationBundle.
 * 
 * @package App\Security
 * @author Travel Project Team
 */

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts JWT tokens from HTTP cookies
 * 
 * This extractor looks for JWT tokens in cookies instead of the Authorization header,
 * which helps protect against XSS attacks that could steal tokens from localStorage.
 * Cookie-based tokens can be made httpOnly and secure for additional protection.
 */
class CookieTokenExtractor implements TokenExtractorInterface
{
    /**
     * The name of the cookie that contains the JWT token
     *
     * @var string
     */
    private string $cookieName;

    /**
     * Constructor to set the cookie name
     * 
     * @param string $cookieName The name of the cookie containing the JWT token (defaults to 'JWT')
     */
    public function __construct(string $cookieName = 'JWT')
    {
        $this->cookieName = $cookieName;
    }

    /**
     * Extract the JWT token from the request cookies
     * 
     * This method retrieves the JWT token from the specified cookie if present.
     * It's called by the authentication system during token authentication.
     *
     * @param Request $request The HTTP request
     * @return string|null The JWT token if found, null otherwise
     */
    public function extract(Request $request): ?string
    {
        // Return the token from the cookie
        return $request->cookies->get($this->cookieName);    
    }

    /**
     * Check if the request contains a JWT token in cookies
     * 
     * This method determines if a JWT token exists in the specified cookie.
     * It's used to decide whether this extractor should be used for a given request.
     *
     * @param Request $request The HTTP request
     * @return bool True if the token exists in cookies, false otherwise
     */
    public function hasToken(Request $request): bool
    {
        return $request->cookies->has($this->cookieName);
    }
}
