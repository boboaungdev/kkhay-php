<?php

namespace Kkhay\Tests;

use PHPUnit\Framework\TestCase;
use Kkhay\Client;
use InvalidArgumentException;

class ClientTest extends TestCase
{
    public function testConstructValid(): void
    {
        $client = new Client('kkhay_live_test_key');
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testConstructEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Client('');
    }
}

