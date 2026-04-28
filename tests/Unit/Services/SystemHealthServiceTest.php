<?php

namespace Tests\Unit\Services;

use App\Services\SystemHealthService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SystemHealthServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSummaryReturnsConnectedStatusWhenPdoIsAvailable(): void
    {
        $service = new SystemHealthService();
        $summary = $service->summary();

        $this->assertSame(config('app.name'), $summary['app']);
        $this->assertSame(app()->environment(), $summary['environment']);
        $this->assertTrue($summary['database']['connected']);
        $this->assertSame(config('database.default'), $summary['database']['driver']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testSummaryReportsDisconnectedWhenPdoFails(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getPdo')->andThrow(new \RuntimeException('connection refused'));

        DB::shouldReceive('connection')->andReturn($connection);

        $service = new SystemHealthService();
        $summary = $service->summary();

        $this->assertFalse($summary['database']['connected']);
        $this->assertSame('connection refused', $summary['database']['error']);
    }
}
