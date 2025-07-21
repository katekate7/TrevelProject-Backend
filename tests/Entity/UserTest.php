<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testUserCanBeCreatedWithBasicInfo(): void
    {
        $user = new User();
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');
        $user->setRole('user');

        $this->assertEquals('john_doe', $user->getUsername());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals('user', $user->getRole());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    public function testAdminUserHasCorrectRoles(): void
    {
        $user = new User();
        $user->setUsername('admin_user');
        $user->setEmail('admin@example.com');
        $user->setRole('admin');

        $this->assertEquals('admin', $user->getRole());
        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
    }
}
