<?php

namespace Tests\Unit;

use App\Services\RecurrenceEngine;
use Tests\TestCase;

class RecurrenceEngineTest extends TestCase
{
    public function testDailyRecurrence(): void
    {
        $base = '2026-08-01 10:00:00';
        $next = RecurrenceEngine::calculateNextDate('daily', $base);
        $this->assertEquals('2026-08-02 10:00:00', $next);
    }

    public function testWeeklyRecurrence(): void
    {
        $base = '2026-08-01 10:00:00';
        $next = RecurrenceEngine::calculateNextDate('weekly', $base);
        $this->assertEquals('2026-08-08 10:00:00', $next);
    }

    public function testMonthlyRecurrence(): void
    {
        $base = '2026-08-01 10:00:00';
        $next = RecurrenceEngine::calculateNextDate('monthly', $base);
        $this->assertEquals('2026-09-01 10:00:00', $next);
    }

    public function testIntervalRecurrence(): void
    {
        $base = '2026-08-01 10:00:00';
        $next = RecurrenceEngine::calculateNextDate('interval:5', $base);
        $this->assertEquals('2026-08-06 10:00:00', $next);
    }
}
