<?php

namespace App\Tests\Entity;

use App\Entity\Item;
use PHPUnit\Framework\TestCase;

final class ItemTest extends TestCase
{
    public function testItemCanBeCreatedWithName(): void
    {
        $item = new Item();
        $item->setName('Passport');

        $this->assertEquals('Passport', $item->getName());
        $this->assertFalse($item->isImportant());
    }

    public function testItemCanBeSetAsImportant(): void
    {
        $item = new Item();
        $item->setName('Passport');
        $item->setImportant(true);

        $this->assertEquals('Passport', $item->getName());
        $this->assertTrue($item->isImportant());
    }
}
