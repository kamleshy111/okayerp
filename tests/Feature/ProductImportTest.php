<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles and permissions
        $this->seed(DatabaseSeeder::class);
    }

    public function test_import_endpoint_requires_authentication_and_permissions(): void
    {
        // Guest gets redirect
        $response = $this->post('/product/import');
        $response->assertRedirect('/login');

        // User without store role redirect
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/product/import');
        $response->assertStatus(302);

        // Store user without product manage permission gets 403
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->syncPermissions([]); // Revoke all permissions
        $response = $this->actingAs($storeUser)->post('/product/import');
        $response->assertStatus(403);
    }

    public function test_can_import_products_from_csv(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('product manage');

        // Create a pre-existing category to verify firstOrCreate doesn't duplicate
        $existingCategory = Category::create([
            'user_id' => $storeUser->id,
            'name' => 'Electronics'
        ]);

        $csvHeaders = 'Product Name,Category Name,Unit Type,CGST,SGST,HSN Code,Description';
        $row1 = 'Mouse,Electronics,Pcs,9.0,9.0,8471,Wireless optical mouse';
        $row2 = 'Keyboard,Accessories,Pcs,9.0,9.0,8471,Mechanical keyboard';
        
        $csvContent = $csvHeaders . "\n" . $row1 . "\n" . $row2;
        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($storeUser)->post('/product/import', [
            'file' => $file
        ]);

        $response->assertOk();
        $response->assertJsonPath('imported_count', 2);
        
        // Assert products were created
        $this->assertDatabaseHas('products', [
            'user_id' => $storeUser->id,
            'name' => 'Mouse',
            'category_id' => $existingCategory->id,
            'unit_type' => 'Pcs',
            'price' => 0.00,
            'hsn_code' => '8471',
            'description' => 'Wireless optical mouse'
        ]);

        $newCategory = Category::where('user_id', $storeUser->id)->where('name', 'Accessories')->first();
        $this->assertNotNull($newCategory);

        $this->assertDatabaseHas('products', [
            'user_id' => $storeUser->id,
            'name' => 'Keyboard',
            'category_id' => $newCategory->id,
            'unit_type' => 'Pcs',
            'price' => 0.00,
            'hsn_code' => '8471',
            'description' => 'Mechanical keyboard'
        ]);
    }

    public function test_import_validation_fails_with_invalid_csv_structure(): void
    {
        $storeUser = User::factory()->create(['role' => 'store']);
        $storeUser->givePermissionTo('product manage');

        // CSV without "Name" or "Product Name" header
        $csvHeaders = 'Category Name,Unit Type';
        $row1 = 'Electronics,Pcs';
        $csvContent = $csvHeaders . "\n" . $row1;
        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($storeUser)->post('/product/import', [
            'file' => $file
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'CSV must contain a "Name" or "Product Name" column.');
    }
}
