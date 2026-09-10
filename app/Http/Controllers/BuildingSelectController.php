<?php

namespace App\Http\Controllers;

use App\Models\Building;
use Illuminate\Http\Request;

class BuildingSelectController extends Controller
{
    public function show()
    {
        abort_if(auth()->user()->isAdmin(), 404); // admins never need this screen
       $buildings = Building::where('code', '!=', 'NG')->orderBy('name')->get();
        return view('auth.select-building', compact('buildings'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'building_id' => 'required|exists:buildings,id',
        ]);

        $request->session()->put('assigned_building_id', (int) $data['building_id']);

        return redirect()->route('scanner.index');
    }
}