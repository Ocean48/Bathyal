<?php

namespace Tests\Unit;

use App\Services\AutoScheduler;
use Tests\TestCase;

class AutoSchedulerTest extends TestCase
{
    public function testCycleDetectionSelfLoop(): void
    {
        $this->assertTrue(AutoScheduler::wouldCreateCycle(1, 1));
    }

    public function testNonCyclicDependency(): void
    {
        $this->assertFalse(AutoScheduler::wouldCreateCycle(999, 1000));
    }
}
