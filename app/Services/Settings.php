<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Cached, typed access to store settings.
 *
 * Every setting is declared once in {@see self::definitions()} with its type,
 * default and validation rules. Stored values override defaults; an explicitly
 * stored null (e.g. proof.threshold = off) is kept and returned as null.
 */
class Settings
{
    public const CACHE_KEY = 'settings.all';

    /**
     * @return array<string, array{type: string, default: mixed, rules: list<mixed>}>
     */
    public static function definitions(): array
    {
        return [
            'pricing.tier_scope' => [
                'type' => 'string',
                'default' => 'product_design',
                'rules' => ['required', 'in:product_design,design,order'],
            ],
            'proof.threshold' => [
                'type' => 'int',
                'default' => 20,
                'rules' => ['nullable', 'integer', 'min:0'],
            ],
            'proof.timing' => [
                'type' => 'string',
                'default' => 'pay_then_proof',
                'rules' => ['required', 'in:pay_then_proof,proof_then_pay'],
            ],
            'proof.max_revisions' => [
                'type' => 'int',
                'default' => 3,
                'rules' => ['required', 'integer', 'min:0'],
            ],
            'proof.unpaid_expiry_days' => [
                'type' => 'int',
                'default' => 7,
                'rules' => ['required', 'integer', 'min:1'],
            ],
            'delivery.free_threshold' => [
                'type' => 'float',
                'default' => null,
                'rules' => ['nullable', 'numeric', 'min:0'],
            ],
            'shop.notification_email' => [
                'type' => 'string',
                'default' => null,
                'rules' => ['nullable', 'email', 'max:255'],
            ],
            'orders.turnaround_days' => [
                'type' => 'int',
                'default' => 5,
                'rules' => ['required', 'integer', 'min:0'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::query()->pluck('value', 'key')
                ->map(fn (?string $json) => json_decode((string) $json, true))
                ->all();
        });

        $values = [];

        foreach (self::definitions() as $key => $definition) {
            $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : $definition['default'];
        }

        return $values;
    }

    public function get(string $key): mixed
    {
        $this->definition($key);

        return $this->all()[$key];
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        return $value === null ? null : (string) $value;
    }

    public function int(string $key): ?int
    {
        $value = $this->get($key);

        return $value === null ? null : (int) $value;
    }

    public function float(string $key): ?float
    {
        $value = $this->get($key);

        return $value === null ? null : (float) $value;
    }

    /**
     * Validate and store a value. Throws a ValidationException for invalid input.
     */
    public function set(string $key, mixed $value): void
    {
        $definition = $this->definition($key);

        if ($value === '') {
            $value = null;
        }

        $validated = Validator::make(['value' => $value], ['value' => $definition['rules']])
            ->validate()['value'] ?? null;

        $validated = $this->cast($definition['type'], $validated);

        Setting::query()->updateOrCreate(['key' => $key], ['value' => json_encode($validated)]);

        $this->flush();
    }

    /**
     * Store a setting's default only if nothing is stored yet (used by the seeder).
     */
    public function seedDefaults(): void
    {
        foreach (self::definitions() as $key => $definition) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => json_encode($definition['default'])]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{type: string, default: mixed, rules: list<mixed>}
     */
    private function definition(string $key): array
    {
        return self::definitions()[$key]
            ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    private function cast(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            default => (string) $value,
        };
    }
}
