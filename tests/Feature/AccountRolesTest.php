<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Billing\InvoiceIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rôles comptable et super administrateur. */
class AccountRolesTest extends TestCase
{
    use RefreshDatabase;

    private function invoice()
    {
        $order = Order::factory()->create(['customer_id' => Customer::factory(), 'status' => OrderStatus::HandedOver,
            'subtotal_cents' => 1000, 'total_cents' => 1000]);

        return app(InvoiceIssuer::class)->issue($order, null, false);
    }

    public function test_accountant_reads_invoices_and_reports_only(): void
    {
        $invoice = $this->invoice();
        $this->actingAs(User::factory()->accountant()->create());

        $this->get('/gestion')->assertRedirect(route('admin.invoices.index'));
        $this->get(route('admin.invoices.index'))->assertOk()->assertSee($invoice->number)
            ->assertSee('Rapports')->assertDontSee('Commandes</a>', false)->assertDontSee('Interac');
        $this->get(route('admin.invoices.show', $invoice))->assertOk()
            ->assertDontSee(route('admin.invoices.credit', $invoice))->assertDontSee(route('admin.orders.show', $invoice->order));
        $this->get(route('admin.invoices.pdf', $invoice))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();

        foreach ([route('admin.orders.index'), route('admin.products.index'), route('admin.customers.index'),
            route('admin.receipts.create'), route('admin.settings.edit'), route('admin.users.index'),
            route('admin.payments.reconcile'), route('admin.invoices.credit', $invoice)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('admin.payments.store', $invoice->order), ['method' => 'comptant', 'amount' => '10'])->assertForbidden();
    }

    public function test_employee_has_no_reports(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_manager_manages_employees_and_accountants_but_not_managers(): void
    {
        $manager = User::factory()->manager()->create();
        $other = User::factory()->manager()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($manager);

        $this->get(route('admin.users.create'))->assertOk()->assertSee('Comptable')->assertDontSee('Super administrateur</option>', false);
        $this->post(route('admin.users.store'), ['name' => 'Compta', 'email' => 'compta@example.com', 'role' => 'comptable',
            'password' => 'secret-compta-1', 'password_confirmation' => 'secret-compta-1'])->assertRedirect();
        $this->assertSame(UserRole::Accountant, User::where('email', 'compta@example.com')->sole()->role);

        $this->post(route('admin.users.store'), ['name' => 'G2', 'email' => 'g2@example.com', 'role' => 'gerant',
            'password' => 'secret-gerant-1', 'password_confirmation' => 'secret-gerant-1'])->assertSessionHasErrors('role');

        $this->get(route('admin.users.edit', $other))->assertForbidden();
        $this->put(route('admin.users.update', $admin), ['name' => 'X', 'email' => $admin->email, 'role' => 'employe', 'is_active' => '0'])
            ->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_has_every_right_and_manages_managers(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $this->actingAs($admin);

        foreach ([route('admin.dashboard'), route('admin.orders.index'), route('admin.reports.index'),
            route('admin.settings.edit'), route('admin.users.index')] as $url) {
            $this->get($url)->assertOk();
        }

        $this->put(route('admin.users.update', $manager), ['name' => $manager->name, 'email' => $manager->email, 'role' => 'gerant', 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($manager->fresh()->is_active);

        // Il ne peut ni se rétrograder ni se désactiver : il reste toujours un super administrateur actif
        $this->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'gerant', 'is_active' => '1'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_role_can_be_changed_from_the_command_line(): void
    {
        $user = User::factory()->manager()->create(['email' => 'patron@example.com']);

        $this->artisan('users:role', ['email' => 'patron@example.com', 'role' => 'admin'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());
        $this->artisan('users:role', ['email' => 'inconnu@example.com', 'role' => 'admin'])->assertFailed();
    }
}
