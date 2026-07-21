<?php

namespace App\Services\Payments\Data;

use App\Services\Payments\Enums\BillingCycle;

/**
 * Representa um plano configurado em config/subscription.php.
 */
class Plan
{
    /**
     * @param  array<int, string>  $features
     */
    public function __construct(
        public string $key,
        public string $name,
        public float $price,
        public BillingCycle $cycle,
        public ?string $description = null,
        public array $features = [],
    ) {}

    public static function find(string $key): ?self
    {
        $config = config("subscription.plans.{$key}");

        if (! $config) {
            return null;
        }

        return self::fromConfig($key, $config);
    }

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        return collect(config('subscription.plans', []))
            ->map(fn (array $config, string $key) => self::fromConfig($key, $config))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            name: $config['name'],
            price: (float) $config['price'],
            cycle: BillingCycle::from($config['cycle']),
            description: $config['description'] ?? null,
            features: $config['features'] ?? [],
        );
    }
}
