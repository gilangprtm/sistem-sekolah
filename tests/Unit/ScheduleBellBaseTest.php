<?php

namespace Tests\Unit;

use Tests\TestCase;

class ScheduleBellBaseTest extends TestCase
{
    public function test_monday_to_thursday_has_exact_lessons_and_breaks(): void
    {
        $this->assertSame(40, config('schedule.lesson_duration_minutes'));
        $this->assertSame(20, config('schedule.break_duration_minutes'));
        $this->assertSame('07:30', config('schedule.start_time'));
        $this->assertSame([
            ['type' => 'lesson', 'number' => 1, 'start' => '07:30', 'end' => '08:10'],
            ['type' => 'lesson', 'number' => 2, 'start' => '08:10', 'end' => '08:50'],
            ['type' => 'lesson', 'number' => 3, 'start' => '08:50', 'end' => '09:30'],
            ['type' => 'break', 'start' => '09:30', 'end' => '09:50'],
            ['type' => 'lesson', 'number' => 4, 'start' => '09:50', 'end' => '10:30'],
            ['type' => 'lesson', 'number' => 5, 'start' => '10:30', 'end' => '11:10'],
            ['type' => 'lesson', 'number' => 6, 'start' => '11:10', 'end' => '11:50'],
            ['type' => 'break', 'start' => '11:50', 'end' => '12:10'],
            ['type' => 'lesson', 'number' => 7, 'start' => '12:10', 'end' => '12:50'],
            ['type' => 'lesson', 'number' => 8, 'start' => '12:50', 'end' => '13:30'],
            ['type' => 'lesson', 'number' => 9, 'start' => '13:30', 'end' => '14:10'],
        ], config('schedule.weekdays.monday_thursday'));
    }

    public function test_friday_has_exact_lessons_and_breaks(): void
    {
        $this->assertSame([
            ['type' => 'lesson', 'number' => 1, 'start' => '07:30', 'end' => '08:10'],
            ['type' => 'lesson', 'number' => 2, 'start' => '08:10', 'end' => '08:50'],
            ['type' => 'lesson', 'number' => 3, 'start' => '08:50', 'end' => '09:30'],
            ['type' => 'break', 'start' => '09:30', 'end' => '09:50'],
            ['type' => 'lesson', 'number' => 4, 'start' => '09:50', 'end' => '10:30'],
            ['type' => 'lesson', 'number' => 5, 'start' => '10:30', 'end' => '11:10'],
            ['type' => 'break', 'start' => '11:10', 'end' => '11:30'],
            ['type' => 'lesson', 'number' => 6, 'start' => '11:30', 'end' => '12:10'],
            ['type' => 'lesson', 'number' => 7, 'start' => '12:10', 'end' => '12:50'],
        ], config('schedule.weekdays.friday'));
    }
}
