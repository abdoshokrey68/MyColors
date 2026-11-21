<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Order\Models\Order;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;

class DemoConcurrentCheckoutCommand extends Command
{
    protected $signature = 'demo:concurrent-checkout
                            {--mode=stock : stock (two users, qty 4+3 vs stock 5) or same-user (one user, two parallel checkouts)}';

    protected $description = 'Prove concurrent checkout safety via parallel artisan workers (MySQL, no HTTP server).';

    public function handle(): int
    {
        if (config('database.default') === 'sqlite') {
            $this->warn('This demo should run against MySQL. SQLite lockForUpdate is not equivalent to InnoDB row locks.');
        }

        $mode = (string) $this->option('mode');

        return match ($mode) {
            'same-user' => $this->runSameUserMode(),
            'stock' => $this->runStockMode(),
            default => $this->invalidMode($mode),
        };
    }

    private function runStockMode(): int
    {
        $product = $this->createProduct(stock: 5);
        $customerA = User::factory()->create(['email' => 'demo-a-'.uniqid().'@example.com']);
        $customerB = User::factory()->create(['email' => 'demo-b-'.uniqid().'@example.com']);

        $this->seedCart($customerA, $product, 4);
        $this->seedCart($customerB, $product, 3);

        $this->info("Mode=stock. Product #{$product->id} stock=5. Parallel checkouts A=4, B=3 ...");

        $this->runWorkers([(int) $customerA->id, (int) $customerB->id]);

        $product->refresh();
        $orders = Order::query()->whereIn('user_id', [$customerA->id, $customerB->id])->count();

        $this->newLine();
        $this->info("Final stock: {$product->stock_quantity} (expected 1)");
        $this->info("Orders created: {$orders} (expected 1)");
        $this->comment('Feature tests stay sequential on SQLite; this command overlaps real MySQL transactions in separate PHP processes.');

        return ($product->stock_quantity === 1 && $orders === 1) ? self::SUCCESS : self::FAILURE;
    }

    private function runSameUserMode(): int
    {
        $product = $this->createProduct(stock: 10);
        $user = User::factory()->create(['email' => 'demo-same-'.uniqid().'@example.com']);
        $this->seedCart($user, $product, 2);

        $this->info("Mode=same-user. Product #{$product->id} stock=10. Same user checks out twice in parallel ...");

        $this->runWorkers([(int) $user->id, (int) $user->id]);

        $product->refresh();
        $orders = Order::query()->where('user_id', $user->id)->count();

        $this->newLine();
        $this->info("Final stock: {$product->stock_quantity} (expected 8)");
        $this->info("Orders created: {$orders} (expected 1)");
        $this->comment('Cart row + locking item reads serialize double-submit; the loser sees an empty cart.');

        return ($product->stock_quantity === 8 && $orders === 1) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function runWorkers(array $userIds): void
    {
        $startAt = microtime(true) + 0.5;
        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        $results = Process::concurrently(function (Pool $pool) use ($userIds, $startAt, $php, $artisan) {
            foreach ($userIds as $index => $userId) {
                $pool->as('w'.$index)
                    ->path(base_path())
                    ->timeout(60)
                    ->command([
                        $php,
                        $artisan,
                        'demo:checkout-worker',
                        (string) $userId,
                        '--start-at='.$startAt,
                    ]);
            }
        });

        foreach ($results as $label => $process) {
            $this->line(sprintf(
                '[%s] exit=%d %s',
                $label,
                $process->exitCode(),
                trim($process->output().$process->errorOutput())
            ));
        }
    }

    private function createProduct(int $stock): Product
    {
        return Product::query()->create([
            'name' => 'Concurrency Demo SKU',
            'sku' => 'DEMO-CONC-'.uniqid(),
            'description' => 'Temporary product for concurrency demo',
            'price' => 10,
            'stock_quantity' => $stock,
            'status' => ProductStatus::Active,
        ]);
    }

    private function seedCart(User $user, Product $product, int $quantity): void
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }

    private function invalidMode(string $mode): int
    {
        $this->error("Unknown mode [{$mode}]. Use stock or same-user.");

        return self::FAILURE;
    }
}
