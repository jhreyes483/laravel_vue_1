<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Traits\Sp;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Models\Role;
use App\Http\Helpers\Handler as HandlerPermisions;
use Spatie\Permission\Models\Permission as ModelPermission;
use Illuminate\Support\Facades\DB;

class RoleAssignController extends Controller
{
    use Sp;
    //


    public function getUsers(Request $request){
        // traiga los usuario por paginado
        $sp     = 'lsp_get_users';
        $params = [
            'p_offset' => HandlerPermisions::getOffsetTable($request)   ,
            'p_limit'  => $request->limit??2,
        ];     
        $resp  = $this->execSP($sp, $params);
        $count = ($resp['data'][0]->count??0);
        return HandlerPermisions::responseFormatTable($resp, $count , [] , $sp.json_encode($params), $this );
    }


    public function getRoles(Request $request){
        $roles = Role::all();
        return $this->responseApi->response(true, ['type' => 'success', 'content' => 'Roles'], ['roles']);
      
    }


    public function getPermissions(){
        // trae los permisos
        $permissions  = ModelPermission::all();
        return $this->responseApi->response(true, ['type' => 'success', 'content' => 'Permisos'], []);
    }

    public function getUserById(Request $request){
        $user  = User::find($request->id);
        $roles = Role::all();

      //$permissions ='table.investment';
      //$user->givePermissionTo($permissions); //asinar permiso

        if($user && is_object($user)){

           // DB::enableQueryLog();
            $permissionNames = $user->getPermissionNames();
           // dd(DB::getQueryLog());

            $userRoles = $user->roles()->pluck('id')->toArray();
            foreach( $roles as $role ){
                $usRole[$role->id]  = (in_array($role->id, $userRoles));
            }

            $response = [
                'user'          =>$user,
                'roles'         =>$roles,
                'usuarioRoles'  =>$usRole,
                'userPermissions' => $permissionNames

            ];
            return $this->responseApi->response(true, ['type' => 'success', 'content' => 'User'], $response);
        }else{
            return $this->responseApi->response(true, ['type' => 'error', 'content' => 'No existe el usuario'], []);
        }
    }

    public function getTableUserPermissions(Request $request) {
        $user            = User::find($request->entity_id);
        $permissionNames = $this->getPermissionsByEntity($user);
       $sp     = 'lsp_get_permissions';
       $params = [
           'p_offset' => HandlerPermisions::getOffsetTable($request)   ,
           'p_limit'  => $request->limit??2,
       ];     
       $resp  = $this->execSP($sp, $params);
       $count = ($resp['data'][0]->count??0);
      
       if($resp && $resp['data'] && count($resp['data'])){
            foreach($resp['data'] as $i => $permission){
                $resp['data'][$i]->entity_has_permission
                 = (in_array($permission->name, $permissionNames  ));
            }
       }
        return HandlerPermisions::responseFormatTable($resp, $count , [] , $sp.json_encode($params), $this );
    }


    public function removePermission(Request $request){
        if( $request->entity == 'user'){
            $entity         = User::find($request->entity_identity); // id user
        }
        if( $request->entity == 'rol'){
            $entity         = Role::findByName($request->entity_identity); // nombre roll
        }

        $permission = $entity->permissions()->where('name', $request->name)->first();
        if($permission){
            $entity->revokePermissionTo($permission);
            $entity->save();
        }
        $permissionNames = $this->getPermissionsByEntity($entity);
        return $this->responseApi->response(true, ['type' => 'success', 'content' => 'Actulizo permisos de '.$request->entity], ['permissions'=>$permissionNames]);
    }


    public function addPermission(Request $request){
        if( $request->entity == 'user'){
            $entity         = User::find($request->entity_identity); // id user
        }
        if( $request->entity == 'rol'){
            $permission = $entity->permissions()->where('name', $request->name)->first(); // nombre roll
        }

       // $permission = Permission::findOrCreate($request->name, $request->guard_name);

        $permission = ModelPermission::where('name', $request->name)->first();

        if (!$permission) {
            $permission = ModelPermission::create(['name' => $request->name]);
        }

        if($permission){
            $entity->givePermissionTo($permission);
            $entity->save();
        }
        $permissionNames = $this->getPermissionsByEntity($entity);
        return $this->responseApi->response(true, ['type' => 'success', 'content' => 'Actulizo permisos de '.$request->entity], ['permissions'=>$permissionNames]);
    }

    private function getPermissionsByEntity($entity){
        $permissionNames = $entity->getPermissionNames();
        $permissionNames = array_values((array)  $permissionNames)[0];
        return  $permissionNames;
    }

    public function updateUserRoles(Request $request ){


        $user = User::find($request->user_id);

        $t = $user->roles()->sync($request->rolesSelected);

        $userRoles = $user->roles()->pluck('id')->toArray();



        $roles = $user->roles()->select('id', 'name')->get();
       foreach ($roles as $role) {
            $response['roles_user'] = ['id' => $role->id, 'name' => $role->name];
       }
       $response['roles_selected'] = $request->rolesSelected;

        return $this->responseApi->response(true, ['type' => 'success', 'content' => 'Actulizo roles de usuario'], ['roles_user' => $response]);
       
    }
}
