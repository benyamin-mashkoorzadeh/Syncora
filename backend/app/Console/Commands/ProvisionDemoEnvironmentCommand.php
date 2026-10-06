<?php

namespace App\Console\Commands;

use App\Actions\Demo\ProvisionDemoEnvironment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:provision {--reset : Restore the configured demo workspace to its seeded baseline}')]
#[Description('Provision or restore the configured Syncora portfolio demo')]
class ProvisionDemoEnvironmentCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProvisionDemoEnvironment $provisionDemoEnvironment): int
    {
        $workspace = $provisionDemoEnvironment->handle((bool) $this->option('reset'));

        $this->components->info(
            ($this->option('reset') ? 'Restored' : 'Provisioned')." demo workspace [{$workspace->slug}].",
        );

        return self::SUCCESS;
    }
}
