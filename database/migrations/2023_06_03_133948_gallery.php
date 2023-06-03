<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Gallery extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasTable('categories')){
            Schema::create('categories', function (Blueprint $table) {

                $table->increments('id');

                $table->string('name', 255);
                $table->string('include_tags', 63);
                $table->string('extend_tags', 63);
                $table->string('exclude_tags', 63);
                $table->string('required_status', 63);
                $table->string('exception_status', 63)->default('0,9');

                $table->integer('status')->default(1);
                $table->integer('count')->default(0);
                $table->integer('enabled')->default(1);
                $table->integer('updating')->default(0);
                $table->integer('downloading')->default(0);
                $table->integer('type')->default(0);
                $table->integer('rank')->default(0);

                $table->dateTime('deleted_at');
                $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('uploaded_at');

            });
        }

        if(!Schema::hasTable('posts')){
            Schema::create('posts', function (Blueprint $table) {

                $table->increments('id');

                $table->string('category_id', 127);
                $table->string('hash', 255);
                $table->string('file_name', 255);
                $table->string('original_uri', 511);
                $table->string('debug_mark', 255);

                $table->float('ratio');

                $table->integer('post_id');
                $table->integer('status')->default(1);
                $table->integer('width')->default(0);
                $table->integer('height')->default(0);
                $table->integer('shown')->default(0);
                $table->integer('size')->default(0);
                $table->integer('debug')->default(0);

                $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('uploaded_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('estimate_at')->nullable();

            });
        }

        if(!Schema::hasTable('tags')){
            Schema::create('tags', function (Blueprint $table) {

                $table->increments('id');

                $table->string('type', 20);
                $table->string('tag', 255);
                $table->string('aliases', 127);

                $table->integer('count')->default(1);
                $table->integer('enabled')->default(1);

            });
        }

        if(!Schema::hasTable('posts_tags')){
            Schema::create('posts_tags', function (Blueprint $table) {

                $table->integer('posts_id')->unsigned();
                $table->integer('tags_id')->unsigned();

                $table->primary(['posts_id', 'tags_id']);

                $table->index(['posts_id', 'tags_id']);

                $table->foreign('posts_id')->references('id')->on('posts');
                $table->foreign('tags_id')->references('id')->on('tags');

            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts_tags');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
}
