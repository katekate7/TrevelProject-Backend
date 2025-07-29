<?php
/**
 * Test suite for User entity functionality.
 * 
 * This test class validates the User entity's behavior including:
 * - Basic property setting and getting
 * - Role-based access control
 * - Symfony security role mapping
 * 
 * @package App\Tests\Entity
 * @author Travel Project Team
 */

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for User entity.
 * 
 * Tests cover user creation, property management, and role handling
 * to ensure the User entity behaves correctly in all scenarios.
 */
final class UserTest extends TestCase
{
    /**
     * Test basic user creation and property setting.
     * 
     * Verifies that:
     * - User can be created with basic information
     * - Properties are set and retrieved correctly
     * - Default role behavior works as expected
     * - Symfony role mapping functions properly
     */
    public function testUserCanBeCreatedWithBasicInfo(): void
    {
        // Arrange & Act: Create user with basic information
        $user = new User();
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setRole('user');

        // Assert: Verify all properties are set correctly
        $this->assertEquals('john_doe', $user->getUsername());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals('user', $user->getRole());
        // Verify Symfony security role mapping for regular users
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    /**
     * Test admin user role handling and permissions.
     * 
     * Verifies that:
     * - Admin role is set correctly
     * - Admin users get proper Symfony security roles
     * - Role hierarchy is respected (admin includes user permissions)
     */
    public function testAdminUserHasCorrectRoles(): void
    {
        // Arrange & Act: Create admin user
        $user = new User();
        $user->setUsername('admin_user');
        $user->setEmail('admin@example.com');
        $user->setRole('admin');

        // Assert: Verify admin role and Symfony security roles
        $this->assertEquals('admin', $user->getRole());
        // Admin should have both ROLE_USER and ROLE_ADMIN
        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
    }
}
