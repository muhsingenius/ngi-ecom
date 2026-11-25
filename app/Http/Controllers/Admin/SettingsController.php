<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('admin.settings', ['values' => $settings->all(), 'definitions' => Settings::definitions()]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $errors = [];

        foreach (Settings::definitions() as $key => $definition) {
            $field = str_replace('.', '_', $key);

            if (! $request->has($field)) {
                continue;
            }

            try {
                $settings->set($key, $request->input($field));
            } catch (ValidationException $e) {
                $errors[$field] = collect($e->errors()['value'] ?? [])->first();
            }
        }

        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        return back()->with('status', 'Settings saved.');
    }
}
