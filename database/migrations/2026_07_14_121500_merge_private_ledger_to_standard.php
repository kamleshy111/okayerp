<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update all existing accepted = 0 columns to accepted = 1
        DB::table('sales')->where('accepted', 0)->update(['accepted' => 1]);
        DB::table('purchases')->where('accepted', 0)->update(['accepted' => 1]);
        DB::table('estimates')->where('accepted', 0)->update(['accepted' => 1]);
        DB::table('sale_payments')->where('accepted', 0)->update(['accepted' => 1]);
        DB::table('purchase_payments')->where('accepted', 0)->update(['accepted' => 1]);
        
        // 2. We need to handle journal entries and accounts.
        // Since accounts were separated by name + accepted, there could be duplicates like:
        // Name="Cash", accepted=0 and Name="Cash", accepted=1.
        // We need to merge them to prevent duplicate accounts on the trial balance/general ledger.
        
        $duplicateAccounts = DB::table('accounts')
            ->select('user_id', 'name', DB::raw('COUNT(*) as count'))
            ->groupBy('user_id', 'name')
            ->having('count', '>', 1)
            ->get();
            
        foreach ($duplicateAccounts as $dup) {
            // Find the accepted=1 account (which we want to keep)
            $keepAccount = DB::table('accounts')
                ->where('user_id', $dup->user_id)
                ->where('name', $dup->name)
                ->where('accepted', 1)
                ->first();
                
            // Find the accepted=0 account (which we want to merge)
            $mergeAccount = DB::table('accounts')
                ->where('user_id', $dup->user_id)
                ->where('name', $dup->name)
                ->where('accepted', 0)
                ->first();
                
            if ($keepAccount && $mergeAccount) {
                // Update journal entries from duplicate account to keep account
                DB::table('journal_entries')
                    ->where('account_id', $mergeAccount->id)
                    ->update(['account_id' => $keepAccount->id]);
                    
                // Delete duplicate account
                DB::table('accounts')->where('id', $mergeAccount->id)->delete();
            }
        }
        
        // Update all remaining accounts and journal entries to accepted = 1
        DB::table('accounts')->where('accepted', 0)->update(['accepted' => 1]);
        DB::table('journal_entries')->where('accepted', 0)->update(['accepted' => 1]);
        
        // 3. Set users.ledger_pin to null
        DB::table('users')->update(['ledger_pin' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed since this is a destructive merging migration
    }
};
