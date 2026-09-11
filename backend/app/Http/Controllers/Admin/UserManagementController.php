<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:12'],
            'phone' => ['nullable','string','max:32'],
            'role' => ['required','in:admin,manager,employee,customer,owner'],
            'permissions' => ['nullable','array'],
            'permissions.*' => ['string'],
            'is_active' => ['nullable','boolean'],
        ]);
        $actorRole = $request->attributes->get('authRole') ?: 'admin';
        if (in_array($data['role'], ['admin','owner'], true) && $actorRole !== 'admin') {
            return response()->json(['ok'=>false,'message'=>'Only the owner can create an owner account'],403);
        }
        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name'=>$data['name'],'email'=>strtolower($data['email']),'password'=>$data['password'], 'is_active'=>(bool)($data['is_active'] ?? true)]);
            DB::table('profiles')->updateOrInsert(['id'=>(string)$user->id],[
                'full_name'=>$data['name'],'phone'=>$data['phone'] ?? null,'created_at'=>now(),'updated_at'=>now()
            ]);
            DB::table('user_roles')->updateOrInsert(['user_id'=>(string)$user->id],[
                'role'=>($data['role'] === 'owner' ? 'admin' : $data['role']),'created_at'=>now(),'updated_at'=>now()
            ]);
            if (array_key_exists('permissions', $data)) {
                $valid=array_values(array_intersect(array_unique($data['permissions'] ?? []), array_keys(config('permissions',[]))));
                foreach (array_keys(config('permissions', [])) as $permission) {
                    DB::table('user_permissions')->insert(['user_id'=>(string)$user->id,'permission'=>$permission,'allowed'=>in_array($permission,$valid,true),'created_at'=>now(),'updated_at'=>now()]);
                }
            }
            return $user;
        });
        return response()->json(['ok'=>true,'user_id'=>(string)$user->id],201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'email' => ['sometimes','email','max:255','unique:users,email,'.$id],
            'password' => ['nullable','string','min:12'],
            'phone' => ['nullable','string','max:32'],
            'role' => ['sometimes','in:admin,manager,employee,customer,owner'],
            'permissions' => ['nullable','array'],
            'permissions.*' => ['string'],
            'is_active' => ['nullable','boolean'],
        ]);
        $actor = $request->attributes->get('authUser');
        $actorId=(string)($actor->id ?? '');
        $actorRole=$request->attributes->get('authRole') ?: 'admin';
        if ($actorId === (string)$id && isset($data['role']) && $data['role'] !== 'admin') {
            return response()->json(['ok'=>false,'message'=>'The owner cannot remove their own owner role'],403);
        }
        if ($actorRole !== 'admin' && (($data['role'] ?? null) === 'admin' || $actorId === (string)$id)) {
            return response()->json(['ok'=>false,'message'=>'Only the owner can change owner-level accounts'],403);
        }
        $user=User::findOrFail($id);
        $payload=[];
        if(isset($data['name'])) $payload['name']=$data['name'];
        if(isset($data['email'])) $payload['email']=strtolower($data['email']);
        if(!empty($data['password'])) $payload['password']=$data['password'];
        if(array_key_exists('is_active',$data)) $payload['is_active']=(bool)$data['is_active'];
        if($payload) $user->update($payload);
        $profile=DB::table('profiles')->where('id',(string)$id)->first();
        if(array_key_exists('name',$data) || array_key_exists('phone',$data)) {
            DB::table('profiles')->updateOrInsert(['id'=>(string)$id],[
                'full_name'=>$data['name'] ?? $user->name,
                'phone'=>array_key_exists('phone',$data)?$data['phone']:($profile->phone ?? null),
                'created_at'=>$profile?->created_at ?? now(), 'updated_at'=>now()
            ]);
        }
        if(isset($data['role'])) DB::table('user_roles')->updateOrInsert(['user_id'=>(string)$id],['role'=>($data['role'] === 'owner' ? 'admin' : $data['role']),'created_at'=>now(),'updated_at'=>now()]);
        if(array_key_exists('permissions',$data)) {
            $valid=array_values(array_intersect(array_unique($data['permissions'] ?? []), array_keys(config('permissions',[]))));
            DB::transaction(function() use($id,$valid){
                DB::table('user_permissions')->where('user_id',(string)$id)->delete();
                foreach(array_keys(config('permissions', [])) as $permission) DB::table('user_permissions')->insert(['user_id'=>(string)$id,'permission'=>$permission,'allowed'=>in_array($permission,$valid,true),'created_at'=>now(),'updated_at'=>now()]);
            });
        }
        return response()->json(['ok'=>true]);
    }

    public function permissions(string $id): JsonResponse
    {
        $role=DB::table('user_roles')->where('user_id',(string)$id)->value('role');
        $effective=PermissionService::forUser((string)$id,$role);
        return response()->json(['ok'=>true,'role'=>$role,'permissions'=>$effective,'catalog'=>config('permissions',[])]);
    }
}
