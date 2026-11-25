<?php

namespace App\Http\Controllers;

use App\Models\Design;
use App\Models\Order;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function orders(Request $request): View
    {
        return view('account.orders', [
            'orders' => Order::where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function designs(Request $request): View
    {
        $designs = Design::where('user_id', $request->user()->id)->with(['product', 'colour', 'areas'])->latest()->get();

        return view('account.designs', [
            'designs' => $designs,
            'previews' => $designs->mapWithKeys(fn (Design $d) => [$d->id => ($a = $d->areas->first(fn ($area) => $area->preview_path)) ? PrivateFile::url($a->preview_path, now()->addHour()) : null]),
        ]);
    }

    public function destroyDesign(Request $request, Design $design): RedirectResponse
    {
        abort_unless($design->user_id === $request->user()->id, 404);

        // Orders keep their own copies of the print files, so these can go.
        Storage::disk('designs')->deleteDirectory("designs/{$design->id}");
        $design->delete();

        return back()->with('status', 'Design deleted.');
    }
}
