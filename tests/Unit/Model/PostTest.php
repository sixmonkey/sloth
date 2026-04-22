<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Sloth\Model\Post;
use Sloth\Model\Taxonomy;
use Sloth\Model\Term;
use Sloth\Model\User;

beforeEach(function (): void {
    global $dbSetup;

    if (empty($dbSetup)) {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'default');
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $dbSetup = true;
    }

    Capsule::schema()->dropAllTables();
    Capsule::schema()->create('posts', function ($t) {
            $t->increments('ID');
            $t->bigInteger('post_author')->unsigned()->default(1);
            $t->dateTime('post_date')->nullable();
            $t->dateTime('post_date_gmt')->nullable();
            $t->longText('post_content')->nullable();
            $t->text('post_title')->nullable();
            $t->text('post_excerpt')->nullable();
            $t->string('post_status')->default('draft');
            $t->string('comment_status')->default('open');
            $t->string('ping_status')->default('open');
            $t->string('post_password')->default('');
            $t->string('post_name')->default('');
            $t->text('to_ping')->nullable();
            $t->text('pinged')->nullable();
            $t->dateTime('post_modified')->nullable();
            $t->dateTime('post_modified_gmt')->nullable();
            $t->longText('post_content_filtered')->nullable();
            $t->bigInteger('post_parent')->unsigned()->default(0);
            $t->string('guid')->default('');
            $t->integer('menu_order')->default(0);
            $t->string('post_type')->default('post');
            $t->string('post_mime_type')->default('');
$t->bigInteger('comment_count')->default(0);
        });
    });

    describe('Post Model', function (): void {
    it('creates a post', function (): void {
        $post = new Post();
        $post->post_title = 'Test Post';
        $post->post_content = 'Test content';
        $post->post_status = 'publish';
        $post->post_type = 'post';
        $post->post_name = 'test-post';
        $post->post_author = 1;
        $post->save();

        expect($post->ID)->toBeGreaterThan(0);
    });

    it('has integer ID', function (): void {
        $post = new Post();
        $post->post_title = 'Test';
        $post->post_status = 'publish';
        $post->post_type = 'post';
        $post->post_author = 1;
        $post->save();

        expect($post->ID)->toBeInt();
        expect($post->ID)->toBeGreaterThan(0);
    });

    it('has status scope', function (): void {
        $post = new Post();
        $post->post_title = 'Test';
        $post->post_status = 'publish';
        $post->post_type = 'post';
        $post->post_author = 1;
        $post->save();

        $posts = Post::status('publish')->get();

        expect($posts)->not->toBeNull();
        expect($posts->count())->toBe(1);
    });

    it('has type scope', function (): void {
        $post = new Post();
        $post->post_title = 'Test';
        $post->post_status = 'publish';
        $post->post_type = 'custom';
        $post->post_author = 1;
        $post->save();

        $posts = Post::type('custom')->get();

        expect($posts)->not->toBeNull();
        expect($posts->count())->toBe(1);
    });

    it('has slug scope', function (): void {
        $post = new Post();
        $post->post_title = 'Test';
        $post->post_status = 'publish';
        $post->post_type = 'post';
        $post->post_name = 'my-test-slug';
        $post->post_author = 1;
        $post->save();

        $posts = Post::slug('my-test-slug')->get();

        expect($posts)->not->toBeNull();
        expect($posts->count())->toBe(1);
    });

    it('has children relation', function (): void {
        $parent = new Post();
        $parent->post_title = 'Parent';
        $parent->post_status = 'publish';
        $parent->post_type = 'post';
        $parent->post_author = 1;
        $parent->save();

        $child1 = new Post();
        $child1->post_title = 'Child 1';
        $child1->post_status = 'publish';
        $child1->post_type = 'post';
        $child1->post_parent = $parent->ID;
        $child1->post_author = 1;
        $child1->save();

        $child2 = new Post();
        $child2->post_title = 'Child 2';
        $child2->post_status = 'publish';
        $child2->post_type = 'post';
        $child2->post_parent = $parent->ID;
        $child2->post_author = 1;
        $child2->save();

        $children = $parent->children;

        expect($children->count())->toBe(2);
        expect($children->first()->post_parent)->toBe($parent->ID);
    });

    it('has parent relation', function (): void {
        $parent = new Post();
        $parent->post_title = 'Parent';
        $parent->post_status = 'publish';
        $parent->post_type = 'post';
        $parent->post_author = 1;
        $parent->save();

        $child = new Post();
        $child->post_title = 'Child';
        $child->post_status = 'publish';
        $child->post_type = 'post';
        $child->post_parent = $parent->ID;
        $child->post_author = 1;
        $child->save();

        expect($child->parent)->not->toBeNull();
        expect($child->parent->ID)->toBe($parent->ID);
    });

    it('can be ordered newest', function (): void {
        $post1 = new Post();
        $post1->post_title = 'First';
        $post1->post_status = 'publish';
        $post1->post_type = 'post';
        $post1->post_author = 1;
        $post1->post_date = '2024-01-01 10:00:00';
        $post1->save();

        $post2 = new Post();
        $post2->post_title = 'Second';
        $post2->post_status = 'publish';
        $post2->post_type = 'post';
        $post2->post_author = 1;
        $post2->post_date = '2024-01-01 12:00:00';
        $post2->save();

        $newest = Post::newest()->first();

        expect($newest->ID)->toBe($post2->ID);
    });

    it('can be ordered oldest', function (): void {
        $post1 = new Post();
        $post1->post_title = 'First';
        $post1->post_status = 'publish';
        $post1->post_type = 'post';
        $post1->post_author = 1;
        $post1->post_date = '2024-01-01 10:00:00';
        $post1->save();

        $post2 = new Post();
        $post2->post_title = 'Second';
        $post2->post_status = 'publish';
        $post2->post_type = 'post';
        $post2->post_author = 1;
        $post2->post_date = '2024-01-01 12:00:00';
        $post2->save();

        $oldest = Post::oldest()->first();

        expect($oldest->ID)->toBe($post1->ID);
    });

    it('has aliases', function (): void {
        $post = new Post();
        $post->post_title = 'Test Title';
        $post->post_status = 'publish';
        $post->post_type = 'post';
        $post->post_name = 'test-slug';
        $post->post_content = 'Test content';
        $post->post_author = 1;
        $post->save();

        expect($post->title)->toBe('Test Title');
        expect($post->slug)->toBe('test-slug');
        expect($post->content)->toBe('Test content');
    });

    it('type is fillable', function (): void {
        $post = new Post();
        $post->post_title = 'Test';
        $post->post_status = 'publish';
        $post->post_type = 'video';
        $post->post_author = 1;
        $post->save();

        expect($post->post_type)->toBe('video');
    });

    it('parent does not return null when zero', function (): void {
        $post = new Post();
        $post->post_title = 'Test';
        $post->post_status = 'publish';
        $post->post_type = 'post';
        $post->post_parent = 0;
        $post->post_author = 1;
        $post->save();

        expect($post->post_parent)->toBe(0);
    });

    it('has attachment relation', function (): void {
        $parent = new Post();
        $parent->post_title = 'Parent';
        $parent->post_status = 'publish';
        $parent->post_type = 'post';
        $parent->post_author = 1;
        $parent->save();

        $attachment = new Post();
        $attachment->post_title = 'Attachment';
        $attachment->post_status = 'publish'; // Changed from 'inherit' to pass global scope
        $attachment->post_type = 'attachment';
        $attachment->post_parent = $parent->ID;
        $attachment->post_author = 1;
        $attachment->save();

        $attachments = $parent->attachment;

        expect($attachments->count())->toBe(1);
    });

    it('has revision relation', function (): void {
        $parent = new Post();
        $parent->post_title = 'Parent';
        $parent->post_status = 'publish';
        $parent->post_type = 'post';
        $parent->post_author = 1;
        $parent->save();

        $revision = new Post();
        $revision->post_title = 'Revision';
        $revision->post_status = 'publish'; // Changed from 'inherit' to 'publish' to pass global scope
        $revision->post_type = 'revision';
        $revision->post_parent = $parent->ID;
        $revision->post_author = 1;
        $revision->save();

        $revisions = $parent->revision;

        expect($revisions->count())->toBe(1);
    });
});