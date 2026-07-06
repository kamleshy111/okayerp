<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Offer;
use App\Models\ReferralUser;
use App\Models\ReferralSale;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $offers = Offer::where('user_id', $userId)->latest()->get();

        // Calculate progress metrics for each active/inactive offer
        $offersData = $offers->map(function ($offer) use ($userId) {
            // Find all referral users and calculate their total sales within start_date & end_date
            $referralPerformance = ReferralUser::where('user_id', $userId)
                ->get()
                ->map(function ($referral) use ($offer) {
                    $totalReferredSales = ReferralSale::where('referral_user_id', $referral->id)
                        ->whereBetween('created_at', [
                            $offer->start_date . ' 00:00:00',
                            $offer->end_date . ' 23:59:59'
                        ])
                        ->sum('sale_amount');

                    $progressPercentage = $offer->target_amount > 0 
                        ? round(($totalReferredSales / $offer->target_amount) * 100, 2) 
                        : 0;

                    return [
                        'referral_user_id' => $referral->id,
                        'name' => $referral->name,
                        'phone' => $referral->phone,
                        'total_sales' => $totalReferredSales,
                        'percentage' => min(100, $progressPercentage),
                        'achieved' => $totalReferredSales >= $offer->target_amount,
                    ];
                })
                // Sort by highest sales referred in the timeframe
                ->sortByDesc('total_sales')
                ->values();

            return [
                'id' => $offer->id,
                'title' => $offer->title,
                'target_amount' => $offer->target_amount,
                'start_date' => date('d-m-Y', strtotime($offer->start_date)),
                'end_date' => date('d-m-Y', strtotime($offer->end_date)),
                'reward_description' => $offer->reward_description,
                'performance' => $referralPerformance,
            ];
        });

        return Inertia::render('Offer/Index', [
            'offers' => $offersData,
        ]);
    }

    public function create()
    {
        return Inertia::render('Offer/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reward_description' => 'nullable|string',
        ]);

        Offer::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'target_amount' => $validated['target_amount'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reward_description' => $validated['reward_description'],
        ]);

        return redirect()->route('offer.index')
            ->with('success', 'Offer created successfully.');
    }

    public function edit($id)
    {
        $offer = Offer::where('user_id', Auth::id())->findOrFail($id);

        return Inertia::render('Offer/Edit', [
            'offer' => $offer,
        ]);
    }

    public function update(Request $request, $id)
    {
        $offer = Offer::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reward_description' => 'nullable|string',
        ]);

        $offer->update($validated);

        return redirect()->route('offer.index')
            ->with('success', 'Offer updated successfully.');
    }

    public function destroy($id)
    {
        $offer = Offer::where('user_id', Auth::id())->findOrFail($id);
        $offer->delete();

        return response()->json(['message' => 'Offer deleted successfully.']);
    }
}
