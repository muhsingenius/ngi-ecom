<?php

namespace App\Http\Controllers;

use App\Models\OrderLine;
use App\Models\Proof;
use App\Services\ProofService;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The customer's proof page. The long random token in the emailed link is the credential,
 * so no account is needed (guest checkout).
 */
class ProofController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $proof = $this->find($token);
        $order = $proof->order->load(['lines.items', 'lines.areas']);

        // An old link goes to the newest proof rather than showing something out of date.
        if ($proof->status === 'superseded' && ($latest = $order->latestProof())) {
            return redirect($latest->url());
        }

        return view('proofs.show', [
            'proof' => $proof,
            'order' => $order,
            'canRevise' => app(ProofService::class)->canRevise($order),
            'maxRevisions' => app(Settings::class)->int('proof.max_revisions') ?? 3,
        ]);
    }

    public function image(string $token): StreamedResponse
    {
        $proof = $this->find($token);

        return Storage::disk('designs')->response($proof->image_path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    public function approve(string $token, ProofService $proofs): RedirectResponse
    {
        $proof = $this->find($token);
        $proofs->approve($proof);

        return redirect($proof->url())->with('status', 'Thank you. Your proof is approved.');
    }

    public function changes(Request $request, string $token, ProofService $proofs): RedirectResponse
    {
        $proof = $this->find($token);
        $data = $request->validate(['comment' => ['required', 'string', 'min:5', 'max:2000']], ['comment.required' => 'Tell us what you would like changed.', 'comment.min' => 'Tell us a little more about what to change.']);

        $proofs->requestChanges($proof, $data['comment']);

        return redirect($proof->url())->with('status', 'Thanks, we have your request. Edit the design below, or wait for us to update it.');
    }

    /** Open a copy of one of the order's designs in the designer. */
    public function revise(string $token, OrderLine $line, ProofService $proofs, Request $request): RedirectResponse
    {
        $proof = $this->find($token);

        abort_unless($line->order_id === $proof->order_id && $proof->status === 'changes_requested' && $proofs->canRevise($proof->order), 403);

        try {
            $design = $proofs->startRevision($line, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['revise' => $e->getMessage()]);
        }

        return redirect()->route('designs.edit', $design);
    }

    private function find(string $token): Proof
    {
        return Proof::with('order')->where('token', $token)->firstOrFail();
    }
}
