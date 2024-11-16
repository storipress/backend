<?php

use App\Enums\User\Gender;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->text('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->integer('gender')->default(Gender::other());
            $table->date('birthday')->nullable();
            $table->string('phone_number')->nullable();
            // avatar will use one-to-one polymorphic relation
            $table->string('location')->nullable();
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->string('stripe_id')
                ->collation('utf8mb4_bin')
                ->nullable()
                ->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        $user = new User([
            'email' => 'hello@storipress.com',
            'password' => Hash::make(Str::random(32)),
            'first_name' => 'Storipress',
            'last_name' => 'Helper',
            'location' => 'The Internet',
            'website' => 'https://storipress.com',
        ]);

        User::withoutEvents(fn () => $user->save());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
