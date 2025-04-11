<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

class CookieTokenExtractor implements TokenExtractorInterface
{
    private string $cookieName;

    public function __construct(string $cookieName = 'JWT')
    {
        $this->cookieName = $cookieName;
    }

    public function extract(Request $request): ?string
    {
        dump('Extractor працює ✅');
        dump($request->cookies->all());
        return $request->cookies->get($this->cookieName);    
    }

    public function hasToken(Request $request): bool
    {
        return $request->cookies->has($this->cookieName);
    }
}
