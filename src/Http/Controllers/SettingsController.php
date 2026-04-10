<?php

namespace Seat\ManualPap\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Seat\ManualPap\Models\CorporationWhitelist;

class SettingsController extends Controller
{
    public function index(): View
    {
        $corporations = CorporationWhitelist::all();

        // Resolve corporation names
        $corpIds = $corporations->pluck('corporation_id')->toArray();
        $corpNames = DB::table('corporation_infos')
            ->whereIn('corporation_id', $corpIds)
            ->pluck('name', 'corporation_id')
            ->toArray();

        return view('manualpap::settings', compact('corporations', 'corpNames'));
    }

    public function add(): RedirectResponse
    {
        $data = request()->validate([
            'corporation_name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($data['corporation_name']);

        // Search by exact name first, then LIKE
        $corp = DB::table('corporation_infos')
            ->where('name', $name)
            ->first();

        if (!$corp) {
            $corp = DB::table('corporation_infos')
                ->where('name', 'LIKE', '%' . $name . '%')
                ->first();
        }

        if (!$corp) {
            return redirect()->route('manualpap.settings')
                ->withInput()
                ->withErrors(['corporation_name' => trans('manualpap::manualpap.settings_corp_not_found')]);
        }

        // Check if already whitelisted
        if (CorporationWhitelist::find($corp->corporation_id)) {
            return redirect()->route('manualpap.settings')
                ->withInput()
                ->withErrors(['corporation_name' => trans('manualpap::manualpap.settings_corp_exists')]);
        }

        CorporationWhitelist::create([
            'corporation_id' => $corp->corporation_id,
        ]);

        return redirect()->route('manualpap.settings')
            ->with('success', trans('manualpap::manualpap.settings_corp_added', ['name' => $corp->name]));
    }

    public function remove(int $corporationId): RedirectResponse
    {
        CorporationWhitelist::destroy($corporationId);

        return redirect()->route('manualpap.settings')
            ->with('success', trans('manualpap::manualpap.settings_corp_removed'));
    }
}
