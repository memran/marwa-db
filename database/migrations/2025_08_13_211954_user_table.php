<?php

use Marwa\DB\Schema\Schema;
use Marwa\DB\CLI\AbstractMigration;

return new class extends AbstractMigration {
    public function up(): void
    {
        Schema::create('users', function ($t) {
            $t->bigIncrements('id');
            $t->uuid('uuid')->unique(name: 'uniq_users_uuid'); // or: $t->unique('uuid')

            $t->string('name', 100)->nullable();
            $t->string('email', 190)->unique();
            $t->text('bio')->nullable();
            $t->integer('age', unsigned: true)->default(0);
            $t->boolean('active')->default(1);

            $t->decimal('balance', 12, 2)->default(0);
            $t->float('rating', 8, 2)->default(0);
            $t->double('score', 15, 8)->default(0);

            $t->date('dob')->nullable();
            $t->dateTime('last_login')->nullable();
            $t->timestamp('email_verified_at')->nullable();

            $t->json('settings')->nullable();
            $t->binary('avatar')->nullable();

            $t->enum('status', ['pending', 'active', 'banned'])->default('pending');

            $t->timestamps();
            $t->softDeletes();

            // Foreign sample
            // $t->foreignId('team_id');
            // $t->foreign('team_id', 'teams', 'id', options: ['onDelete' => 'cascade']);
        });

        // ALTER (add columns & indexes)
        // Schema::table('users', function ($t) {
        //     $t->string('phone', 30)->nullable();
        //     $t->index(['email', 'active']);
        // });
    }
    public function down(): void
    {
        Schema::drop('user');
    }
};
