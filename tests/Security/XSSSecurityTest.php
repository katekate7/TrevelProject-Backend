<?php
namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;

final class XSSSecurityTest extends TestCase
{
    public function testHtmlAndJsonEncoding(): void
    {
        // Array of malicious payloads we want to test against.
        // Each payload tries a different type of potential XSS injection.
        $payloads = [
            '<script>alert(1)</script>',      // Classic script tag injection
            '<img src=x onerror=alert(1)>',   // Image with onerror JavaScript event
            '"><img src=x onerror=alert(1)>', // Attribute escape + malicious image
        ];

        foreach ($payloads as $p) {
            // -------------------------------------------
            // 1) Test HTML escaping using htmlspecialchars()
            // -------------------------------------------
            // Convert special characters to HTML entities.
            // This prevents browsers from interpreting them as HTML or JavaScript.
            $html = htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            // Verify that the escaped HTML contains no raw "<" character.
            // If "<" exists, it might allow HTML/JS injection.
            $this->assertStringNotContainsString('<', $html);

            // Verify that the escaped HTML contains no raw ">" character.
            // This prevents any closing tag from being interpreted by the browser.
            $this->assertStringNotContainsString('>', $html);

            // -------------------------------------------
            // 2) Test JSON encoding with HEX escaping
            // -------------------------------------------
            // Convert the payload to JSON, escaping special characters with JSON_HEX_* flags.
            // This ensures that "<" and ">" are represented as Unicode escape sequences.
            $json = json_encode(['v' => $p], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            // Ensure the result is a valid string.
            $this->assertIsString($json);

            // Verify that the encoded JSON does not contain raw "<" character.
            $this->assertStringNotContainsString('<', $json);

            // Verify that the encoded JSON does not contain raw ">" character.
            $this->assertStringNotContainsString('>', $json);
        }
    }
}
