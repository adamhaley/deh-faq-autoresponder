<?php

namespace Tests\Unit;

use App\Jobs\ComposeEmailThreadDraft;
use App\Jobs\GenerateEmailQuestionAnswerDraft;
use App\Jobs\RetrieveEmailQuestionFaqMatches;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\RateLimited;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueueHardeningTest extends TestCase
{
    /**
     * @param  class-string  $jobClass
     */
    #[DataProvider('pipelineJobs')]
    public function test_pipeline_jobs_are_unique_and_retry_with_backoff(string $jobClass, array $arguments, string $uniqueId): void
    {
        $job = new $jobClass(...$arguments);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame(0, $job->tries);
        $this->assertSame(120, $job->timeout);
        $this->assertSame(600, $job->uniqueFor);
        $this->assertSame([5, 30, 120], $job->backoff());
        $this->assertSame($uniqueId, $job->uniqueId());
        $this->assertGreaterThan(now(), $job->retryUntil());
    }

    /**
     * @param  class-string  $jobClass
     */
    #[DataProvider('pipelineJobs')]
    public function test_pipeline_jobs_use_rate_limit_middleware(string $jobClass, array $arguments): void
    {
        $job = new $jobClass(...$arguments);
        $middleware = $job->middleware();

        $this->assertContainsOnlyInstancesOf(RateLimited::class, $middleware);
        $this->assertNotEmpty($middleware);
    }

    /**
     * @return array<string, array{0: class-string, 1: array<int, mixed>, 2: string}>
     */
    public static function pipelineJobs(): array
    {
        return [
            'retrieve faq matches' => [RetrieveEmailQuestionFaqMatches::class, [123], '123'],
            'generate answer draft' => [GenerateEmailQuestionAnswerDraft::class, [456], '456'],
            'compose thread draft' => [ComposeEmailThreadDraft::class, ['thread-789'], 'thread-789'],
        ];
    }
}
