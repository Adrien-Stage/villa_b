<?php
use App\Models\{User,Tenant,RestaurantCustomerOrder,PermissionGrant};
use App\Services\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);
test('audit anonymous visitor reads a staff order by sequential id', function () {
    activerModules(['restaurant']);
    $tenant=Tenant::create(['name'=>'Audit fictif','slug'=>'audit-fictif','is_active'=>true]);
    $order=RestaurantCustomerOrder::create(['source'=>'staff','table_number'=>'AUDIT42','status'=>'pending','total_amount'=>123400,'placed_at'=>now()]);
    $this->get(route('portal.restaurant.order',['tenant'=>$tenant->slug,'order'=>$order->id]))->assertOk()->assertSee('AUDIT42')->assertSee('1 234');
});
test('audit personal allow overrides ERP role deny', function () {
    $u=User::factory()->create(['role'=>'purchasing_manager']);
    foreach ([['role','purchasing_manager','deny',PermissionGrant::ORIGINE_ERP],['user',(string)$u->id,'allow',PermissionGrant::ORIGINE_ETABLISSEMENT]] as [$type,$id,$effect,$origin]) {
        PermissionGrant::create(['subject_type'=>$type,'subject_id'=>$id,'permission'=>'economat.items.modifier','effect'=>$effect,'origin'=>$origin,'reason'=>'Test audit fictif']);
    }
    expect(app(PermissionResolver::class)->allows($u,'economat.items.modifier'))->toBeTrue();
});
test('audit initial account password is not forced to change', function () {
    App\Support\RoleCatalog::sync();
    $admin=User::factory()->create(['role'=>'admin']);
    $this->actingAs($admin)->post(route('users.store'),['name'=>'Audit Fictif','email'=>'audit-new@example.test','roles'=>['reception'],'password'=>'Audit-only-9284','password_confirmation'=>'Audit-only-9284','is_active'=>true])->assertSessionHasNoErrors();
    expect(User::where('email','audit-new@example.test')->sole()->must_change_password)->toBeFalse();
});
