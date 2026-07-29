<?php

namespace Tests\Feature;

use App\Framework;
use App\FrameworkLevel;
use App\LearningTree;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrameworkItemSyncLearningTreeTest extends TestCase
{
    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create(['role' => 2]);
        $this->user_2 = factory(User::class)->create(['role' => 2]);
        $this->student = factory(User::class)->create(['role' => 3]);
        $this->framework = factory(Framework::class)->create(['user_id' => $this->user->id]);
        $this->framework_level = factory(FrameworkLevel::class)->create(['framework_id' => $this->framework->id]);

        // creates a level ("Some level") with a descriptor ("some descriptor")
        // attached, matching FrameworkDescriptorTest.php's pattern - this is
        // the only confirmed way to get a level+descriptor pair into the DB
        // without assuming the shape of the framework_level_framework_descriptor
        // pivot table directly.
        $this->actingAs($this->user)->postJson('/api/framework-levels/with-descriptors', [
            'title_1' => 'Some level',
            'title_2' => '',
            'title_3' => '',
            'title_4' => '',
            'descriptor' => 'some descriptor',
            'framework_id' => $this->framework->id
        ]);
        $this->synced_framework_level = FrameworkLevel::where('title', 'Some level')->first();
        $this->synced_framework_descriptor = DB::table('framework_descriptors')
            ->where('descriptor', 'some descriptor')
            ->first();

        $this->learningTree = factory(LearningTree::class)->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function non_instructor_cannot_get_framework_items_by_learning_tree()
    {
        $this->actingAs($this->student)
            ->getJson("/api/framework-item-sync-learning-tree/learning-tree/{$this->learningTree->id}")
            ->assertJson(['message' => 'You are not allowed to get the framework alignments for the learning tree.']);
    }

    /** @test */
    public function instructor_can_get_framework_items_by_learning_tree()
    {
        $this->actingAs($this->user)
            ->getJson("/api/framework-item-sync-learning-tree/learning-tree/{$this->learningTree->id}")
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function saving_framework_alignment_creates_framework_item_learning_tree_rows_for_levels_and_descriptors()
    {
        $data = [
            'title' => $this->learningTree->title,
            'description' => $this->learningTree->description,
            'public' => 1,
            'framework_item_sync_learning_tree' => [
                'levels' => [['id' => $this->synced_framework_level->id, 'text' => $this->synced_framework_level->title]],
                'descriptors' => [['id' => $this->synced_framework_descriptor->id, 'text' => $this->synced_framework_descriptor->descriptor]]
            ]
        ];

        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('framework_item_learning_tree', [
            'learning_tree_id' => $this->learningTree->id,
            'framework_item_id' => $this->synced_framework_level->id,
            'framework_item_type' => 'level'
        ]);
        $this->assertDatabaseHas('framework_item_learning_tree', [
            'learning_tree_id' => $this->learningTree->id,
            'framework_item_id' => $this->synced_framework_descriptor->id,
            'framework_item_type' => 'descriptor'
        ]);
    }

    /** @test */
    public function resaving_framework_alignment_replaces_the_old_set_instead_of_accumulating_duplicates()
    {
        $this->learningTree->addFrameworkItems([
            'levels' => [['id' => $this->synced_framework_level->id, 'text' => $this->synced_framework_level->title]],
            'descriptors' => []
        ]);
        $this->assertDatabaseCount('framework_item_learning_tree', 1);

        $data = [
            'title' => $this->learningTree->title,
            'description' => $this->learningTree->description,
            'public' => 1,
            'framework_item_sync_learning_tree' => [
                'levels' => [],
                'descriptors' => [['id' => $this->synced_framework_descriptor->id, 'text' => $this->synced_framework_descriptor->descriptor]]
            ]
        ];

        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $data)
            ->assertJson(['type' => 'success']);

        // old level sync should be gone, only the new descriptor sync remains
        $this->assertDatabaseCount('framework_item_learning_tree', 1);
        $this->assertDatabaseHas('framework_item_learning_tree', [
            'learning_tree_id' => $this->learningTree->id,
            'framework_item_id' => $this->synced_framework_descriptor->id,
            'framework_item_type' => 'descriptor'
        ]);
        $this->assertDatabaseMissing('framework_item_learning_tree', [
            'learning_tree_id' => $this->learningTree->id,
            'framework_item_id' => $this->synced_framework_level->id,
            'framework_item_type' => 'level'
        ]);
    }

    /** @test */
    public function getting_learning_trees_by_descriptor_returns_synced_trees()
    {
        $this->learningTree->addFrameworkItems([
            'levels' => [],
            'descriptors' => [['id' => $this->synced_framework_descriptor->id, 'text' => $this->synced_framework_descriptor->descriptor]]
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/framework-item-sync-learning-tree/get-learning-trees-by-descriptor/{$this->synced_framework_descriptor->id}")
            ->assertJson(['type' => 'success'])
            ->assertJsonFragment(['id' => $this->learningTree->id]);
    }
}
