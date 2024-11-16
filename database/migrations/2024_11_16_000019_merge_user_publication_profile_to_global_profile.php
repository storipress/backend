<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MergeUserPublicationProfileToGlobalProfile extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $changes = [];

        Tenant::chunk(30, function ($tenants) use (&$changes) {
            $tenants->each->run(function () use (&$changes) {
                DB::table('users')
                    ->get([
                        'id', 'first_name', 'last_name', 'location', 'bio',
                        'website', 'facebook', 'twitter', 'instagram',
                    ])
                    ->each(function (stdClass $user) use (&$changes) {
                        $changes[$user->id] = array_merge(
                            $changes[$user->id] ?? [],
                            array_filter((array) $user),
                        );
                    });
            });
        });

        /** @var array<int, int> $reserved */
        $reserved = DB::table('users')
            ->where('email', 'hello@storipress.com')
            ->pluck('id')
            ->toArray();

        $changes = array_diff_key($changes, array_flip($reserved));

        foreach ($changes as $change) {
            DB::table('users')
                ->where('id', $change['id'])
                ->update(Arr::except($change, ['id']));
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
