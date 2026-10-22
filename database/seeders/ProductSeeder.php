<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Products;
class ProductSeeder extends Seeder
{
   public function run(): void
    {
        Products::create([
            'name' => 'Baby Cotton Shirt',
            'slug' => 'baby-cotton-shirt',
            'description' => 'Soft cotton shirt for babies.',
            'price' => 1299.00,
            'stock' => 25,
            'image' => null,
            'is_active' => true,
        ]);

        Products::create([
            'name' => 'Baby Romper',
            'slug' => 'baby-romper',
            'description' => 'Comfortable everyday baby romper.',
            'price' => 1799.00,
            'stock' => 15,
            'image' => null,
            'is_active' => true,
        ]);
    }
}
