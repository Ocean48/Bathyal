<?php

namespace Tests\Unit;

use App\Core\Database;
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

    public function testRescheduleFromTaskCalculation(): void
    {
        // Setup mock task dates: Task 1 (start today, due tomorrow)
        $now = date('Y-m-d H:i:s');
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        Database::execute("UPDATE tasks SET start_date = :s, due_date = :d WHERE id = 1", [
            's' => $now,
            'd' => $tomorrow,
        ]);

        $rescheduled = AutoScheduler::rescheduleFromTask(1);
        $this->assertNotNull($rescheduled);
        $this->assertTrue(is_array($rescheduled));
    }
}

