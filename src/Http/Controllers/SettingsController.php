<?php

namespace Seat\ManualPap\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Seat\ManualPap\Models\CharacterBlocklist;
use Seat\ManualPap\Models\CorporationWhitelist;
use Seat\Eveapi\Models\Character\CharacterInfo;

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

        // Blocklist
        $blocklist = CharacterBlocklist::all();
        $blockCharIds = $blocklist->pluck('character_id')->toArray();
        $blockCharNames = CharacterInfo::whereIn('character_id', $blockCharIds)
            ->pluck('name', 'character_id')
            ->toArray();

        return view('manualpap::settings', compact('corporations', 'corpNames', 'blocklist', 'blockCharNames'));
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

    public function addBlocklist(): RedirectResponse
    {
        $data = request()->validate([
            'character_name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($data['character_name']);

        // Search by exact name first, then LIKE
        $char = CharacterInfo::where('name', $name)->first();

        if (!$char) {
            $char = CharacterInfo::where('name', 'LIKE', '%' . $name . '%')->first();
        }

        if (!$char) {
            return redirect()->route('manualpap.settings')
                ->withInput()
                ->withErrors(['character_name' => trans('manualpap::manualpap.blocklist_not_found')]);
        }

        if (CharacterBlocklist::find($char->character_id)) {
            return redirect()->route('manualpap.settings')
                ->withInput()
                ->withErrors(['character_name' => trans('manualpap::manualpap.blocklist_exists')]);
        }

        CharacterBlocklist::create([
            'character_id' => $char->character_id,
        ]);

        return redirect()->route('manualpap.settings')
            ->with('success', trans('manualpap::manualpap.blocklist_added', ['name' => $char->name]));
    }

    public function removeBlocklist(int $characterId): RedirectResponse
    {
        CharacterBlocklist::destroy($characterId);

        return redirect()->route('manualpap.settings')
            ->with('success', trans('manualpap::manualpap.blocklist_removed'));
    }
}
