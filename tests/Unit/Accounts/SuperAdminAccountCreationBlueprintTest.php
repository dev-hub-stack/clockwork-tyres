<?php

namespace Tests\Unit\Accounts;

use App\Modules\Accounts\Support\SuperAdminAccountCreationBlueprint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuperAdminAccountCreationBlueprintTest extends TestCase
{
    #[Test]
    public function it_describes_the_super_admin_supplier_account_flow_without_impersonation_or_approval_queue(): void
    {
        $blueprint = new SuperAdminAccountCreationBlueprint();

        $this->assertTrue($blueprint->canCreateSupplierAccounts());
        $this->assertTrue($blueprint->canManageSupplierAccounts());
        $this->assertFalse($blueprint->canImpersonateAccounts());
        $this->assertFalse($blueprint->usesManualApprovalQueue());
        $this->assertSame([
            'Create supplier account directly',
            'Assign wholesale capability and account type',
            'Configure the base subscription and reports add-on',
            'Publish the account without an approval queue',
        ], $blueprint->accountCreationFlow());

        $this->assertSame('name', $blueprint->creationFields()[0]['key']);
        $this->assertSame(['retailer', 'supplier', 'both'], $blueprint->creationFields()[2]['options']);
        $this->assertSame(['owner', 'admin', 'staff'], $blueprint->creationFields()[9]['options']);
    }

    #[Test]
    public function it_exports_a_compact_array_for_admin_gov_use(): void
    {
        $blueprint = new SuperAdminAccountCreationBlueprint();

        $this->assertSame([
            'can_create_supplier_accounts' => true,
            'can_manage_supplier_accounts' => true,
            'can_impersonate_accounts' => false,
            'uses_manual_approval_queue' => false,
            'account_creation_flow' => [
                'Create supplier account directly',
                'Assign wholesale capability and account type',
                'Configure the base subscription and reports add-on',
                'Publish the account without an approval queue',
            ],
            'creation_fields' => $blueprint->creationFields(),
        ], $blueprint->toArray());
    }
}
