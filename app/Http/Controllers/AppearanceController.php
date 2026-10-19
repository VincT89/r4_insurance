<?php

namespace App\Http\Controllers;

use App\Support\Appearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.appearance', [
            'fields' => collect(Appearance::FIELDS)->groupBy('group', preserveKeys: true),
            'colors' => Appearance::colors($request->user()->appearance),
            'defaults' => Appearance::defaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = ['colors' => ['required', 'array:'.implode(',', array_keys(Appearance::FIELDS))], 'user_id' => ['prohibited']];
        $attributes = [];
        foreach (Appearance::FIELDS as $key => $field) {
            $rules['colors.'.$key] = ['required', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/'];
            $attributes['colors.'.$key] = $field['label'];
        }
        $values = $request->validate($rules, ['regex' => 'Il campo :attribute deve contenere un colore nel formato #123abc.'], $attributes);
        $request->user()->forceFill(['appearance' => Appearance::colors($values['colors'])])->save();

        return to_route('appearance.edit')->with('success', 'Aspetto personale salvato.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['appearance' => null])->save();

        return to_route('appearance.edit')->with('success', 'Colori iniziali ripristinati per il tuo account.');
    }
}
