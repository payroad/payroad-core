<?php

namespace Tests\Domain\Attempt;

use Payroad\Domain\Attempt\AttemptStatus;
use PHPUnit\Framework\TestCase;

final class AttemptStatusTest extends TestCase
{
    /**
     * @dataProvider terminalStatusProvider
     */
    public function testIsTerminalReturnsTrueForTerminalStatuses(AttemptStatus $status): void
    {
        $this->assertTrue($status->isTerminal());
    }

    public static function terminalStatusProvider(): array
    {
        return [
            'SUCCEEDED' => [AttemptStatus::SUCCEEDED],
            'FAILED'    => [AttemptStatus::FAILED],
            'CANCELED'  => [AttemptStatus::CANCELED],
            'EXPIRED'   => [AttemptStatus::EXPIRED],
        ];
    }

    /**
     * @dataProvider nonTerminalStatusProvider
     */
    public function testIsTerminalReturnsFalseForNonTerminalStatuses(AttemptStatus $status): void
    {
        $this->assertFalse($status->isTerminal());
    }

    public static function nonTerminalStatusProvider(): array
    {
        return [
            'PENDING'               => [AttemptStatus::PENDING],
            'PROCESSING'            => [AttemptStatus::PROCESSING],
            'AWAITING_CONFIRMATION' => [AttemptStatus::AWAITING_CONFIRMATION],
        ];
    }

    public function testSucceededIsSuccess(): void
    {
        $this->assertTrue(AttemptStatus::SUCCEEDED->isSuccess());
    }

    public function testSucceededIsNotFailure(): void
    {
        $this->assertFalse(AttemptStatus::SUCCEEDED->isFailure());
    }

    public function testFailedIsNotSuccess(): void
    {
        $this->assertFalse(AttemptStatus::FAILED->isSuccess());
    }

    public function testFailedIsFailure(): void
    {
        $this->assertTrue(AttemptStatus::FAILED->isFailure());
    }

    public function testCanceledIsFailure(): void
    {
        $this->assertTrue(AttemptStatus::CANCELED->isFailure());
    }

    public function testCanceledIsNotSuccess(): void
    {
        $this->assertFalse(AttemptStatus::CANCELED->isSuccess());
    }

    public function testExpiredIsFailure(): void
    {
        $this->assertTrue(AttemptStatus::EXPIRED->isFailure());
    }

    public function testExpiredIsNotSuccess(): void
    {
        $this->assertFalse(AttemptStatus::EXPIRED->isSuccess());
    }

    public function testPendingIsNotSuccess(): void
    {
        $this->assertFalse(AttemptStatus::PENDING->isSuccess());
    }

    public function testPendingIsNotFailure(): void
    {
        $this->assertFalse(AttemptStatus::PENDING->isFailure());
    }

    public function testProcessingIsNotSuccess(): void
    {
        $this->assertFalse(AttemptStatus::PROCESSING->isSuccess());
    }

    public function testProcessingIsNotFailure(): void
    {
        $this->assertFalse(AttemptStatus::PROCESSING->isFailure());
    }

    public function testAwaitingConfirmationIsNotTerminal(): void
    {
        $this->assertFalse(AttemptStatus::AWAITING_CONFIRMATION->isTerminal());
    }
}
