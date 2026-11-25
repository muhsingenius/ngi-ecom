<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintMethod;
use App\Http\Controllers\Controller;
use App\Models\Mockup;
use App\Models\PrintArea;
use App\Models\Product;
use App\Models\ProductColour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Colours, sizes, variants, print areas and mockups: the parts a product is built from.
 */
class ProductPartsController extends Controller
{
    // Colours -----------------------------------------------------------

    public function storeColour(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $colour = $product->colours()->create($data + ['sort' => $product->colours()->count()]);

        // Give the new colour a variant for every size the product already has.
        $sizes = $product->variants()->get()->groupBy('size');
        $sort = 0;

        foreach ($sizes as $size => $variants) {
            $product->variants()->create([
                'product_colour_id' => $colour->id, 'size' => $size,
                'size_surcharge' => $variants->first()->size_surcharge, 'sort' => $sort++,
            ]);
        }

        return back()->with('status', "Colour {$colour->name} added. Upload a mockup for each print area below.");
    }

    public function updateColour(Request $request, ProductColour $colour): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'active' => ['boolean'],
        ]);

        $colour->update($data);

        return back()->with('status', 'Colour saved.');
    }

    public function destroyColour(ProductColour $colour): RedirectResponse
    {
        $paths = $colour->mockups()->pluck('path');
        $colour->delete();
        $paths->each(fn ($path) => $this->deleteFileIfUnused($path));

        return back()->with('status', 'Colour removed.');
    }

    // Sizes -------------------------------------------------------------

    public function storeSize(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'size' => ['required', 'string', 'max:20'],
            'size_surcharge' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($product->variants()->where('size', $data['size'])->exists()) {
            return back()->withErrors(['size' => 'That size already exists.']);
        }

        if ($product->colours()->doesntExist()) {
            return back()->withErrors(['size' => 'Add a colour first; sizes are created for each colour.']);
        }

        $sort = $product->variants()->select('size')->distinct()->count();

        foreach ($product->colours as $colour) {
            $product->variants()->create([
                'product_colour_id' => $colour->id, 'size' => $data['size'],
                'size_surcharge' => $data['size_surcharge'] ?? 0, 'sort' => $sort,
            ]);
        }

        return back()->with('status', "Size {$data['size']} added to every colour.");
    }

    public function updateSize(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'size' => ['required', 'string'],
            'size_surcharge' => ['required', 'numeric', 'min:0'],
        ]);

        $product->variants()->where('size', $data['size'])->update(['size_surcharge' => $data['size_surcharge']]);

        return back()->with('status', 'Surcharge saved.');
    }

    public function destroySize(Request $request, Product $product): RedirectResponse
    {
        $product->variants()->where('size', $request->string('size'))->delete();

        return back()->with('status', 'Size removed.');
    }

    /** Switch individual colour/size combinations on or off (e.g. out of stock). */
    public function updateVariants(Request $request, Product $product): RedirectResponse
    {
        $active = collect($request->input('variants', []))->keys()->map(fn ($id) => (int) $id);

        $product->variants()->update(['active' => false]);
        $product->variants()->whereIn('id', $active)->update(['active' => true]);

        return back()->with('status', 'Availability saved.');
    }

    // Print areas -------------------------------------------------------

    public function storeArea(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateArea($request);
        $data['slug'] = $this->areaSlug($product, $data['name']);
        $data['sort'] = $product->printAreas()->count();

        $area = $product->printAreas()->create($data);

        // New areas reuse an existing mockup photo per colour until a better one is uploaded.
        foreach ($product->colours()->with('mockups')->get() as $colour) {
            $existing = $colour->mockups->first();

            if ($existing) {
                $colour->mockups()->create(['print_area_id' => $area->id, 'path' => $existing->path]);
            }
        }

        return back()->with('status', "Print area {$area->name} added.");
    }

    public function updateArea(Request $request, PrintArea $area): RedirectResponse
    {
        $area->update($this->validateArea($request));

        return back()->with('status', "Print area {$area->name} saved.");
    }

    public function destroyArea(PrintArea $area): RedirectResponse
    {
        $paths = $area->mockups()->pluck('path');
        $area->delete();
        $paths->each(fn ($path) => $this->deleteFileIfUnused($path));

        return back()->with('status', 'Print area removed.');
    }

    // Mockups -----------------------------------------------------------

    public function storeMockup(Request $request, ProductColour $colour): RedirectResponse
    {
        $data = $request->validate([
            'print_area_id' => ['required', Rule::exists('print_areas', 'id')->where('product_id', $colour->product_id)],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('image')->store("mockups/{$colour->product_id}", 'public');
        $existing = $colour->mockups()->where('print_area_id', $data['print_area_id'])->first();

        if ($existing) {
            $old = $existing->path;
            $existing->update(['path' => $path]);
            $this->deleteFileIfUnused($old);
        } else {
            $colour->mockups()->create(['print_area_id' => $data['print_area_id'], 'path' => $path]);
        }

        return back()->with('status', 'Mockup saved.');
    }

    public function destroyMockup(Mockup $mockup): RedirectResponse
    {
        $path = $mockup->path;
        $mockup->delete();
        $this->deleteFileIfUnused($path);

        return back()->with('status', 'Mockup removed.');
    }

    // Helpers -----------------------------------------------------------

    /** @return array<string, mixed> */
    private function validateArea(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'width_cm' => ['required', 'numeric', 'min:0.5', 'max:200'],
            'height_cm' => ['required', 'numeric', 'min:0.5', 'max:200'],
            'method' => ['required', Rule::enum(PrintMethod::class)],
            'max_colours' => ['nullable', 'integer', 'min:1', 'max:20'],
            'min_line_mm' => ['nullable', 'numeric', 'min:0.5', 'max:100'],
            'max_chars' => ['nullable', 'integer', 'min:1', 'max:500'],
            'extra_fee' => ['required', 'numeric', 'min:0'],
            'allows_name' => ['boolean'],
            'allows_number' => ['boolean'],
            'box_x' => ['required', 'numeric', 'min:0', 'max:100'],
            'box_y' => ['required', 'numeric', 'min:0', 'max:100'],
            'box_w' => ['required', 'numeric', 'min:1', 'max:100'],
            'box_h' => ['required', 'numeric', 'min:1', 'max:100'],
            'active' => ['boolean'],
        ]);
    }

    private function areaSlug(Product $product, string $name): string
    {
        $slug = Str::slug($name);
        $candidate = $slug;
        $i = 2;

        while ($product->printAreas()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }

    private function deleteFileIfUnused(string $path): void
    {
        if (! Mockup::where('path', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }
}
