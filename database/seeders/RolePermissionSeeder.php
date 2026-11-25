<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    private const CENTRAL_TEAM_ID = 1;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId(self::CENTRAL_TEAM_ID);

        foreach ($this->permissionCatalogue() as $slug) {
            Permission::findOrCreate($slug, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $assignments = $this->roleAssignments();

        foreach ($assignments as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, 'web');

            if (! $role->team_id) {
                $role->team_id = self::CENTRAL_TEAM_ID;
                $role->save();
            }

            $role->syncPermissions($permissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionCatalogue(): array
    {
        return [
            'tenants.view', 'tenants.create', 'tenants.update', 'tenants.approve', 'tenants.suspend', 'tenants.resume', 'tenants.delete', 'tenants.impersonate', 'tenants.domains_manage', 'tenants.settings_manage',
            'resellers.view', 'resellers.create', 'resellers.update', 'resellers.approve', 'resellers.suspend', 'resellers.resume', 'resellers.hierarchy_view', 'resellers.hierarchy_manage',
            'users.view', 'users.invite', 'users.update', 'users.deactivate', 'users.roles_assign', 'users.impersonate', 'users.export',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.publish', 'products.authorize', 'products.pricing_manage', 'products.variants_manage', 'products.reviews_moderate',
            'demos.view', 'demos.create', 'demos.update', 'demos.delete',
            'cms.view', 'cms.pages_manage', 'cms.menus_manage', 'cms.themes_manage', 'cms.media_manage',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.export',
            'crm.view', 'crm.leads_manage', 'crm.quotations_view', 'crm.quotations_manage', 'crm.quotations_approve',
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.refund', 'orders.export',
            'billing.view', 'billing.invoices_manage', 'billing.coupons_manage', 'billing.taxes_manage', 'billing.export',
            'payments.view', 'payments.reconcile', 'payments.refund_execute', 'payments.gateway_manage',
            'wallets.view', 'wallets.view_all', 'wallets.adjust', 'wallets.export',
            'payouts.view', 'payouts.request', 'payouts.approve', 'payouts.reject', 'payouts.mark_paid', 'payouts.bank_details_manage',
            'commissions.view', 'commissions.view_all', 'commissions.approve', 'commissions.adjust', 'commissions.rules_manage', 'commissions.export',
            'affiliates.view', 'affiliates.create', 'affiliates.update', 'affiliates.approve', 'affiliates.suspend', 'affiliates.links_manage',
            'subscriptions.view', 'subscriptions.plans_manage', 'subscriptions.manage', 'subscriptions.export',
            'fulfillment.view', 'fulfillment.jobs_manage', 'fulfillment.events_view',
            'licenses.view', 'licenses.issue', 'licenses.revoke', 'licenses.activate', 'licenses.deactivate',
            'support.tickets_view', 'support.tickets_reply', 'support.tickets_assign', 'support.tickets_escalate', 'support.tickets_close', 'support.notes_manage', 'support.departments_manage', 'support.sla_manage', 'support.kb_manage', 'support.reports_view',
            'ai.view', 'ai.agents_manage', 'ai.conversations_view', 'ai.knowledge_manage', 'ai.usage_view', 'ai.settings_manage',
            'notifications.view', 'notifications.templates_manage', 'notifications.logs_view',
            'marketing.view', 'marketing.campaigns_manage', 'marketing.assets_manage',
            'reports.view', 'reports.financial_view', 'reports.export',
            'api.keys_manage', 'api.webhooks_manage', 'api.logs_view',
            'settings.platform_manage', 'settings.security_manage', 'settings.tenant_manage', 'settings.integrations_manage',
            'audit.view', 'audit.export',
        ];
    }

    private function roleAssignments(): array
    {
        $all = $this->permissionCatalogue();

        $platformAdmin = [
            'tenants.view', 'tenants.create', 'tenants.update', 'tenants.approve',
            'resellers.view', 'resellers.create', 'resellers.update', 'resellers.approve', 'resellers.suspend', 'resellers.resume', 'resellers.hierarchy_view', 'resellers.hierarchy_manage',
            'users.view', 'users.invite', 'users.update', 'users.export',
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.export',
            'billing.view', 'billing.export', 'payments.view', 'wallets.view', 'commissions.view_all',
            'customers.view', 'demos.view', 'demos.create', 'demos.update', 'demos.delete',
            'support.tickets_view', 'support.tickets_reply', 'support.tickets_assign', 'support.tickets_escalate', 'support.tickets_close', 'support.notes_manage', 'support.departments_manage', 'support.sla_manage', 'support.kb_manage', 'support.reports_view',
            'affiliates.view', 'affiliates.create', 'affiliates.update', 'affiliates.approve', 'affiliates.suspend',
            'subscriptions.view', 'subscriptions.plans_manage', 'subscriptions.manage', 'subscriptions.export',
            'fulfillment.view', 'fulfillment.jobs_manage', 'fulfillment.events_view', 'licenses.issue', 'licenses.revoke', 'licenses.view',
            'ai.view', 'ai.agents_manage', 'ai.conversations_view', 'ai.knowledge_manage', 'ai.usage_view', 'ai.settings_manage',
            'notifications.view', 'notifications.templates_manage', 'notifications.logs_view',
            'marketing.view', 'marketing.campaigns_manage', 'marketing.assets_manage',
            'reports.view', 'reports.export', 'audit.view', 'audit.export',
            'settings.platform_manage', 'settings.integrations_manage',
        ];

        $platformAdmin = array_merge($platformAdmin, [
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.publish', 'products.authorize', 'products.pricing_manage', 'products.variants_manage', 'products.reviews_moderate',
            'cms.view', 'cms.pages_manage', 'cms.menus_manage', 'cms.themes_manage', 'cms.media_manage',
        ]);

        $financeAdmin = [
            'tenants.view', 'resellers.view', 'users.view', 'customers.view',
            'orders.view', 'orders.cancel', 'orders.refund', 'orders.export',
            'billing.view', 'billing.invoices_manage', 'billing.coupons_manage', 'billing.taxes_manage', 'billing.export',
            'payments.view', 'payments.reconcile', 'payments.refund_execute',
            'wallets.view', 'wallets.view_all', 'wallets.adjust', 'wallets.export',
            'payouts.view', 'payouts.approve', 'payouts.reject', 'payouts.mark_paid', 'payouts.request', 'payouts.bank_details_manage',
            'commissions.view', 'commissions.view_all', 'commissions.approve', 'commissions.adjust', 'commissions.rules_manage', 'commissions.export',
            'affiliates.view', 'subscriptions.view', 'subscriptions.export', 'reports.view', 'reports.financial_view', 'reports.export', 'audit.view',
        ];

        $productManager = [
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.publish', 'products.authorize', 'products.pricing_manage', 'products.variants_manage', 'products.reviews_moderate',
            'demos.view', 'demos.create', 'demos.update', 'demos.delete', 'resellers.view', 'resellers.hierarchy_view',
            'subscriptions.view', 'fulfillment.view', 'reports.view', 'billing.view', 'commissions.view',
        ];

        $supportManager = [
            'support.tickets_view', 'support.tickets_reply', 'support.tickets_assign', 'support.tickets_escalate', 'support.tickets_close', 'support.notes_manage', 'support.departments_manage', 'support.sla_manage', 'support.kb_manage', 'support.reports_view',
            'customers.view', 'orders.view', 'billing.view', 'subscriptions.view', 'fulfillment.view', 'licenses.view', 'payments.view', 'resellers.view', 'tenants.view', 'users.view',
            'ai.conversations_view', 'ai.knowledge_manage', 'notifications.logs_view', 'notifications.view', 'reports.view', 'audit.view',
        ];

        $supportAgent = [
            'support.tickets_view', 'support.tickets_reply', 'support.tickets_assign', 'support.tickets_close', 'support.notes_manage', 'support.kb_manage', 'support.reports_view',
            'customers.view', 'orders.view', 'billing.view', 'subscriptions.view', 'fulfillment.view', 'licenses.view', 'payments.view', 'products.view', 'resellers.view', 'tenants.view',
            'ai.view', 'ai.conversations_view', 'notifications.view',
        ];

        $reseller = [
            'resellers.view', 'resellers.create', 'resellers.update', 'resellers.approve', 'resellers.suspend', 'resellers.resume', 'resellers.hierarchy_view', 'resellers.hierarchy_manage',
            'users.view', 'users.invite', 'users.update', 'users.deactivate', 'users.roles_assign', 'users.export',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.publish', 'products.authorize', 'products.pricing_manage', 'products.variants_manage', 'products.reviews_moderate',
            'demos.view', 'demos.create', 'demos.update', 'demos.delete', 'cms.view', 'cms.pages_manage', 'cms.menus_manage', 'cms.themes_manage', 'cms.media_manage',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.export',
            'crm.view', 'crm.leads_manage', 'crm.quotations_view', 'crm.quotations_manage', 'crm.quotations_approve',
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.export',
            'billing.view', 'billing.invoices_manage', 'billing.coupons_manage', 'billing.export',
            'payments.view', 'wallets.view', 'wallets.export', 'payouts.view', 'payouts.request', 'payouts.bank_details_manage',
            'commissions.view', 'commissions.export', 'affiliates.view', 'affiliates.create', 'affiliates.update', 'affiliates.approve', 'affiliates.suspend', 'affiliates.links_manage',
            'subscriptions.view', 'subscriptions.manage', 'subscriptions.export', 'fulfillment.view', 'fulfillment.jobs_manage', 'fulfillment.events_view',
            'licenses.view', 'licenses.issue', 'licenses.revoke', 'licenses.activate', 'licenses.deactivate',
            'support.tickets_view', 'support.tickets_reply', 'support.tickets_assign', 'support.tickets_escalate', 'support.tickets_close', 'support.notes_manage', 'support.kb_manage', 'support.reports_view',
            'ai.view', 'ai.agents_manage', 'ai.conversations_view', 'ai.knowledge_manage', 'ai.usage_view', 'ai.settings_manage',
            'notifications.view', 'notifications.templates_manage', 'notifications.logs_view',
            'marketing.view', 'marketing.campaigns_manage', 'marketing.assets_manage',
            'reports.view', 'reports.financial_view', 'reports.export', 'api.keys_manage', 'api.webhooks_manage', 'api.logs_view',
            'settings.tenant_manage', 'audit.view',
        ];

        $employee = [
            'products.view', 'orders.view', 'orders.update', 'customers.view', 'customers.update',
            'crm.view', 'crm.leads_manage', 'support.tickets_view', 'support.tickets_reply', 'support.notes_manage', 'support.tickets_close',
            'subscriptions.view', 'fulfillment.view', 'fulfillment.jobs_manage', 'licenses.view', 'billing.view', 'reports.view', 'notifications.view', 'cms.view',
        ];

        $salesAgent = [
            'crm.view', 'crm.leads_manage', 'crm.quotations_view', 'crm.quotations_manage',
            'customers.view', 'customers.create', 'customers.update', 'products.view', 'orders.view', 'subscriptions.view',
            'billing.view', 'support.tickets_view', 'reports.view', 'notifications.view', 'affiliates.links_manage',
        ];

        $affiliate = [
            'affiliates.view', 'affiliates.links_manage', 'commissions.view', 'payouts.view', 'payouts.request', 'payouts.bank_details_manage',
            'orders.view', 'reports.view', 'notifications.view', 'marketing.view',
        ];

        $customer = [
            'products.view', 'orders.view', 'payments.view', 'billing.view', 'subscriptions.view', 'subscriptions.manage',
            'licenses.view', 'licenses.activate', 'licenses.deactivate', 'fulfillment.view',
            'support.tickets_view', 'support.tickets_reply', 'customers.update', 'notifications.view', 'marketing.view',
        ];

        return [
            'platform.super_admin' => $all,
            'platform.admin' => array_values(array_unique($platformAdmin)),
            'platform.finance_admin' => $financeAdmin,
            'platform.product_manager' => $productManager,
            'platform.support_manager' => $supportManager,
            'platform.support_agent' => $supportAgent,
            'reseller.owner' => $reseller,
            'reseller.sub_reseller' => $reseller,
            'reseller.employee' => $employee,
            'reseller.sales_agent' => $salesAgent,
            'affiliate.partner' => $affiliate,
            'customer.buyer' => $customer,
        ];
    }
}
