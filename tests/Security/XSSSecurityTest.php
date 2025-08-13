<?php
namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;

final class XSSSecurityTest extends TestCase
{
    public function testBasicHtmlEscaping(): void
    {
        // List of malicious payloads to test
        $payloads = [
            '<script>alert(1)</script>',      // Classic script injection
            '<img src=x onerror=alert(1)>',   // Image tag with JavaScript event
            '"><img src=x onerror=alert(1)>', // Attribute break + malicious image
        ];

        foreach ($payloads as $p) {
            // Escape HTML special characters to prevent execution
            $escaped = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');

            // Assert that escaped output contains no raw "<"
            $this->assertStringNotContainsString('<', $escaped);

            // Assert that escaped output contains no raw ">"
            $this->assertStringNotContainsString('>', $escaped);
        }
    }
}
