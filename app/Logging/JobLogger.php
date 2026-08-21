<?php

namespace App\Logging;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The queue's half of the request logger.
 *
 * A job has no request around it, so nothing would otherwise say which job a
 * line came from, or how long the work took. This listens to the queue's own
 * events and does for a job exactly what LogRequests does for a request: puts
 * the identity into Context, so every line the job writes carries it, and emits
 * one line when the work ends.
 *
 * `request_id` needs no help here. Laravel packs the context into the job
 * payload when it is queued and hydrates it in the worker, so a job dispatched
 * by a request is already logging under that request's id -- in another
 * container, minutes later.
 */
class JobLogger
{
    /**
     * When each job in flight started, keyed by its uuid. A worker handles one
     * job at a time, but a uuid key costs nothing and keeps this honest if that
     * ever stops being true.
     *
     * @var array<string, float>
     */
    private array $started = [];

    /**
     * A job is about to run.
     */
    public function starting(JobProcessing $event): void
    {
        $this->started[$this->key($event->job)] = microtime(true);

        /**
         * Added after Laravel has hydrated the context out of the payload --
         * framework providers boot before application ones -- so this sits
         * alongside the request_id the job inherited rather than replacing it.
         */
        Context::add($this->identity($event->job));
    }

    /**
     * A job finished cleanly.
     */
    public function finished(JobProcessed $event): void
    {
        Log::info('job', [
            ...$this->identity($event->job),
            'outcome' => 'processed',
            'duration_ms' => $this->duration($event->job),
        ]);
    }

    /**
     * A job gave up: this is the terminal failure, after any retries.
     */
    public function failed(JobFailed $event): void
    {
        Log::error('job', [
            ...$this->identity($event->job),
            'outcome' => 'failed',
            'duration_ms' => $this->duration($event->job),
            'error' => $this->error($event->exception),
        ]);
    }

    /**
     * One attempt threw.
     *
     * Deliberately its own message rather than another `job` line: an attempt
     * that will be retried is not an outcome, and counting `job` lines should
     * count finished work. A job that fails for good produces both -- an
     * attempt line and a `job` line saying failed -- which is what actually
     * happened.
     */
    public function attemptFailed(JobExceptionOccurred $event): void
    {
        Log::warning('job attempt failed', [
            ...$this->identity($event->job),
            'error' => $this->error($event->exception),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(Job $job): array
    {
        return [
            'job' => $job->resolveName(),
            'job_id' => $this->key($job),
            'queue' => $job->getQueue(),
            'connection' => $job->getConnectionName(),
            'attempt' => $job->attempts(),
        ];
    }

    /**
     * How long the attempt took, and forget it -- a retry starts its own clock.
     */
    private function duration(Job $job): ?float
    {
        $key = $this->key($job);
        $startedAt = $this->started[$key] ?? null;

        unset($this->started[$key]);

        return $startedAt === null ? null : round((microtime(true) - $startedAt) * 1000, 2);
    }

    private function key(Job $job): string
    {
        return $job->uuid() ?? (string) $job->getJobId();
    }

    /**
     * @return array<string, string>
     */
    private function error(Throwable $exception): array
    {
        return [
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile().':'.$exception->getLine(),
        ];
    }
}
