<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'team_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id');
                $table->index('team_id', 'roles_team_id_index');
            });

            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name']);
                $table->unique(['team_id', 'name', 'guard_name']);
            });
        }

        if (! Schema::hasColumn('model_has_roles', 'team_id')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->after('role_id');
                $table->index('team_id', 'model_has_roles_team_id_index');
            });

            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->dropPrimary();
                $table->primary(['team_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_team_role_model_type_primary');
            });
        }

        if (! Schema::hasColumn('model_has_permissions', 'team_id')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->after('permission_id');
                $table->index('team_id', 'model_has_permissions_team_id_index');
            });

            Schema::table('model_has_permissions', function (Blueprint $table) {
                $table->dropPrimary();
                $table->primary(
                    ['team_id', 'permission_id', 'model_id', 'model_type'],
                    'model_has_permissions_team_permission_model_type_primary'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('model_has_permissions', 'team_id')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                $table->dropPrimary('model_has_permissions_team_permission_model_type_primary');
                $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
                $table->dropIndex('model_has_permissions_team_id_index');
                $table->dropColumn('team_id');
            });
        }

        if (Schema::hasColumn('model_has_roles', 'team_id')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->dropPrimary('model_has_roles_team_role_model_type_primary');
                $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
                $table->dropIndex('model_has_roles_team_id_index');
                $table->dropColumn('team_id');
            });
        }

        if (Schema::hasColumn('roles', 'team_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique(['team_id', 'name', 'guard_name']);
                $table->dropIndex('roles_team_id_index');
                $table->dropColumn('team_id');
                $table->unique(['name', 'guard_name']);
            });
        }
    }
};
