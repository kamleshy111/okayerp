<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\ReferralUser;
use App\Models\ReferralSale;

class ReferralUserController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $referralUsers = ReferralUser::where('user_id', $userId)
            ->withCount('sales')
            ->withSum('referralSales', 'sale_amount')
            ->latest()
            ->get();

        return Inertia::render('Referral/Index', [
            'referralUsers' => $referralUsers,
        ]);
    }

    public function create()
    {
        return Inertia::render('Referral/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
        ], [
            'name.required' => 'Referral user name is required.',
        ]);

        ReferralUser::create([
            'user_id' => Auth::id(),
            'name'    => $validated['name'],
            'phone'   => $validated['phone'] ?? null,
            'email'   => $validated['email'] ?? null,
            'notes'   => $validated['notes'] ?? null,
        ]);

        return redirect()->route('referral-user.index')
            ->with('success', 'Referral user created successfully.');
    }

    public function edit($id)
    {
        $referralUser = ReferralUser::where('user_id', Auth::id())->findOrFail($id);

        return Inertia::render('Referral/Edit', [
            'referralUser' => $referralUser,
        ]);
    }

    public function update(Request $request, $id)
    {
        $referralUser = ReferralUser::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
        ]);

        $referralUser->update($validated);

        return redirect()->route('referral-user.index')
            ->with('success', 'Referral user updated successfully.');
    }

    public function destroy($id)
    {
        $referralUser = ReferralUser::where('user_id', Auth::id())->findOrFail($id);
        $referralUser->delete();

        return response()->json(['message' => 'Referral user deleted successfully.']);
    }

    public function search(Request $request)
    {
        $query = $request->query('query');
        $userId = Auth::id();

        if (!$query) {
            $referralUsers = ReferralUser::where('user_id', $userId)
                ->orderByRaw('(SELECT MAX(created_at) FROM referral_sales WHERE referral_sales.referral_user_id = referral_users.id) DESC')
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get();
            return response()->json($referralUsers);
        }

        $referralUsers = ReferralUser::where('user_id', $userId)
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('phone', 'LIKE', "%{$query}%");
            })
            ->orderByRaw('(SELECT MAX(created_at) FROM referral_sales WHERE referral_sales.referral_user_id = referral_users.id) DESC')
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();

        return response()->json($referralUsers);
    }

    public function tracker($id)
    {
        $userId = Auth::id();
        $referralUser = ReferralUser::where('user_id', $userId)->findOrFail($id);

        // Fetch referred sales history
        $referredSales = ReferralSale::where('referral_user_id', $id)
            ->with(['sale.customer'])
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'invoice_no' => "Invoice #" . $item->sale_id,
                    'customer_name' => $item->sale->customer->name ?? 'N/A',
                    'sale_amount' => $item->sale_amount,
                    'date' => $item->created_at->format('d-m-Y'),
                ];
            });

        // Fetch all offers
        $offers = \App\Models\Offer::where('user_id', $userId)->latest()->get();

        $achievedOffers = [];
        $inProgressOffers = [];
        $expiredOffers = [];

        $today = date('Y-m-d');

        foreach ($offers as $offer) {
            $totalSalesInPeriod = ReferralSale::where('referral_user_id', $id)
                ->whereBetween('created_at', [
                    $offer->start_date . ' 00:00:00',
                    $offer->end_date . ' 23:59:59'
                ])
                ->sum('sale_amount');

            $progressPercentage = $offer->target_amount > 0 
                ? round(($totalSalesInPeriod / $offer->target_amount) * 100, 2) 
                : 0;

            $data = [
                'id' => $offer->id,
                'title' => $offer->title,
                'target_amount' => $offer->target_amount,
                'start_date' => date('d-m-Y', strtotime($offer->start_date)),
                'end_date' => date('d-m-Y', strtotime($offer->end_date)),
                'reward_description' => $offer->reward_description,
                'total_sales' => $totalSalesInPeriod,
                'percentage' => min(100, $progressPercentage),
            ];

            if ($totalSalesInPeriod >= $offer->target_amount) {
                $achievedOffers[] = $data;
            } elseif ($today <= $offer->end_date) {
                $inProgressOffers[] = $data;
            } else {
                $expiredOffers[] = $data;
            }
        }

        return Inertia::render('Referral/Tracker', [
            'referralUser' => $referralUser,
            'referredSales' => $referredSales,
            'achievedOffers' => $achievedOffers,
            'inProgressOffers' => $inProgressOffers,
            'expiredOffers' => $expiredOffers,
        ]);
    }
}
