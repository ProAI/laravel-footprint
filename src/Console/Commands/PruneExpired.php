<?php

namespace ProAI\Footprint\Console\Commands;

use Illuminate\Console\Command;
use ProAI\Footprint\Contracts\UserSessionRepository;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'footprint:prune-expired',
    description: 'Prune expired user sessions'
)]
class PruneExpired extends Command
{
    /**
     * Execute the console command.
     *
     * @param  \ProAI\Footprint\Contracts\UserSessionRepository  $repository
     * @return int
     */
    public function handle(UserSessionRepository $repository): int
    {
        /** @var int $retention */
        $retention = config('footprint.expired_session_retention', 1440);

        $this->components->task(
            'Pruning expired user sessions',
            fn () => $repository->deleteExpired($retention)
        );

        $this->components->info('Expired user sessions pruned successfully.');

        return 0;
    }
}
