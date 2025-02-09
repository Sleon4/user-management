<?php

namespace Tests;

use Lion\Test\Test;
use PHPUnit\Framework\Attributes\Test as Testing;

class ExampleTest extends Test
{
    #[Testing]
    public function example(): void
    {
        $this->assertTrue(true);
    }
}