<template>
  <b-modal id="modal-learning-tree-revision-comparison"
           :key="`modal-learning-tree-revision-comparison`"
           title="Compare Learning Tree Versions"
           dialog-class="modal-learning-tree-comparison-fullscreen"
           hide-footer
  >
    <div class="mb-2 d-flex align-items-center justify-content-between flex-wrap">
      <b-button-group>
        <b-button size="sm"
                  :variant="activeTab === 'assignment' ? 'primary' : 'outline-primary'"
                  @click="activeTab = 'assignment'"
        >
          Assigned Version
        </b-button>
        <b-button size="sm"
                  :variant="activeTab === 'current' ? 'primary' : 'outline-primary'"
                  @click="activeTab = 'current'"
        >
          Latest Version
        </b-button>
      </b-button-group>
      <b-button size="sm"
                variant="primary"
                @click="openUpdateLearningTreeRevision"
      >
        Update to Latest Version
      </b-button>
    </div>
    <iframe v-if="activeTab === 'assignment'"
            key="assignment-tree-iframe"
            allowtransparency="true"
            frameborder="0"
            :src="assignmentTreeSrc"
    />
    <iframe v-if="activeTab === 'current'"
            key="current-tree-iframe"
            allowtransparency="true"
            frameborder="0"
            :src="currentTreeSrc"
    />
  </b-modal>
</template>

<script>
export default {
  name: 'LearningTreeRevisionComparison',
  props: {
    assignmentName: {
      type: String,
      default: ''
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    learningTreeId: {
      type: Number,
      default: 0
    },
    // EK: the root node's assignment_question.question_id, needed to build
    // the /students/learning-trees/... route the same way
    // questions_view.vue's enterLearningTree() already does - see
    // getLearningTreeLearningTreeId() in learning_trees_editor.vue for why
    // an assignmentId in that route is what triggers loading the locked
    // snapshot instead of the live tree.
    rootNodeQuestionId: {
      type: Number,
      default: 0
    }
  },
  data: () => ({
    activeTab: 'assignment'
  }),
  computed: {
    assignmentTreeSrc () {
      const xCenter = window.innerWidth / 2
      return `/students/learning-trees/${this.assignmentId}/${this.learningTreeId}/${this.rootNodeQuestionId}/${xCenter}?previewMode=1`
    },
    currentTreeSrc () {
      return `/instructors/learning-trees/editor/${this.learningTreeId}?previewMode=1`
    }
  },
  methods: {
    show () {
      this.activeTab = 'assignment'
      this.$bvModal.show('modal-learning-tree-revision-comparison')
    },
    // EK: deliberately doesn't duplicate any update logic - just hands off
    // to the same modal-update-learning-tree-revision (UpdateLearningTreeRevision.vue)
    // that the existing "Update to Latest Revision" button/alert already
    // opens, so there's exactly one code path that actually performs the update.
    openUpdateLearningTreeRevision () {
      this.$bvModal.hide('modal-learning-tree-revision-comparison')
      this.$emit('showUpdateLearningTreeRevision')
    }
  }
}
</script>

<!-- EK: not scoped - b-modal portals its markup to <body>, outside this
     component's DOM subtree, so a scoped <style> (which relies on a
     data-attribute added only to this component's own template nodes)
     would never match it. #modal-learning-tree-revision-comparison is a
     unique id, so scoping this globally is safe - same pattern already
     used for .modal-90 in learning_trees_editor.vue. -->
<style>
.modal .modal-learning-tree-comparison-fullscreen {
  max-width: 98vw;
  width: 98vw;
  height: 95vh;
  margin: 2.5vh auto;
}

.modal .modal-learning-tree-comparison-fullscreen .modal-content {
  height: 100%;
}

.modal .modal-learning-tree-comparison-fullscreen .modal-body {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  height: calc(100% - 56px); /* modal-header height */
  overflow: hidden;
}

.modal .modal-learning-tree-comparison-fullscreen .modal-body iframe {
  flex: 1 1 auto;
  width: 100%;
  min-height: 0;
  border: none;
}
</style>
