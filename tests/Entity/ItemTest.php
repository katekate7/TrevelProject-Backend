<?php
/**
 * Test suite for Item entity functionality.
 * 
 * This test class validates the Item entity's behavior including:
 * - Basic item creation and property management
 * - Importance flag handling
 * - Default value behavior
 * 
 * @package App\Tests\Entity
 * @author Travel Project Team
 */

namespace App\Tests\Entity;

use App\Entity\Item;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Item entity.
 * 
 * Tests cover item creation, property setting, and importance
 * flag management to ensure proper entity behavior.
 */
final class ItemTest extends TestCase
{
    /**
     * Test basic item creation with name property.
     * 
     * Verifies that:
     * - Item can be created and name can be set
     * - Default importance flag is false
     * - Name property is retrieved correctly
     */
    public function testItemCanBeCreatedWithName(): void
    {
        // Arrange & Act: Create item with name
        $item = new Item();
        $item->setName('Passport');

        // Assert: Verify name is set and default importance is false
        $this->assertEquals('Passport', $item->getName());
        $this->assertFalse($item->isImportant()); // Default should be false
    }

    /**
     * Test item importance flag functionality.
     * 
     * Verifies that:
     * - Importance flag can be set to true
     * - Both name and importance properties work together
     * - Boolean flag behavior is correct
     */
    public function testItemCanBeSetAsImportant(): void
    {
        // Arrange & Act: Create item and mark as important
        $item = new Item();
        $item->setName('Passport');
        $item->setImportant(true);

        // Assert: Verify both name and importance flag are set correctly
        $this->assertEquals('Passport', $item->getName());
        $this->assertTrue($item->isImportant()); // Should be true when explicitly set
    }
}
