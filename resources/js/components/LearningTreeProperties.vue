<template>
  <div>
    <b-modal
      :id="modalId"
      ref="modal"
      size="lg"
      no-close-on-backdrop
      :hide-footer="!canEditLearningTree"
      @hidden="$emit('resetLearningTreePropertiesModal')"
    >
      <template #modal-header>
        <div>
          <h2 class="h5 modal-title">
            Tree Properties
          </h2>
          <div v-if="learningTreeId">
            <small>Learning Tree ID: <span :id="`learning-tree-id-${learningTreeId}`">{{ learningTreeId }}</span>
            </small>
            <a href=""
               aria-label="Copy Learning Tree ID"
               @click.prevent="doCopy(`learning-tree-id-${learningTreeId}`)"
            >
              <font-awesome-icon :icon="copyIcon" class="text-muted pl-1"/>
            </a>
          </div>
        </div>
        <button type="button" aria-label="Close"
                class="close"
                @click="$bvModal.hide(modalId)"
        >
          ×
        </button>
      </template>
      <RequiredText v-if="canEditLearningTree"/>
      <b-form ref="form">
        <b-form-group
          label-cols-sm="4"
          label-cols-lg="3"
          label-for="learning_tree_title"
        >
          <template v-slot:label>
            Title<span v-show="canEditLearningTree">*</span>
          </template>
          <b-form-input
            v-show="canEditLearningTree"
            id="learning_tree_title"
            v-model="learningTreeForm.title"
            type="text"
            :class="{ 'is-invalid': learningTreeForm.errors.has('title') }"
            @keydown="learningTreeForm.errors.clear('title')"
          />
          <has-error :form="learningTreeForm" field="title"/>
          <div v-show="!canEditLearningTree">
            {{ learningTreeForm.title }}
          </div>
        </b-form-group>

        <b-form-group
          label-cols-sm="4"
          label-cols-lg="3"
          label-for="description"
        >
          <template v-slot:label>
            Public Description<span v-show="canEditLearningTree">*</span>
          </template>
          <b-form-textarea
            v-if="canEditLearningTree"
            id="description"
            v-model="learningTreeForm.description"
            type="text"
            :class="{ 'is-invalid': learningTreeForm.errors.has('description') }"
            @keydown="learningTreeForm.errors.clear('description')"
          />
          <has-error :form="learningTreeForm" field="description"/>
          <div v-if="!canEditLearningTree">
            {{ learningTreeForm.description }}
          </div>
        </b-form-group>
        <b-form-group
          label-cols-sm="4"
          label-cols-lg="3"
          label-for="public"
        >
          <template v-slot:label>
            Public<span v-show="canEditLearningTree">*</span>
            <QuestionCircleTooltip id="public-learning-tree-tooltip"/>
            <b-tooltip target="public-learning-tree-tooltip"
                       delay="250"
                       triggers="hover focus"
            >
              Learning trees that are public can be used by any instructor. Learning trees that are not public are only
              accessible in searches
              by {{ canEditLearningTree ? 'you' : 'the tree\'s author' }}.
            </b-tooltip>
          </template>
          <b-form-row class="mt-2">
            <b-form-radio-group
              v-if="canEditLearningTree"
              id="public"
              v-model="learningTreeForm.public"
            >
              <b-form-radio name="public" value="1">
                Yes
              </b-form-radio>
              <b-form-radio name="public" value="0">
                No
              </b-form-radio>
            </b-form-radio-group>
            <div v-if="!canEditLearningTree">
              {{ Boolean(learningTreeForm.public) ? 'Yes' : 'No' }}
            </div>
          </b-form-row>
        </b-form-group>
        <b-form-group
          v-if="canEditLearningTree"
          label-cols-sm="4"
          label-cols-lg="3"
          label-for="description"
        >
          <template v-slot:label>
            Private Notes
          </template>
          <b-form-textarea
            id="description"
            v-model="learningTreeForm.notes"
            type="text"
            :class="{ 'is-invalid': learningTreeForm.errors.has('notes') }"
            @keydown="learningTreeForm.errors.clear('notes')"
          />
          <has-error :form="learningTreeForm" field="notes"/>
        </b-form-group>

        <!-- TAGS: matches CreateQuestion.vue's tags block exactly -->
        <b-form-group
          label-cols-sm="4"
          label-cols-lg="3"
          label-for="learning_tree_tags"
          label="Tags"
        >
          <b-form-row v-if="canEditLearningTree" class="mt-2">
            <b-form-input
              id="learning_tree_tags"
              v-model="tag"
              style="width:200px"
              type="text"
              class="mr-2"
              size="sm"
              @keydown.enter.prevent="addTag()"
            />
            <b-button variant="outline-primary" size="sm" @click="addTag()">
              Add Tag
            </b-button>
          </b-form-row>
          <div class="d-flex flex-row flex-wrap">
            <span v-for="chosenTag in learningTreeForm.tags" :key="chosenTag" class="mt-2">
              <b-button v-if="canEditLearningTree"
                        size="sm"
                        variant="secondary"
                        class="mr-2"
                        style="line-height:.8"
                        @click="removeTag(chosenTag)"
              ><span v-html="chosenTag"/> x</b-button>
              <span v-else class="mr-2">{{ chosenTag }}</span>
            </span>
          </div>
        </b-form-group>

        <!-- FRAMEWORK ALIGNMENT: mirrors CreateQuestion.vue's framework alignment block -->
        <b-form-group
          v-if="canEditLearningTree"
          label-for="framework_alignment"
          label-cols-sm="4"
          label-cols-lg="3"
          label="Framework Alignment"
        >
          <div class="mt-1">
            <b-button size="sm" variant="outline-primary" @click="$bvModal.show('modal-framework-aligner-learning-tree')">
              Update
            </b-button>
          </div>
        </b-form-group>
        <span v-if="frameworkItemSyncLearningTree.descriptors.length">
          <span v-for="(descriptor, descriptorsIndex) in frameworkItemSyncLearningTree.descriptors"
                :key="`framework-item-sync-learning-tree-descriptors-${descriptorsIndex}`"
                class="mr-2"
          >
            <b-button size="sm"
                      variant="secondary"
                      style="line-height:.8"
                      @click="removeFrameworkItemSyncLearningTree('descriptors', descriptor.id)"
            >{{ descriptor.text }} x
            </b-button>
          </span>
        </span>
        <span v-if="frameworkItemSyncLearningTree.levels.length">
          <span v-for="(level, levelsIndex) in frameworkItemSyncLearningTree.levels"
                :key="`framework-item-sync-learning-tree-levels-${levelsIndex}`"
                class="mr-2"
          >
            <b-button size="sm"
                      variant="secondary"
                      style="line-height:.8"
                      @click="removeFrameworkItemSyncLearningTree('levels', level.id)"
            >{{ level.text }} x
            </b-button>
          </span>
        </span>

        <b-modal id="modal-framework-aligner-learning-tree"
                 title="Framework Alignment"
                 size="lg"
                 no-close-on-backdrop
        >
          <FrameworkAligner :key="`framework-aligner-key-learning-tree-${learningTreeId}`"
                            :item-id="learningTreeId"
                            :framework-item-sync="frameworkItemSyncLearningTree"
                            :is-embedded="true"
                            @setFrameworkItemSync="value => $emit('setFrameworkItemSyncLearningTree', value)"
          />
          <template #modal-footer>
            <b-button
              variant="primary"
              size="sm"
              class="float-right"
              @click="$bvModal.hide('modal-framework-aligner-learning-tree')"
            >
              OK
            </b-button>
          </template>
        </b-modal>
      </b-form>
      <template #modal-footer>
        <b-button size="sm"
                  @click="hideLearningTreePropertiesModal()"
        >
          Cancel
        </b-button>
        <b-button size="sm"
                  variant="primary"
                  @click="$emit('saveLearningTreeProperties')"
        >
          Save
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script>
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { doCopy } from '~/helpers/Copy'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import FrameworkAligner from './FrameworkAligner.vue'

export default {
  name: 'LearningTreeProperties',
  components: {
    faCopy,
    FontAwesomeIcon,
    FrameworkAligner
  },
  props: {
    canEditLearningTree: {
      type: Boolean,
      default: true
    },
    modalId: {
      type: String,
      default: 'modal-learning-tree-properties'
    },
    learningTreeId: {
      type: Number,
      default: 0
    },
    learningTreeForm: {
      type: Object,
      default: () => {
      }
    },
    // Owned by the parent (learning_trees_editor.vue), which fetches it via
    // GET /api/framework-item-sync-learning-tree/learning-tree/{id} when the
    // properties modal is opened, same as learningTreeForm is populated
    // there. This component only reads it and emits change requests up -
    // it does not mutate its own copy.
    frameworkItemSyncLearningTree: {
      type: Object,
      default: () => ({ descriptors: [], levels: [] })
    }
  },
  data: () => ({
    copyIcon: faCopy,
    tag: ''
  }),
  methods: {
    doCopy,
    hideLearningTreePropertiesModal () {
      this.$bvModal.hide(this.modalId)
      this.$emit('resetLearningTreePropertiesModal')
    },
    addTag () {
      if (!this.learningTreeForm.tags.includes(this.tag)) {
        this.learningTreeForm.tags.push(this.tag)
      } else {
        this.$noty.info(`${this.tag} is already on your list of tags.`)
      }
      this.tag = ''
    },
    removeTag (chosenTag) {
      this.learningTreeForm.tags = this.learningTreeForm.tags.filter(tag => tag !== chosenTag)
      this.$noty.info(`${chosenTag} has been removed.`)
    },
    removeFrameworkItemSyncLearningTree (itemType, itemId) {
      const updated = {
        ...this.frameworkItemSyncLearningTree,
        [itemType]: this.frameworkItemSyncLearningTree[itemType].filter(item => item.id !== itemId)
      }
      this.$emit('setFrameworkItemSyncLearningTree', updated)
    }
  }
}
</script>

<style scoped>

</style>
