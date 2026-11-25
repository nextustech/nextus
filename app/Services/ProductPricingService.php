<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPricing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductPricingService
{
    public function recordPrice(Product $product, array $attributes, bool $canSetFloor): ProductPricing
    {
        $effectiveFrom = isset($attributes['effective_from'])
            ? CarbonImmutable::parse($attributes['effective_from'])
            : CarbonImmutable::now();
        $variantId = $attributes['product_variant_id'] ?? null;
        $currency = strtoupper($attributes['currency'] ?? 'INR');

        if ($variantId !== null && ! $product->variants()->whereKey($variantId)->exists()) {
            throw new InvalidArgumentException('The variant does not belong to this product.');
        }

        return DB::transaction(function () use ($product, $attributes, $canSetFloor, $effectiveFrom, $variantId, $currency): ProductPricing {
            $current = $this->queryPrice($product, $variantId, $currency)
                ->where('effective_from', '<=', $effectiveFrom)
                ->where(function ($query) use ($effectiveFrom): void {
                    $query->whereNull('effective_to')
                        ->orWhere('effective_to', '>', $effectiveFrom);
                })
                ->orderByDesc('effective_from')
                ->lockForUpdate()
                ->first();

            $sameStart = $this->queryPrice($product, $variantId, $currency)
                ->where('effective_from', $effectiveFrom)
                ->exists();
            if ($sameStart) {
                throw new InvalidArgumentException('A price already exists at this effective time.');
            }

            $floorType = $canSetFloor
                ? ($attributes['floor_type'] ?? 'fixed')
                : ($current?->floor_type ?? 'fixed');
            $floorPrice = $canSetFloor
                ? ($attributes['floor_price'] ?? null)
                : ($current?->floor_price ?? '0.00');

            if ($floorPrice === null) {
                throw new InvalidArgumentException('A floor price is required.');
            }
            if ($floorType === 'percent') {
                $this->toBasisPoints((string) $floorPrice);
            } else {
                $this->toMinorUnits((string) $floorPrice);
            }

            if (! $canSetFloor && $current === null) {
                throw new InvalidArgumentException('A platform-set floor is required before this price can be published.');
            }
            $baseMinorUnits = $this->toMinorUnits((string) $attributes['base_price']);
            $floorMinorUnits = $floorType === 'percent'
                ? $this->percentageFloorMinorUnits($baseMinorUnits, (string) $floorPrice)
                : $this->toMinorUnits((string) $floorPrice);
            if ($baseMinorUnits < $floorMinorUnits) {
                throw new InvalidArgumentException(sprintf(
                    'Selling price below minimum %s %s.',
                    $currency,
                    number_format($floorMinorUnits / 100, 2, '.', ','),
                ));
            }

            $next = $this->queryPrice($product, $variantId, $currency)
                ->where('effective_from', '>', $effectiveFrom)
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->first();

            $previous = $current;
            if ($previous) {
                $previous->update(['effective_to' => $effectiveFrom]);
            }

            return ProductPricing::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'base_price' => $attributes['base_price'],
                'floor_price' => $floorPrice,
                'floor_type' => $floorType,
                'currency' => $currency,
                'effective_from' => $effectiveFrom,
                'effective_to' => $next?->effective_from,
            ]);
        });
    }

    public function effectivePrice(
        Product $product,
        ?int $variantId = null,
        string $currency = 'INR',
        ?CarbonImmutable $at = null,
    ): ?ProductPricing {
        $at ??= CarbonImmutable::now();

        return $this->queryPrice($product, $variantId, strtoupper($currency))
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    public function floorMinorUnits(ProductPricing $price): int
    {
        $floorValue = $this->toMinorUnits((string) $price->floor_price);

        if ($price->floor_type === 'percent') {
            $baseMinorUnits = $this->toMinorUnits((string) $price->base_price);

            return $this->percentageFloorMinorUnits($baseMinorUnits, (string) $price->floor_price);
        }

        return $floorValue;
    }

    public function assertGrossPriceMeetsFloor(
        Product $product,
        string|int|float $grossPrice,
        ?int $variantId = null,
        string $currency = 'INR',
        ?CarbonImmutable $at = null,
    ): void {
        $price = $this->effectivePrice($product, $variantId, $currency, $at);
        if (! $price) {
            throw new InvalidArgumentException('No effective product price is available.');
        }

        $grossMinorUnits = $this->toMinorUnits((string) $grossPrice);
        $floorMinorUnits = $this->floorMinorUnits($price);
        if ($grossMinorUnits < $floorMinorUnits) {
            throw new InvalidArgumentException(sprintf(
                'Selling price below minimum %s %s.',
                strtoupper($currency),
                number_format($floorMinorUnits / 100, 2, '.', ','),
            ));
        }
    }

    private function queryPrice(Product $product, ?int $variantId, string $currency)
    {
        return ProductPricing::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->where('currency', $currency);
    }

    private function toMinorUnits(string $amount): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Price must be a non-negative amount with at most two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function toBasisPoints(string $percentage): int
    {
        if (! preg_match('/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/', $percentage)) {
            throw new InvalidArgumentException('A percentage floor must be between 0 and 100.');
        }

        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function percentageFloorMinorUnits(int $baseMinorUnits, string $percentage): int
    {
        $basisPoints = $this->toBasisPoints($percentage);
        $wholePart = intdiv($baseMinorUnits, 10000) * $basisPoints;
        $fractionPart = intdiv((($baseMinorUnits % 10000) * $basisPoints) + 5000, 10000);

        return $wholePart + $fractionPart;
    }
}
