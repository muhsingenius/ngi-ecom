<?php

namespace App\Http\Controllers;

use App\Models\Design;
use App\Models\DesignArea;
use App\Models\DesignAsset;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProductColour;
use App\Services\DesignRules;
use App\Services\ProofService;
use App\Support\Png;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Intervention\Image\Laravel\Facades\Image;

class DesignController extends Controller
{
    /** Open the designer on a blank product. */
    public function create(Request $request, Product $product): View
    {
        abort_unless($product->active, 404);

        return $this->designer($product, null, $request);
    }

    /** Re-open a saved design. */
    public function edit(Request $request, Design $design): View
    {
        abort_unless($design->isOwnedBy($request), 404);

        return $this->designer($design->product, $design, $request);
    }

    public function show(Request $request, Design $design): View
    {
        abort_unless($design->isOwnedBy($request), 404);

        $design->load(['product', 'colour', 'areas.printArea']);

        return view('designs.show', [
            'design' => $design,
            'previews' => $design->areas->mapWithKeys(fn ($a) => [$a->id => $a->preview_path ? PrivateFile::url($a->preview_path) : null]),
        ]);
    }

    /** Save a design with its print-ready exports. */
    public function store(Request $request, DesignRules $rules): JsonResponse
    {
        return $this->persist($request, $rules, null);
    }

    public function update(Request $request, Design $design, DesignRules $rules): JsonResponse
    {
        abort_unless($design->isOwnedBy($request), 404);

        return $this->persist($request, $rules, $design);
    }

    private function persist(Request $request, DesignRules $rules, ?Design $design): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'colour_id' => ['required', 'integer', 'exists:product_colours,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'group_from' => ['nullable', 'integer'],
            'areas' => ['required', 'array', 'min:1', 'max:10'],
            'areas.*.print_area_id' => ['required', 'integer'],
            'areas.*.state' => ['required', 'string', 'max:6000000'],
            'areas.*.personalisation' => ['nullable', 'string', 'max:20000'],
            'areas.*.colours_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'areas.*.export' => ['required', 'file', 'max:61440'],
        ]);

        $product = Product::with('printAreas')->active()->findOrFail($request->integer('product_id'));
        $colour = ProductColour::where('product_id', $product->id)->where('active', true)->findOrFail($request->integer('colour_id'));

        if ($design && $design->product_id !== $product->id) {
            return response()->json(['message' => 'That design belongs to a different product.'], 422);
        }

        // A design opened to revise an order can only be saved while that order still awaits proof approval.
        if ($design?->revises_order_line_id && ! app(ProofService::class)->canRevise(OrderLine::findOrFail($design->revises_order_line_id)->order)) {
            return response()->json(['message' => 'This order has already moved on, so the design can no longer be changed here. Please contact us.'], 422);
        }

        $errors = [];
        $prepared = [];

        foreach ($request->input('areas') as $i => $row) {
            $area = $product->printAreas->where('active', true)->firstWhere('id', (int) $row['print_area_id']);

            if (! $area) {
                return response()->json(['message' => 'Unknown print area.'], 422);
            }

            $state = json_decode($row['state'], true);

            if (! is_array($state) || ! is_array($state['objects'] ?? null)) {
                return response()->json(['message' => "The design for {$area->name} is not valid."], 422);
            }

            if (empty($state['objects'])) {
                continue; // An empty area is simply not printed.
            }

            $bytes = (string) file_get_contents($request->file("areas.$i.export")->getRealPath());
            $dims = Png::dimensions($bytes);

            if (! $dims) {
                return response()->json(['message' => "The print file for {$area->name} is not a PNG image."], 422);
            }

            [$expectedW, $expectedH] = $area->exportPixels((int) config('designer.export_dpi'));
            $dpi = (int) round($dims[0] / ($area->width_cm / 2.54));
            $ratioOff = abs(($dims[0] / $dims[1]) - ($area->width_cm / $area->height_cm)) / ($area->width_cm / $area->height_cm);

            if ($ratioOff > 0.02) {
                return response()->json(['message' => "The print file for {$area->name} has the wrong proportions."], 422);
            }

            if ($dpi < (int) config('designer.min_export_dpi')) {
                return response()->json(['message' => "The print file for {$area->name} is too low resolution ({$dpi} DPI; {$expectedW}×{$expectedH} px expected)."], 422);
            }

            $assetIds = $rules->assetIds($state);

            if ($assetIds && DesignAsset::ownedBy($request)->whereIn('id', $assetIds)->count() !== count($assetIds)) {
                return response()->json(['message' => 'The design uses an image that is not yours.'], 422);
            }

            $personalisation = $row['personalisation'] ?? null ? json_decode($row['personalisation'], true) : null;
            $errors = array_merge($errors, $rules->problems($area, $state, (int) ($row['colours_count'] ?? 0)));

            $prepared[] = compact('area', 'state', 'bytes', 'dims', 'dpi', 'personalisation') + ['colours' => (int) ($row['colours_count'] ?? 0), 'raw' => $row['state']];
        }

        if ($errors) {
            return response()->json(['message' => $errors[0], 'problems' => $errors], 422);
        }

        if (! $prepared) {
            return response()->json(['message' => 'Add something to your design first.'], 422);
        }

        // A design reused on another product joins the original's group so quantity tiers can span products.
        $groupKey = (string) Str::uuid();

        if (! $design && $request->filled('group_from')) {
            $source = Design::find($request->integer('group_from'));
            $groupKey = $source && $source->isOwnedBy($request) ? $source->group_key : $groupKey;
        }

        $design = DB::transaction(function () use ($request, $design, $product, $colour, $prepared, $groupKey) {
            $design ??= new Design([
                'product_id' => $product->id,
                'group_key' => $groupKey,
                'user_id' => $request->user()?->id,
                'session_token' => $request->user() ? null : $request->session()->get('designer_token'),
            ]);

            $design->fill(['product_colour_id' => $colour->id, 'name' => $request->input('name')])->save();

            $keep = [];

            foreach ($prepared as $p) {
                $base = "designs/{$design->id}/area-{$p['area']->id}";
                $png = Png::withDpi($p['bytes'], $p['dpi']);

                Storage::disk('designs')->put("$base.png", $png);
                Storage::disk('designs')->put("$base-preview.png", (string) Image::decodeBinary($p['bytes'])->scaleDown(width: 900)->encodeUsingMediaType('image/png'));

                DesignArea::updateOrCreate(
                    ['design_id' => $design->id, 'print_area_id' => $p['area']->id],
                    [
                        'state' => $p['raw'], 'personalisation' => $p['personalisation'],
                        'export_path' => "$base.png", 'preview_path' => "$base-preview.png",
                        'export_width' => $p['dims'][0], 'export_height' => $p['dims'][1],
                        'export_dpi' => $p['dpi'], 'colours_count' => $p['colours'],
                    ],
                );

                $keep[] = $p['area']->id;
            }

            // Areas the customer cleared are no longer printed, so their files go too.
            $design->areas()->whereNotIn('print_area_id', $keep)->get()->each(function (DesignArea $gone) {
                Storage::disk('designs')->delete(array_filter([$gone->export_path, $gone->preview_path]));
                $gone->delete();
            });

            return $design;
        });

        if ($design->revises_order_line_id) {
            // Replace the order's print files with this version and send a fresh proof.
            $order = app(ProofService::class)->applyRevision($design);

            return response()->json(['design_id' => $design->id, 'next' => $order->url()]);
        }

        return response()->json(['design_id' => $design->id, 'next' => route('designs.order', $design)], $design->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * State of an existing design to start from ("use this design on another product").
     *
     * @return array{groupFrom: int, areas: array<string, array{state: string, widthCm: float, heightCm: float}>}|null
     */
    private function seedFrom(Request $request): ?array
    {
        $source = Design::with('areas.printArea')->find($request->query('from'));

        if (! $source || ! $source->isOwnedBy($request)) {
            return null;
        }

        return [
            'groupFrom' => $source->id,
            'areas' => $source->areas->mapWithKeys(fn ($a) => [$a->printArea->slug => ['state' => $a->state, 'widthCm' => $a->printArea->width_cm, 'heightCm' => $a->printArea->height_cm]])->all(),
        ];
    }

    private function designer(Product $product, ?Design $design, Request $request): View
    {
        $seed = $design ? null : $this->seedFrom($request);

        $product->load([
            'colours' => fn ($q) => $q->where('active', true),
            'colours.mockups',
            'printAreas' => fn ($q) => $q->where('active', true),
        ]);

        abort_if($product->colours->isEmpty() || $product->printAreas->isEmpty(), 404);

        $design?->load('areas');
        $colourId = $design?->product_colour_id ?? (int) $request->query('colour');
        $colour = $product->colours->firstWhere('id', $colourId) ?? $product->colours->first();

        // Fresh, short-lived URLs for images already placed in a saved design.
        $assetIds = collect($design?->areas ?? [])->flatMap(fn ($a) => app(DesignRules::class)->assetIds(json_decode($a->state, true) ?: []))->unique();
        $assets = DesignAsset::ownedBy($request)->whereIn('id', $assetIds)->get()
            ->mapWithKeys(fn ($a) => [$a->id => PrivateFile::url($a->path, now()->addHours(6))]);

        $config = [
            'productId' => $product->id,
            'designId' => $design?->id,
            'name' => $design?->name,
            'textOnly' => $product->type->isTextOnly(),
            'colourId' => $colour->id,
            'colours' => $product->colours->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => $c->hex])->values(),
            'areas' => $product->printAreas->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->name, 'slug' => $a->slug,
                'widthCm' => $a->width_cm, 'heightCm' => $a->height_cm,
                'method' => $a->method->value, 'methodLabel' => $a->method->label(),
                'maxColours' => $a->max_colours, 'minLineMm' => $a->min_line_mm, 'maxChars' => $a->max_chars,
                'allowsName' => $a->allows_name, 'allowsNumber' => $a->allows_number,
                'box' => ['x' => (float) $a->box_x, 'y' => (float) $a->box_y, 'w' => (float) $a->box_w, 'h' => (float) $a->box_h],
                'mockups' => $a->mockups()->get()->mapWithKeys(fn ($m) => [$m->product_colour_id => $m->url()]),
                'state' => $design?->areas->firstWhere('print_area_id', $a->id)?->state,
                'personalisation' => $design?->areas->firstWhere('print_area_id', $a->id)?->personalisation,
            ])->values(),
            'assets' => $assets,
            'seed' => $seed,
            'palette' => config('designer.palette'),
            'fonts' => config('designer.fonts'),
            'pxPerCm' => config('designer.px_per_cm'),
            'exportDpi' => config('designer.export_dpi'),
            'maxExportPixels' => config('designer.max_export_pixels'),
            'minImageDpi' => config('designer.min_image_dpi'),
            'maxUploadMb' => round(config('designer.max_upload_kb') / 1024),
            'urls' => [
                'upload' => route('design-assets.store'),
                'assetUrls' => route('design-assets.urls'),
                'save' => $design ? route('designs.update', $design) : route('designs.store'),
                'method' => $design ? 'PUT' : 'POST',
                'back' => route('catalogue.show', $product),
            ],
        ];

        return view('designs.designer', ['product' => $product, 'config' => $config]);
    }
}
