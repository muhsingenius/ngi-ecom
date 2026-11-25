<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Design;
use App\Models\DesignArea;
use App\Models\DesignAsset;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\Proof;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Proof approval. A proof is generated from the order's mockups and emailed; the
 * customer approves it or asks for changes, which reopens the design and produces a
 * new proof. Only an approved (and paid) order is released to the print shop.
 */
class ProofService
{
    public function __construct(private ProofImage $image, private Settings $settings) {}

    /** Generate a proof from the order's print areas and send it to the customer. */
    public function create(Order $order): Proof
    {
        return $this->publish($order, $this->image->compose($order), replaced: false);
    }

    /** The admin uploads their own proof image in place of the generated one, and it is sent. */
    public function replace(Order $order, UploadedFile $file): Proof
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! in_array($mime, ['image/png', 'image/jpeg'], true) || ! @imagecreatefromstring($bytes)) {
            throw new InvalidArgumentException('Upload a PNG or JPG image for the proof.');
        }

        return $this->publish($order, $bytes, replaced: true, extension: $mime === 'image/png' ? 'png' : 'jpg');
    }

    private function publish(Order $order, string $bytes, bool $replaced, string $extension = 'jpg'): Proof
    {
        $proof = DB::transaction(function () use ($order, $bytes, $replaced, $extension) {
            $order->proofs()->whereIn('status', ['sent', 'changes_requested'])->update(['status' => 'superseded']);

            $version = (int) $order->proofs()->max('version') + 1;
            $path = "orders/{$order->id}/proofs/v{$version}.{$extension}";
            Storage::disk('designs')->put($path, $bytes);

            $proof = $order->proofs()->create(['version' => $version, 'image_path' => $path, 'status' => 'sent', 'replaced' => $replaced, 'sent_at' => now()]);
            $order->record('proof_sent', "Proof version {$version} sent for approval.");

            return $proof;
        });

        app(OrderService::class)->notify(
            $order, 'Your proof is ready', 'Your proof is ready to approve.',
            ['Please check the design, colours and placement carefully. Approve it and we will go ahead, or ask for changes.'],
            'Review your proof', $proof->url(),
        );

        return $proof;
    }

    public function approve(Proof $proof): void
    {
        abort_unless($proof->isOpen(), 409);

        $order = $proof->order;
        $orders = app(OrderService::class);

        DB::transaction(function () use ($proof, $order) {
            $proof->forceFill(['status' => 'approved', 'responded_at' => now()])->save();
            $order->forceFill(['status' => OrderStatus::ProofApproved, 'flagged' => false])->save();
            $order->record('proof_approved', 'Proof approved by the customer.');
        });

        if ($order->isPaid()) {
            $orders->release($order);

            return;
        }

        // Proof then pay: the customer can pay now, within the expiry window.
        $days = $this->settings->int('proof.unpaid_expiry_days') ?? 7;
        $order->forceFill(['proof_expires_at' => now()->addDays($days)])->save();

        $orders->notify($order, 'Proof approved: please pay', 'Thank you for approving your proof.', [
            "Pay within {$days} days and we will start printing straight away. After that the order is cancelled.",
        ], 'Pay now', $order->url());
    }

    public function requestChanges(Proof $proof, string $comment): void
    {
        abort_unless($proof->isOpen(), 409);

        $order = $proof->order;
        $max = $this->settings->int('proof.max_revisions') ?? 3;

        DB::transaction(function () use ($proof, $order, $comment, $max) {
            $proof->forceFill(['status' => 'changes_requested', 'comment' => $comment, 'responded_at' => now()])->save();
            $order->increment('revisions_used');
            $order->record('proof_changes', 'Changes requested: '.Str::limit($comment, 200));

            if ($order->revisions_used >= $max) {
                $order->forceFill(['flagged' => true])->save();
                $order->record('flagged', "Flagged for follow-up: {$order->revisions_used} rounds of changes requested.");
            }
        });
    }

    /**
     * Open a copy of an order line's design for editing by this visitor (the customer from
     * their email link, or an admin). Uploaded images are copied so the editor owns them.
     */
    public function startRevision(OrderLine $line, Request $request): Design
    {
        $line->loadMissing('order');

        return $this->cloneFromOrderLine($line, $request, $line->id, 'Changes for '.$line->order->number);
    }

    /**
     * A fresh, editable copy of an order line's design owned by this visitor. It joins the
     * original design's group, so bulk tiers that span designs still treat it as the same one.
     *
     * @param  int|null  $revisesLineId  set when the copy is to replace that order line's files on save
     */
    public function cloneFromOrderLine(OrderLine $line, Request $request, ?int $revisesLineId, string $name): Design
    {
        $line->loadMissing(['order', 'areas', 'items']);
        $product = Product::with('printAreas')->find($line->product_id);

        if (! $product || ! $product->active) {
            throw new InvalidArgumentException('This product is no longer available to edit.');
        }

        $colourId = $line->items->first()?->variant_id
            ? $product->variants()->whereKey($line->items->first()->variant_id)->value('product_colour_id')
            : null;

        $design = Design::create([
            'user_id' => $request->user()?->id,
            'session_token' => $request->user() ? null : $request->session()->get('designer_token'),
            'product_id' => $product->id,
            'product_colour_id' => $colourId ?? $product->colours()->value('id'),
            'name' => $name,
            'group_key' => $line->design_group_key ?: (string) Str::uuid(),
            'revises_order_line_id' => $revisesLineId,
        ]);

        $assetMap = [];

        foreach ($line->areas as $orderArea) {
            foreach ($orderArea->assets ?? [] as $asset) {
                if (isset($assetMap[$asset['id']]) || ! Storage::disk('designs')->exists($asset['path'])) {
                    continue;
                }

                $copyPath = 'uploads/revisions/'.Str::uuid().'.'.pathinfo($asset['path'], PATHINFO_EXTENSION);
                Storage::disk('designs')->copy($asset['path'], $copyPath);

                $assetMap[$asset['id']] = DesignAsset::create([
                    'user_id' => $request->user()?->id,
                    'session_token' => $request->user() ? null : $request->session()->get('designer_token'),
                    'path' => $copyPath, 'original_name' => $asset['name'], 'mime' => $asset['mime'] ?? 'image/png',
                    'kind' => $asset['kind'] ?? 'raster', 'width' => $asset['width'] ?? null, 'height' => $asset['height'] ?? null,
                    'bytes' => Storage::disk('designs')->size($copyPath),
                ])->id;
            }
        }

        foreach ($line->areas as $orderArea) {
            $printArea = $product->printAreas->firstWhere('name', $orderArea->area_name);

            if (! $printArea || ! $orderArea->state) {
                continue;
            }

            $base = "designs/{$design->id}/area-{$printArea->id}";
            Storage::disk('designs')->copy($orderArea->export_path, "$base.png");

            if ($orderArea->preview_path) {
                Storage::disk('designs')->copy($orderArea->preview_path, "$base-preview.png");
            }

            DesignArea::create([
                'design_id' => $design->id, 'print_area_id' => $printArea->id,
                'state' => $this->remapAssets($orderArea->state, $assetMap),
                'personalisation' => $orderArea->personalisation,
                'export_path' => "$base.png", 'preview_path' => $orderArea->preview_path ? "$base-preview.png" : null,
                'export_width' => $orderArea->export_width, 'export_height' => $orderArea->export_height, 'export_dpi' => $orderArea->export_dpi,
            ]);
        }

        return $design;
    }

    /**
     * The revised design was saved: replace the order line's print files with it and send a fresh proof.
     */
    public function applyRevision(Design $design): Order
    {
        $line = OrderLine::with('order')->findOrFail($design->revises_order_line_id);
        $order = $line->order;

        DB::transaction(function () use ($design, $line, $order) {
            foreach ($line->areas as $old) {
                Storage::disk('designs')->delete(array_filter([$old->export_path, $old->preview_path, $old->mockup_path]));
            }

            $line->areas()->delete();
            app(OrderSnapshot::class)->copy($order, $line, $design);

            $line->update(['design_id' => $design->id]);
            $design->update(['revises_order_line_id' => null]);
            $order->record('design_revised', 'The design was updated.');
        });

        $this->create($order->fresh());

        return $order;
    }

    /** Whether a revision can still be applied to this order. */
    public function canRevise(Order $order): bool
    {
        return $order->status === OrderStatus::AwaitingProof && ! $order->cancelled_at;
    }

    /**
     * Admin override of the proof timing for one order (e.g. a trusted school pays after approval).
     */
    public function overrideTiming(Order $order, string $timing, ?int $userId = null): void
    {
        if (! $order->proof_required || $order->isPaid()) {
            throw new InvalidArgumentException('The proof timing can only be changed on an unpaid order that needs a proof.');
        }

        if (in_array($order->status, [OrderStatus::ProofApproved], true) || $order->cancelled_at) {
            throw new InvalidArgumentException('The proof has already been approved, so the timing can no longer change.');
        }

        if ($order->proof_timing === $timing) {
            return;
        }

        $order->forceFill(['proof_timing' => $timing]);

        if ($timing === 'proof_then_pay' && $order->status === OrderStatus::PendingPayment) {
            $order->forceFill(['status' => OrderStatus::AwaitingProof])->save();
            $order->record('timing_changed', 'Proof timing changed to proof then pay.', $userId);
            $this->create($order);

            return;
        }

        if ($timing === 'pay_then_proof' && $order->status === OrderStatus::AwaitingProof && ! $order->isPaid()) {
            $order->forceFill(['status' => OrderStatus::PendingPayment])->save();
            $order->proofs()->whereIn('status', ['sent', 'changes_requested'])->update(['status' => 'superseded']);
            $order->record('timing_changed', 'Proof timing changed to pay then proof.', $userId);
            app(OrderService::class)->notify($order, 'Payment needed', 'Your order is ready for payment.', ['Pay now and we will send you a proof to approve before printing.'], 'Pay now', $order->url());

            return;
        }

        $order->save();
        $order->record('timing_changed', "Proof timing changed to {$timing}.", $userId);
    }

    /** @param array<int|string, int> $map old asset id => new asset id */
    private function remapAssets(string $state, array $map): string
    {
        $json = json_decode($state, true) ?: ['objects' => []];

        foreach ($json['objects'] as &$object) {
            $old = $object['assetId'] ?? null;

            if ($old !== null && isset($map[$old])) {
                $object['assetId'] = $map[$old];

                if (isset($object['src'])) {
                    $object['src'] = 'asset:'.$map[$old];
                }
            }
        }
        unset($object);

        return json_encode($json);
    }
}
