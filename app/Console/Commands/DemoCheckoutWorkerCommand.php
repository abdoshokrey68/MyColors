<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Order\Services\CheckoutService;
use Throwable;

class DemoCheckoutWorkerCommand extends Command
{
    protected $signature = 'demo:checkout-worker
                            {userId : The user ID to check out}
                            {--start-at= : Unix timestamp (float) when checkout should begin}
                            {--promotion= : Optional promotion code}';

    protected $description = 'Run a single CheckoutService::checkout() on its own process/DB connection.';

    public function handle(CheckoutService $checkout): int
    {
        $user = User::query()->find($this->argument('userId'));

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $startAt = $this->option('start-at');

        if ($startAt !== null && $startAt !== '') {
            $target = (float) $startAt;

            while (microtime(true) < $target) {
                usleep(1000);
            }
        }

        try {
            $order = $checkout->checkout(
                $user,
                $this->option('promotion') ? (string) $this->option('promotion') : null,
            );

            $this->info('OK order_id='.$order->id);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(class_basename($e).': '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
