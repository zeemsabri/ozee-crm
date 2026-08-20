<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The permission to type an email address by hand instead of using the project's clients.
 *
 * Client mail is meant to travel one route: from our authorised mailbox, signed with our
 * details, to the clients attached to the project. That is the point of the integration —
 * a client can reply to us but cannot reach an individual staff member directly, and very
 * few people have access to the Gmail account itself. A free-text recipient box in the
 * composer is a hole straight through that, so the box is now gated on this permission and
 * the server refuses an arbitrary address without it.
 *
 * Granted to super-admin only, deliberately. Managers can still reply to clients — that
 * needs no permission — but forwarding a client thread to an address of their choosing is
 * a different act, and it should start with nobody rather than be quietly widened later.
 * Add roles in the admin UI if that turns out to be too tight.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'email_custom_recipients')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'Email Custom Recipients',
                'slug' => 'email_custom_recipients',
                'description' => 'Type an email address by hand instead of sending to the project clients',
                'category' => 'Email Management',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')->whereIn('slug', ['super-admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_permission')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'email_custom_recipients')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_permission')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
