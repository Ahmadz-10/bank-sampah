<?php

namespace App\Http\Controllers;

use App\Http\Requests\RwRequest;
use App\Models\Rw;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** No destroy: an RW is referenced by households and weighings, so it is deactivated instead. */
class RwController extends Controller
{
    public function index(): View
    {
        return view('rws.index', ['rws' => Rw::query()->withCount('households')->orderBy('number')->get()]);
    }

    public function create(): View
    {
        $rw = new Rw;
        $rw->number = ((int) Rw::query()->max('number')) + 1;

        return view('rws.form', compact('rw'));
    }

    public function store(RwRequest $request): RedirectResponse
    {
        $rw = Rw::create($request->validated());

        return redirect()->route('rws.index')->with('status', __(':name was created.', ['name' => $rw->name]));
    }

    public function edit(Rw $rw): View
    {
        return view('rws.form', compact('rw'));
    }

    public function update(RwRequest $request, Rw $rw): RedirectResponse
    {
        $rw->update($request->validated());

        return redirect()->route('rws.index')->with('status', __(':name was updated.', ['name' => $rw->name]));
    }
}
