<?php

namespace App\Http\Controllers;

use App\Models\DesignAsset;
use App\Services\DesignUploads;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DesignAssetController extends Controller
{
    /** Fresh signed URLs for assets the visitor owns (saved designs hold ids, not expiring links). */
    public function urls(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))->map(fn ($id) => (int) $id)->filter()->take(100);

        $urls = DesignAsset::ownedBy($request)->whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($asset) => [$asset->id => PrivateFile::url($asset->path, now()->addHours(6))]);

        return response()->json($urls);
    }

    public function store(Request $request, DesignUploads $uploads): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']], ['file.required' => 'Choose an image to upload.']);

        try {
            $asset = $uploads->store($request->file('file'), $request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $asset->id,
            'kind' => $asset->kind,
            'width' => $asset->width,
            'height' => $asset->height,
            'name' => $asset->original_name,
            'url' => PrivateFile::url($asset->path, now()->addHours(6)),
        ], 201);
    }
}
