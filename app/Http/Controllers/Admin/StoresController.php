<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;


class StoresController extends Controller
{
    public function index(){

        $stores = User::where('role', 'store')->get();
        return Inertia::render('Admin/Store/Store',[
            'stores' => $stores, 
        ]);
    }

    public function create(){

        return Inertia::render('Admin/Store/Create');
    }

    public function userCreate(){

        return Inertia::render('Admin/Store/Create');
    }

    public function getAdmin(){
        $users = User::where('role', 'admin')->get();
        return Inertia::render('Admin/Admin',[
            'users' => $users, 
        ]);
    }


    public function store(Request $request)
    {
        // Validate input
        $request->validate([
            'email'         => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'bank_name'      => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'ifsc_code'      => ['nullable', 'string', 'max:255'],
            'branch_name'    => ['nullable', 'string', 'max:255'],
            'gstin'          => ['nullable', 'string', 'max:255'],
        ]);

        // Create User
        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'phone'     => $request->phone ?? '',
            'address'   => $request->address ?? '',
            'password'  => Hash::make($request->password),
            'role'      => $request->role === 'admin' ? 'admin' : 'store',
            'bank_name'      => $request->bank_name ?? '',
            'account_number' => $request->account_number ?? '',
            'ifsc_code'      => $request->ifsc_code ?? '',
            'branch_name'    => $request->branch_name ?? '',
            'gstin'          => $request->gstin ?? '',
        ]);

        return response()->json([
            'message' => 'Store created successfully!',
            'user'    => $user
        ], 201);
    }

    public function edit($storeId){

        $data = User::find($storeId);

        if (!$data) {
            return response()->json(["message" => 'Store not found.']);
        }

        $storesDetail = [
            'id'   => $data->id ?? 0,
            'name' => $data->name ?? '',
            'phone' => $data->phone ?? '',
            'email' => $data->email ?? '',
            'profile_photo' => $data->profile_photo ?? '',
            'address' => $data->address ?? '',
            'bank_name' => $data->bank_name ?? '',
            'account_number' => $data->account_number ?? '',
            'ifsc_code' => $data->ifsc_code ?? '',
            'branch_name' => $data->branch_name ?? '',
            'gstin' => $data->gstin ?? '',
        ];

        return Inertia::render('Admin/Store/Edit',[
            'storesDetail' => $storesDetail,
        ]);
    }

    public function update(Request $request, $storeId)
    {
        $store = User::find($storeId);

        if (!$store) {
            return response()->json(["message" => 'Store not found.'], 404);
        }

        // Validation
        $request->validate([
            'name'   => 'required|string|max:255',
            'phone'  => 'nullable|string|max:20',
            'email'  => 'required|email|unique:users,email,' . $storeId,
            'address'=> 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'ifsc_code' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'gstin' => 'nullable|string|max:255',
        ]);

        // Update fields
        $store->name    = $request->name;
        $store->phone   = $request->phone;
        $store->email   = $request->email;
        $store->address = $request->address;
        $store->bank_name = $request->bank_name;
        $store->account_number = $request->account_number;
        $store->ifsc_code = $request->ifsc_code;
        $store->branch_name = $request->branch_name;
        $store->gstin = $request->gstin;

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            if ($store->profile_photo && Storage::disk('public')->exists($store->profile_photo)) {
                Storage::disk('public')->delete($store->profile_photo);
            }

            $file = $request->file('profile_photo');
            $filename = time() . '.' . $file->getClientOriginalExtension();

            $path = $file->storeAs('uploads/image', $filename, 'public');
            $store->profile_photo = $path;
        }


        $store->save();

        return response()->json([
            'message' => 'Store updated successfully!',
            'store'   => $store
        ]);
    }

    public function switch_start($userId){

        $newUser = User::find($userId);

        switch(true)
        {
            case $newUser->role === 'admin' && Auth::user()->role <> 'admin':
                break;
            case  $newUser->role === 'admin' && Auth::user()->role === 'admin':
            default:
                Session::put( 'orig_user', Auth::id() );
                Auth::login($newUser);
        }

        return redirect()->route('dashboard');
    }

    public function user_switch_stop(){

        $id = Session::pull( 'orig_user' );
        if($id){
        $orig_user = User::find($id);
        Auth::login( $orig_user );
        return redirect()->route('dashboard');
        }else{
            return redirect()->route('dashboard');
        }

    }

    public function destroy($id){
        
        $user = User::find($id);

        if($user) {
            $user->delete();
            return response()->json(['message' => 'Store deleted successfully.'], 200); 
        }
    
        return response()->json(['message' => 'Store not found.'], 404);
    }

    public function editPermissions($id)
    {
        $user = User::findOrFail($id);
        
        // Fetch all permissions in the system
        $permissions = \Spatie\Permission\Models\Permission::all(['id', 'name']);
        
        // Fetch direct permissions assigned to this user
        $userPermissions = $user->getDirectPermissions()->pluck('name');
        
        return response()->json([
            'userName' => $user->name,
            'permissions' => $permissions,
            'userPermissions' => $userPermissions,
        ]);
    }

    public function updatePermissions(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'permissions' => 'nullable|array',
        ]);
        
        // Sync direct permissions to this user (updates model_has_permissions)
        $user->syncPermissions($request->permissions ?? []);
        
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        return response()->json([
            'message' => 'Store permissions updated successfully!',
        ]);
    }
}
