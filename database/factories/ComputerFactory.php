<?php

namespace Database\Factories;

use App\Enums\ComputerStatus;
use App\Enums\ComputerType;
use App\Models\Computer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Computer> */
class ComputerFactory extends Factory
{
    protected $model = Computer::class;

    public function definition(): array
    {
        $purchase = fake()->dateTimeBetween('-5 years', '-1 month');
        $brand = fake()->randomElement(['Dell', 'HP', 'Lenovo', 'Asus', 'Acer', 'Apple']);

        return [
            'inventory_number' => 'PC-'.fake()->unique()->numerify('#####'),
            'name' => 'PC-'.strtoupper(fake()->unique()->bothify('??##')),
            'type' => fake()->randomElement(ComputerType::cases()),
            'brand' => $brand,
            'model' => fake()->bothify('???-####'),
            'serial_number' => strtoupper(fake()->unique()->bothify('SN#########')),
            'cpu' => fake()->randomElement(['Intel Core i3', 'Intel Core i5', 'Intel Core i7', 'AMD Ryzen 5', 'AMD Ryzen 7']),
            'ram_gb' => fake()->randomElement([4, 8, 16, 32]),
            'storage' => fake()->randomElement(['256GB SSD', '512GB SSD', '1TB HDD', '1TB SSD']),
            'os' => fake()->randomElement(['Windows 10 Pro', 'Windows 11 Pro', 'Ubuntu 24.04', 'macOS 14']),
            'ip_address' => fake()->localIpv4(),
            'mac_address' => strtoupper(fake()->macAddress()),
            'department' => fake()->randomElement(['Нягтлан бодох', 'Хүний нөөц', 'IT', 'Маркетинг', 'Захиргаа']),
            'location' => 'Өрөө '.fake()->numberBetween(101, 420),
            'assigned_to' => fake()->name(),
            'status' => fake()->randomElement(ComputerStatus::cases()),
            'purchase_date' => $purchase,
            'warranty_until' => (clone $purchase)->modify('+3 years'),
            'notes' => null,
        ];
    }
}
