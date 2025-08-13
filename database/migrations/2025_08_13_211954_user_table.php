<?php

use Marwa\DB\Schema\Builder as Schema;

return new class {
    public function up(): void
    {
        Schema::create('user', function ($table) {
            $table->increments('id');
            $table->string('name', 100)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::drop('user');
    }
};
