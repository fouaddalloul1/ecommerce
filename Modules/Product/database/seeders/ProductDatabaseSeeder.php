<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class ProductDatabaseSeeder extends Seeder
{
    public function run()
    {
        $output = new ConsoleOutput();

        $categoryIds = DB::table('categories')->pluck('id')->toArray();

        foreach ($categoryIds as $categoryId) {
            $this->insertLargeBatch($categoryId, 10, $output); // 10M
        }
    }

    private function insertLargeBatch($categoryId, $count, $output)
    {
        $chunkSize = 1000;

        // Create progress bar
        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();

        for ($i = 0; $i < $count; $i += $chunkSize) {

            $batch = [];

            for ($j = 0; $j < $chunkSize; $j++) {
                $batch[] = [
                    'category_id' => $categoryId,
                    'name' => "Product {$categoryId}-" . ($i + $j),
                    'description' => "Description",
                    'price' => rand(50, 500),
                    'stock' => rand(5, 100),
                    'size' => ['S', 'M', 'L', null][array_rand(['S', 'M', 'L', null])],
                    'image_url' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('products')->insert($batch);

            // Advance progress bar by chunk size
            $progressBar->advance($chunkSize);
        }

        $progressBar->finish();
        $output->writeln("\nDone inserting for category {$categoryId}\n");
    }
}
