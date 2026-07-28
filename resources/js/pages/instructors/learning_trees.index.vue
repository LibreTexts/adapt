<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-learning-tree-index'"/>
    <div v-if="canViewLearningTrees">
      <LearningTreeProperties :learning-tree-form="learningTreeForm"
                              :learning-tree-id="learningTreeId"
                              :framework-item-sync-learning-tree="frameworkItemSyncLearningTree"
                              @saveLearningTreeProperties="saveLearningTreeProperties"
                              @setFrameworkItemSyncLearningTree="setFrameworkItemSyncLearningTree"
      />
      <PageTitle title="My Learning Trees"/>
      <div class="float-right mb-2">
        <b-button
          class="mr-1"
          size="sm"
          variant="primary"
          @click="createLearningTree()"
        >
          New Learning Tree
        </b-button>
        <b-button
          class="mr-1"
          size="sm"
          variant="outline-primary"
          @click="cloneLearningTrees()"
        >
          Clone Learning Trees
        </b-button>
        <ConsultInsight
          :url="'https://commons.libretexts.org/insight/introduction-to-learning-trees'"
          class="float-right"
        />
      </div>
      <b-modal
        id="modal-clone-learning-tree"
        ref="clone-learning-tree-modal"
        title="Clone Learning Trees"

      >
        <RequiredText/>
        <b-form-group
          label-cols-sm="5"
          label-cols-lg="4"
          label-for="learning_tree_clones"
        >
          <template v-slot:label>
            Learning Tree ID(s)*
          </template>
          <b-form-row>
            <b-col lg="5">
              <b-form-input
                id="learning_tree_clones"
                v-model="learningTreeCloneForm.learning_tree_ids"
                type="text"
                placeholder="1,2,3..."
                :class="{ 'is-invalid': learningTreeCloneForm.errors.has('learning_tree_ids') }"
                @keydown="learningTreeCloneForm.errors.clear('learning_tree_ids')"
              />
              <has-error :form="learningTreeCloneForm" field="learning_tree_ids"/>
            </b-col>
          </b-form-row>
        </b-form-group>


        <template #modal-footer>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="handleCloneLearningTrees"
          >
            Clone
          </b-button>
        </template>
      </b-modal>

      <b-modal
        id="modal-delete-learning-tree"
        ref="modal"
        title="Confirm Delete Learning Tree"
      >
        <p>Please note that once a Learning Tree is deleted, it can not be retrieved.</p>
        <template #modal-footer>
          <b-button
            size="sm"
            class="float-right"
            @click="$bvModal.hide('modal-delete-learning-tree')"
          >
            Cancel
          </b-button>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="handleDeleteLearningTree"
          >
            Yes, delete learning tree!
          </b-button>
        </template>
      </b-modal>
      <div v-if="hasLearningTrees">
        <b-table striped hover
                 :fields="fields"
                 :items="learningTrees"
                 aria-label="Learning trees"
        >
          <template v-slot:cell(title)="data">
            <router-link :to="{name: 'instructors.learning_trees.editor', params: {learningTreeId: data.item.id}}">
              {{ data.item.title }}
            </router-link>
          </template>
          <template v-slot:cell(created_at)="data">
            {{ $moment(data.item.created_at, 'YYYY-MM-DD').format('MMMM D, YYYY') }}
          </template>
          <template v-slot:cell(actions)="data">
            <div class="mb-0">
              <b-tooltip :target="getTooltipTarget('learningTreeProperties',data.item.id)"
                         triggers="hover"
                         delay="500"
              >
                Tree Properties
              </b-tooltip>
              <b-tooltip :target="getTooltipTarget('createLearningTreeFromTemplate',data.item.id)"
                         triggers="hover"
                         delay="500"
              >
                Clone "{{ data.item.title }}"
              </b-tooltip>
              <a :id="getTooltipTarget('learningTreeProperties',data.item.id)"
                 href="#"
                 class="pr-1"
                 @click="editLearningTreeProperties(data.item)"
              >
                <b-icon class="text-muted"
                        icon="gear"
                        :aria-label="`Tree properties for ${data.item.title}`"
                />
              </a>

              <a :id="getTooltipTarget('createLearningTreeFromTemplate',data.item.id)"
                 href="#"
                 class="pr-1"
                 @click="createLearningTreeFromTemplate(data.item.id)"
              >
                <font-awesome-icon
                  class="text-muted"
                  :icon="copyIcon"
                />
              </a>
              <b-tooltip :target="getTooltipTarget('deleteLearningTree',data.item.id)"
                         delay="500"
              >
                Delete Learning Tree
              </b-tooltip>
              <a :id="getTooltipTarget('deleteLearningTree',data.item.id)"
                 href="#"
                 @click="deleteLearningTree(data.item.id)"
              >
                <b-icon class="text-muted" icon="trash"/>
              </a>
            </div>
          </template>
        </b-table>
      </div>
      <div v-else>
        <br>
        <div class="mt-4">
          <b-alert :show="showNoLearningTreesAlert" variant="warning">
            <a href="#" class="alert-link">You currently have no
              Learning Trees.
            </a>
          </b-alert>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { mapGetters } from 'vuex'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { getTooltipTarget, initTooltips } from '~/helpers/Tooptips'
import Form from 'vform'
import LearningTreeProperties from '../../components/LearningTreeProperties.vue'
import AllFormErrors from '../../components/AllFormErrors.vue'
import ConsultInsight from '../../components/ConsultInsight.vue'

export default {
  middleware: 'auth',
  components: {
    ConsultInsight,
    AllFormErrors,
    LearningTreeProperties,
    FontAwesomeIcon
  },
  data: () => ({
    allFormErrors: [],
    learningTreeId: 0,
    learningTreeForm: new Form(),
    frameworkItemSyncLearningTree: { descriptors: [], levels: [] },
    copyIcon: faCopy,
    learningTreeCloneForm: new Form({
      learning_tree_ids: ''
    }),
    fields: [
      {
        key: 'id',
        label: 'ID'
      },
      {
        key: 'title',
        sortable: true,
        isRowHeader: true
      },
      {
        key: 'description',
        sortable: true
      },
      {
        key: 'public',
        formatter: value => {
          return value === 1 ? 'Yes' : 'No'
        }
      },
      {
        key: 'created_at',
        label: 'Date Created',
        sortable: true
      },
      {
        key: 'actions',
        thStyle: { width: '95px' }
      }
    ],
    learningTrees: [],
    canViewLearningTrees: false,
    hasLearningTrees: false,
    showNoLearningTreesAlert: false
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  mounted () {
    this.getLearningTrees()
    this.getTooltipTarget = getTooltipTarget
    initTooltips(this)
  },
  methods: {
    async saveLearningTreeProperties () {
      try {
        this.learningTreeForm.framework_item_sync_learning_tree = this.frameworkItemSyncLearningTree
        const { data } = await this.learningTreeForm.post(`/api/learning-trees/info/${this.learningTreeId}`)
        this.$noty[data.type](data.message)
        await this.getLearningTrees()
        this.$bvModal.hide('modal-learning-tree-properties')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.learningTreeForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-learning-tree-index')
        }
      }
    },
    async editLearningTreeProperties (learningTree) {
      this.learningTreeId = learningTree.id
      this.learningTreeForm = new Form({
        title: learningTree.title,
        description: learningTree.description,
        public: learningTree.public,
        notes: learningTree.notes,
        tags: learningTree.tags || [],
        question_subject_id: learningTree.question_subject_id,
        question_chapter_id: learningTree.question_chapter_id,
        question_section_id: learningTree.question_section_id
      })
      await this.getFrameworkItemSyncLearningTree()
      this.$bvModal.show('modal-learning-tree-properties')
    },
    async getFrameworkItemSyncLearningTree () {
      if (!this.learningTreeId) {
        this.frameworkItemSyncLearningTree = { descriptors: [], levels: [] }
        return
      }
      try {
        const { data } = await axios.get(`/api/framework-item-sync-learning-tree/learning-tree/${this.learningTreeId}`)
        this.frameworkItemSyncLearningTree = data.framework_item_sync_learning_tree
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    setFrameworkItemSyncLearningTree (frameworkItemSyncLearningTree) {
      this.frameworkItemSyncLearningTree = frameworkItemSyncLearningTree
    },
    async handleCloneLearningTrees () {
      try {
        const { data } = await this.learningTreeCloneForm.post(`/api/learning-trees/clone`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          await this.getLearningTrees()
          this.$bvModal.hide('modal-clone-learning-tree')
          this.learningTreeCloneForm.learning_tree_ids = ''
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    cloneLearningTrees () {
      this.$bvModal.show('modal-clone-learning-tree')
    },
    async createLearningTreeFromTemplate (learningTreeId) {
      try {
        const { data } = await axios.post(`/api/learning-trees/${learningTreeId}/create-learning-tree-from-template`)
        this.$noty[data.type](data.message)
        await this.getLearningTrees()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async handleDeleteLearningTree () {
      try {
        const { data } = await axios.delete(`/api/learning-trees/${this.learningTreeId}`)
        this.$noty[data.type](data.message)
        this.$bvModal.hide('modal-delete-learning-tree')
        await this.getLearningTrees()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    deleteLearningTree (learningTreeId) {
      this.learningTreeId = learningTreeId
      this.$bvModal.show('modal-delete-learning-tree')
    },
    createLearningTree () {
      this.$router.push(`/instructors/learning-trees/editor/0`)
    },
    async getLearningTrees () {
      try {
        const { data } = await axios.get('/api/learning-trees')
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.canViewLearningTrees = true
          this.hasLearningTrees = data.learning_trees.length > 0
          this.showNoLearningTreesAlert = !this.hasLearningTrees
          this.learningTrees = data.learning_trees
          console.log(data.learning_trees)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  },
  metaInfo () {
    return { title: 'My Learning Trees' }
  }
}
</script>
<style>
body, html {
  overflow: visible;

}

svg:focus, svg:active:focus {
  outline: none !important;
}
</style>
