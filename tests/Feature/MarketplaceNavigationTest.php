<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(User $owner): array
    {
        $category = DB::table('categories')->insertGetId([
            'user_id' => $owner->id,
            'category_name' => 'Navigation category ' . uniqid(),
            'status' => 'Active',
            'image' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unit = DB::table('units')->insertGetId([
            'user_id' => $owner->id,
            'unit_name' => 'Navigation unit ' . uniqid(),
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$category, $unit];
    }

    private function product(Vendor $vendor, int $category, int $unit, string $name): Product
    {
        $product = Product::forceCreate([
            'vendor_id' => $vendor->id,
            'category_id' => $category,
            'unit_id' => $unit,
            'product_name' => $name,
            'product_price' => 10,
            'product_stock' => 20,
            'delivery_duration' => 2,
            'delivery_duration_unit' => 'Days',
            'delivery_charge' => 0,
            'is_delivery_free' => 1,
            'availability_type' => 'selected',
            'country' => 'US',
            'status' => 'Active',
        ]);

        $countryId = DB::table('countries')->where('code', 'US')->value('id');
        if ($countryId) {
            $product->availabilityCountries()->sync([$countryId]);
        }

        return $product;
    }

    public function test_navigation_uses_real_counts_and_hides_seller_controls_for_buyers(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $vendor = Vendor::create(['user_id' => $owner->id, 'balance' => 0]);
        [$category, $unit] = $this->catalog($owner);
        $product = $this->product($vendor, $category, $unit, 'Navigation product');
        $buyer = User::factory()->create(['role' => 'buyer', 'country' => 'HT']);
        $seller = User::factory()->create(['role' => 'seller', 'country' => 'US']);
        Vendor::create(['user_id' => $seller->id, 'balance' => 0]);

        $cart = Cart::create(['user_id' => $buyer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 3]);
        Favorite::create(['user_id' => $buyer->id, 'product_id' => $product->id]);
        Message::create([
            'sender_id' => $owner->id,
            'recipient_id' => $buyer->id,
            'subject' => 'Wholesale enquiry',
            'body' => 'Please send a quote.',
        ]);
        DB::table('notifications')->insert([
            'user_id' => $buyer->id,
            'type' => 'order',
            'data' => json_encode(['message' => 'New order update']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($buyer)->get(route('marketplace.home'))
            ->assertSee('class="nav-count">3</span>', false)
            ->assertSee('class="nav-count">1</span>', false)
            ->assertSee('data-menu-open', false)
            ->assertSee(__('marketplace.nav.wholesale'))
            ->assertDontSee('seller-quick-actions', false);

        $this->actingAs($seller)->get(route('marketplace.home'))
            ->assertSee('seller-quick-actions', false)
            ->assertSee(__('marketplace.nav.quick_seller_actions'))
            ->assertSee(route('seller.product-create'), false);
    }

    public function test_search_suggestions_are_categorized_and_follow_browse_market(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $vendor = Vendor::create(['user_id' => $owner->id, 'balance' => 0]);
        [$category, $unit] = $this->catalog($owner);
        $haiti = $this->product($vendor, $category, $unit, 'Mango wholesale Haiti');
        $haiti->update(['availability_type' => 'selected']);
        $haiti->availabilityCountries()->sync([DB::table('countries')->where('code', 'HT')->value('id')]);
        $unitedStates = $this->product($vendor, $category, $unit, 'Mango wholesale United States');
        $unitedStates->update(['availability_type' => 'selected']);
        $unitedStates->availabilityCountries()->sync([DB::table('countries')->where('code', 'US')->value('id')]);
        $buyer = User::factory()->create(['role' => 'buyer', 'country' => 'HT']);

        $this->actingAs($buyer)->withSession(['marketplace_country' => 'US'])
            ->getJson(route('marketplace.search.suggestions', ['q' => 'Mango']))
            ->assertOk()
            ->assertJsonFragment(['title' => $haiti->product_name])
            ->assertJsonMissing(['title' => $unitedStates->product_name])
            ->assertJsonFragment(['key' => 'products']);
    }

    public function test_authenticated_market_selector_is_locked_to_account_country(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'country' => 'HT']);

        $this->actingAs($buyer)->get(route('marketplace.home'))
            ->assertSee('country-selector-locked', false)
            ->assertSee(__('marketplace.account_country_locked'))
            ->assertSee('HT', false);

        $this->actingAs($buyer)->post(route('marketplace.country.update', 'US'))
            ->assertSessionHas('marketplace_country', 'HT');

        $buyer->refresh();
        $this->assertSame('HT', $buyer->country);
        $this->assertSame('HT', $buyer->marketplace_country);
    }

    public function test_home_hero_explains_wholesale_and_uses_real_cta_routes(): void
    {
        $this->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('FLEXA WHOLESALE')
            ->assertSee('Flexa Wholesale')
            ->assertSee(__('marketplace.hero_title'))
            ->assertSee(__('marketplace.hero_description'))
            ->assertSee('wholesale-truck-hero.png')
            ->assertSee('hero-intro', false)
            ->assertSee('hero-visual', false)
            ->assertSee('href="'.route('marketplace.search').'"', false)
            ->assertSee('href="'.route('signup', ['role' => 'seller']).'"', false)
            ->assertSee(__('marketplace.hero_note_copy'))
            ->assertSee('data-category-search-for="marketplace-category-filter"', false)
            ->assertSee(__('marketplace.category_search_placeholder'))
            ->assertSee(__('marketplace.search_category_button'))
            ->assertDontSee('Connect with wholesale buyers, sellers, suppliers');
    }

    public function test_seller_home_hero_links_start_selling_to_add_product(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        Vendor::create(['user_id' => $seller->id, 'balance' => 0]);

        $this->actingAs($seller)
            ->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('href="'.route('seller.product-create').'"', false);
    }

    public function test_default_wholesale_categories_are_available_to_sellers_and_buyers(): void
    {
        $expected = [
            'Agriculture & Livestock',
            'Automotive & Parts',
            'Baby & Children',
            'Beauty, Perfume & Personal Care',
            'Construction & Hardware',
            'Electronics & Phones',
            'Fashion & Clothing',
            'Food & Beverages',
            'Health & Wellness',
            'Home & Kitchen',
            'Home Appliances',
            'Jewelry & Accessories',
            'Other Products',
            'Professional & Industrial Supplies',
            'Sports & Leisure',
            'Used Products',
        ];

        $this->assertSame(16, Category::active()->whereNotNull('slug')->count());
        foreach ($expected as $categoryName) {
            $this->assertDatabaseHas('categories', [
                'category_name' => $categoryName,
                'status' => 'Active',
            ]);
        }

        $seller = User::factory()->create(['role' => 'seller']);
        $vendor = Vendor::create(['user_id' => $seller->id, 'balance' => 0]);
        $this->actingAs($seller)
            ->get(route('seller.product-create'))
            ->assertOk()
            ->assertSee('name="category_id"', false)
            ->assertSee('data-category-search-for="seller-category-select"', false)
            ->assertSee(__('marketplace.search_category_button'))
            ->assertSee('Food &amp; Beverages', false);
    }

    public function test_category_synonyms_drive_category_first_search_and_available_product_results(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'name' => 'Island Wholesale',
            'company_name' => 'Island Wholesale',
            'country' => 'HT',
        ]);
        $vendor = Vendor::create(['user_id' => $seller->id, 'balance' => 0]);
        [, $unit] = $this->catalog($seller);
        $beauty = Category::where('slug', 'beauty-perfume-personal-care')->firstOrFail();
        $available = $this->product($vendor, $beauty->id, $unit, 'Luxury wholesale collection');
        $available->availabilityCountries()->sync([DB::table('countries')->where('code', 'HT')->value('id')]);
        $unavailable = $this->product($vendor, $beauty->id, $unit, 'Overseas wholesale collection');
        $unavailable->availabilityCountries()->sync([DB::table('countries')->where('code', 'US')->value('id')]);
        $buyer = User::factory()->create(['role' => 'buyer', 'country' => 'HT']);

        $response = $this->actingAs($buyer)
            ->getJson(route('marketplace.search.suggestions', ['q' => 'parfum']))
            ->assertOk()
            ->assertJsonPath('groups.0.key', 'categories')
            ->assertJsonPath('groups.0.items.0.title', 'Beauty, Perfume & Personal Care')
            ->assertJsonFragment(['title' => $available->product_name])
            ->assertJsonMissing(['title' => $unavailable->product_name])
            ->assertJsonFragment(['title' => 'Island Wholesale']);

        $categoryUrl = $response->json('groups.0.items.0.url');
        $this->assertStringContainsString('category=' . $beauty->id, $categoryUrl);

        $this->actingAs($buyer)
            ->get(route('marketplace.search', ['q' => 'parfum']))
            ->assertOk()
            ->assertSee($available->product_name)
            ->assertDontSee($unavailable->product_name);
    }

    public function test_category_names_are_localized_and_keyword_metadata_reaches_live_picker(): void
    {
        $beauty = Category::where('slug', 'beauty-perfume-personal-care')->firstOrFail();

        $this->withSession([config('localization.session_key', 'locale') => 'fr'])
            ->get(route('marketplace.home'))
            ->assertOk()
            ->assertSee('Beauté, Parfums &amp; Soins personnels', false)
            ->assertSee('data-category-label="beauté, parfums &amp; soins personnels beauty, perfume &amp; personal care', false)
            ->assertSee('parfum', false);

        $this->assertSame('Beauté, Parfums & Soins personnels', $beauty->display_name);
    }

    public function test_requested_category_keywords_and_synonyms_match_the_expected_taxonomy(): void
    {
        $examples = [
            'parfum' => 'beauty-perfume-personal-care',
            'beauté' => 'beauty-perfume-personal-care',
            'téléphone' => 'electronics-phones',
            'ordinateur' => 'electronics-phones',
            'voiture' => 'automotive-parts',
            'auto' => 'automotive-parts',
            'maison' => 'home-kitchen',
            'meuble' => 'home-kitchen',
            'frigo' => 'home-appliances',
            'bébé' => 'baby-children',
            'construction' => 'construction-hardware',
            'agriculture' => 'agriculture-livestock',
            'bijoux' => 'jewelry-accessories',
            'vêtement' => 'fashion-clothing',
            'chaussure' => 'fashion-clothing',
            'nourriture' => 'food-beverages',
            'boisson' => 'food-beverages',
        ];

        foreach ($examples as $term => $slug) {
            $this->assertTrue(
                Category::active()->matching($term)->where('slug', $slug)->exists(),
                "{$term} should match {$slug}."
            );
        }
    }
}