<template>
  <div>
    <div class="vld-parent">
      <PageTitle :title="title"/>
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />

      <p>
        Using the search box you can find Learning Trees by its corresponding ID. You can then add the Learning Tree to
        your assignment.
      </p>
      <b-form-row>
        <b-col lg="3">
          <b-form-input
            id="learning_tree_id"
            v-model="learningTreeForm.learning_tree_id"
            type="text"
            placeholder="Learning Tree ID"
            :class="{ 'is-invalid': learningTreeForm.errors.has('learning_tree_id') }"
            @keydown="learningTreeForm.errors.clear('learning_tree_id')"
          />
          <has-error :form="learningTreeForm" field="learning_tree_id"/>
        </b-col>
      </b-form-row>

      <div class="mt-3 d-flex flex-row">
        <b-button variant="success" class="mr-2" size="sm" @click="getLearningTreeById()">
          <b-spinner v-if="gettingLearningTree" small type="grow"/>
          Get Learning Tree
        </b-button>
        <b-button variant="dark" size="sm" @click="getStudentView(assignmentId)">
          View Trees
        </b-button>
      </div>
    </div>
    <hr>
    <div class="mt-2 mb-1">
      <b-button v-if="learningTreeSrc" variant="primary" size="sm" @click="addLearningTree()">
        Add Learning Tree
      </b-button>
    </div>
    <iframe v-if="learningTreeSrc.length > 0"
            allowtransparency="true"
            frameborder="0"
            :src="learningTreeSrc"
            style="width: 800px;min-width: 100%;height:800px"
    />
  </div>
</template>
<script>
import axios from 'axios'
import Form from 'vform'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { mapGetters } from 'vuex'

export default {
  components: {
    Loading
  },
  metaInfo () {
    return { title: 'Find Learning Trees' }
  },
  middleware: 'auth',
  data: () => ({
    gettingLearningTree: false,
    learningTreeExists: false,
    isLoading: true,
    learningTreeTitle: '',
    learningTreeDescription: '',
    learningTreeSrc: '',
    title: '',
    learningTreeForm: new Form({
      learning_tree_id: ''
    })
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    tabs () {
      return [
        {
          icon: '',
          name: 'Questions',
          route: 'questions.get'
        },
        {
          icon: '',
          name: 'Learning Trees',
          route: 'learning_trees.get'
        }
      ]
    }
  },
  mounted () {
    if (this.user.role !== 2) {
      this.$router.push({ name: 'no.access' })
      return false
    }
    this.assignmentId = this.$route.params.assignmentId
    this.getAssignmentInfo()
  },
  methods: {
    async addLearningTree () {
      try {
        const { data } = await axios.post(`/api/assignments/${this.assignmentId}/learning-trees/${this.learningTreeForm.learning_tree_id}`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          // need to add to the array of selected ones
          //  this.questions[this.currentPage - 1].inAssignment = true
          this.learningTreeSrc = ''
          this.learningTreeForm.learning_tree_id = ''
        }
      } catch (error) {
        console.log(error)
        this.$noty.error('We could not add the Learning Tree to the assignment.  Please try again or contact us for assistance.')
      }
    },
    async removeLearningTree () {
      // need some sort of current id
      // need to remove from the questions table as well as the learning tree
      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/questions/${question.id}`)
        if (data.type === 'success') {
          this.$noty.info(data.message)
          question.inAssignment = false
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error('We could not remove the question from the assignment.  Please try again or contact us for assistance.')
      }
    },
    getStudentView (assignmentId) {
      this.$router.push(`/assignments/${assignmentId}/questions/view`)
    },
    async getLearningTreeById () {
      this.learningTreeSrc = this.learningTreeTitle = this.learningTreeDescription = ''
      try {
        if (!this.learningTreeForm.learning_tree_id) {
          this.$noty.error('You didn\'t enter a Learning Tree id.')
          return false
        }
        this.gettingLearningTree = true
        const { data } = await axios.post('/api/learning-trees/learning-tree-exists', { 'learning_tree_id': this.learningTreeForm.learning_tree_id })
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.learningTreeSrc = `/instructors/learning-trees/editor/${this.learningTreeForm.learning_tree_id}/1`
          this.learningTreeTitle = data.title
          this.learningTreeDescription = data.description
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.gettingLearningTree = false
    },
    async getAssignmentInfo () {
      this.isLoading = false
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/get-questions-info`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        let assignment = data.assignment
        this.title = `Add Learning Trees to "${assignment.name}"`
        this.questionFilesAllowed = (assignment.submission_files === 'q')// can upload at the question level
      } catch (error) {
        console.log(error.message)
        this.title = 'Add Learning Trees'
      }
      this.isLoading = false
    }
  }
}
</script>
