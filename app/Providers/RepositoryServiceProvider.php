<?php

namespace App\Providers;

use App\Repositories\Contracts\LoginAttemptRepositoryInterface;
use App\Repositories\Contracts\UserLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserLogRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Every repository interface and the implementation it resolves to.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        UserLogRepositoryInterface::class => UserLogRepository::class,
        LoginAttemptRepositoryInterface::class => LoginAttemptRepository::class,
    ];
}
