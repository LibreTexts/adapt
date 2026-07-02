<template>
  <span>
    <b-modal :id="`modal-clone-question-${questionId}`"
             :title="learningTreeId ? `Clone Learning Tree` : `Clone ${title}`"
             size="lg"
             @hidden="showModalContents= false"
             @shown="initCloneModal()"
    >
      <div v-show="showModalContents">
        <b-alert variant="info" show>
          <span v-if="learningTreeId">
            After cloning this learning tree, you'll have full editing rights to the newly created tree and its root question.
            The remediation nodes will remain shared with the original.
          </span>
          <span v-else>
            After cloning a question, you'll have full editing rights to the newly created question. However, if you would just
            like to use the question in your course without making any changes to the question, please just add the question to your assignment instead of cloning it.
          </span>
        </b-alert>
        <div v-if="isAdmin && !learningTreeId">
          <span>Acting as</span>
          <toggle-button
            style="margin-bottom:5px"
            tabindex="0"
            :width="100"
            :value="actingAs === user.first_name"
            :sync="true"
            :font-size="14"
            :color="toggleColors"
            :labels="{checked: user.first_name, unchecked: 'Admin'}"
            @change="updateActingAs()"
          />
        </div>
        <div v-if="actingAs === user.first_name">
          <RequiredText v-if="!learningTreeId"/>
          <div v-if="!learningTreeId" class="inline-flex d-flex pb-2">
            Clone question to*
            <SavedQuestionsFolders
              :key="`cloned-question-folder-${cloneForm.clone_to_folder_id}`"
              type="my_questions"
              class="pl-2"
              :question-source-is-my-favorites="false"
              :modal-id="`modal-for-bulk-import-${questionId}`"
              :init-saved-questions-folder="cloneForm.clone_to_folder_id"
              :folder-to-choose-from="'My Questions'"
              :create-modal-add-saved-questions-folder="true"
              @savedQuestionsFolderSet="setCloneToFolderId"
            />
          </div>
          <div class="inline-flex d-flex pb-2">
            <span style="padding-right: 18px">Add To (optional)</span>
            <span class="pr-2">
              <b-form-select
                id="course"
                v-model="courseId"
                style="width:300px"
                size="sm"
                :options="courseOptions"
                @change="cloneForm.assignment_id=null;updateAssignments($event)"
              />
            </span>
            <b-form-select id="assignment"
                           v-model="cloneForm.assignment_id"
                           style="width:300px"
                           size="sm"
                           :disabled="courseId === null"
                           :options="assignmentOptions"
                           @change="validateNotWeightedPointsPerQuestionWithSubmissions($event)"
            />
          </div>
        </div>
        <div v-if="actingAs === 'Admin'">
          <p>The copied question will be moved to the new owner's account.</p>
          <v-select id="owner"
                    v-model="questionEditor"
                    placeholder="Please choose the new owner"
                    :options="questionEditorOptions"
                    style="width:300px"
          />
        </div>
      </div>
      <template #modal-footer>
        <b-button size="sm" @click="$bvModal.hide(`modal-clone-question-${questionId}`)">
          Cancel
        </b-button>
        <b-button v-if="!cloning"
                  size="sm"
                  variant="primary"
                  :disabled="weightedPointsPerQuestionWithSubmissions"
                  @click="cloneQuestion()"
        >
          Clone
        </b-button>
         <div v-if="cloning">
          <b-spinner small type="grow"/>
          {{ learningTreeId ? 'Cloning learning tree...' : 'Cloning question...' }}
        </div>
      </template>
    </b-modal>
    <a :id="`clone-${questionId}-${elementId}`"
       href=""
       style="text-decoration: none"
       @click.prevent="openModalCopyQuestion()"
    ><span class="alignMiddle">
      <b-button v-if="asButton" size="sm" variant="outline-secondary">
        <font-awesome-icon
          :id="`clone-${questionId}-${elementId}`"
          :class="canClone ? 'text-muted' : 'text-danger'"
          :icon="copyIcon"
        />
        Clone</b-button>
      <span v-if="!asButton">
        <font-awesome-icon
          v-if="bigIcon"
          :id="`clone-${questionId}-${elementId}`"
          :class="canClone ? 'text-muted' : 'text-danger'"
          :icon="copyIcon"
          style="font-size:18px;"
        />
      </span>
      <font-awesome-icon
        v-if="!bigIcon"
        :class="canClone ? 'text-muted' : 'text-danger'"
        :icon="copyIcon"
      /> </span></a>
<b-tooltip :target="`clone-${questionId}-${elementId}`"
           triggers="hover"
           delay="750"
>
  <span v-if="canClone">
    <span v-if="isForgeDraft && isAdmin">
      Clone the main Forge question ({{ questionId }}) to your account or that of another instructor's. Drafts cannot be cloned individually.
    </span>
    <span v-else-if="isForgeDraft">
      Clone the main Forge question ({{ questionId }}). Drafts cannot be cloned individually.
    </span>
    <span v-else-if="isAdmin && !learningTreeId">
      Make a clone of question {{ questionId }} to your account or that of another instructor's.
    </span>
    <span v-else-if="learningTreeId">
      Clone this learning tree into your account.
    </span>
    <span v-else>
      Clone {{ title }}.
    </span>
  </span>
  <span v-if="!canClone">
    <span v-if="['ccbyncnd', 'ccbynd', 'arr'].includes(license)">
      Due to licensing restrictions, this question cannot be cloned.
    </span>
    <span v-if="!this.public && !learningTreeId">
      Since this question is not public, it cannot be cloned.
    </span>
    <span v-if="learningTreeId">
      Since this learning tree is not public and you do not own it, you cannot clone it.
    </span>
  </span>
</b-tooltip>
  </span>
</template>

<script>
import axios from 'axios'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import SavedQuestionsFolders from './SavedQuestionsFolders'
import { mapGetters } from 'vuex'
import { ToggleButton } from 'vue-js-toggle-button'
import Form from 'vform'

export default {
  name: 'CloneQuestion',
  components: {
    FontAwesomeIcon,
    SavedQuestionsFolders,
    ToggleButton
  },
  props: {
    learningTreeId: {
      type: Number,
      default: 0
    },
    isForgeDraft: {
      type: Boolean,
      default: false
    },
    asButton: {
      type: Boolean,
      default: false
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    bigIcon: {
      type: Boolean,
      default: false
    },
    questionId: {
      type: Number,
      default: 0
    },
    questionEditorUserId: {
      type: Number,
      default: 0
    },
    title: {
      type: String,
      default: ''
    },
    library: {
      type: String,
      default: ''
    },
    license: {
      type: String,
      default: ''
    },
    public: {
      type: Number,
      default: 1
    },
    nonTechnology: {
      type: Number,
      default: 0
    },
    elementId: {
      type: Number,
      default: 0
    }
  },
  data: () => ({
    learningTreeAuthorId: 0,
    learningTreePublic: 1,
    weightedPointsPerQuestionWithSubmissions: false,
    cloning: false,
    showModalContents: false,
    courseId: null,
    courseOptions: [{ value: null, text: 'Please choose a course' }],
    assignmentOptions: [{ value: null, text: 'Please choose an assignment' }],
    cloneForm: new Form({
      assignment_id: null,
      clone_to_folder_id: null
    }),
    actingAs: '',
    toggleColors: window.config.toggleColors,
    copiedQuestionsFolder: true,
    addToAssignment: true,
    questionEditorOptions: [],
    questionEditor: null,
    copyIcon: faCopy
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    isAdmin: () => window.config.isAdmin,
    canClone () {
      if (this.learningTreeId) {
        return this.learningTreeAuthorId === this.user.id || Boolean(this.learningTreePublic)
      }
      if (this.user.id !== this.questionEditorUserId) {
        return !['ccbyncnd', 'ccbynd', 'arr'].includes(this.license) && Boolean(this.public)
      }
      return true
    }
  },
  mounted () {
    this.actingAs = this.user.first_name
    if (this.learningTreeId) {
      this.getLearningTreeAuthor()
    }
  },
  watch: {
    learningTreeId: {
      immediate: false, // mounted() already handles the initial call
      handler (newVal) {
        if (newVal) {
          this.getLearningTreeAuthor()
        } else {
          this.learningTreeAuthorId = 0
        }
      }
    }
  },
  methods: {
    async getLearningTreeAuthor () {
      try {
        const { data } = await axios.get(`/api/learning-trees/${this.learningTreeId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.learningTreeAuthorId = data.author_id
        this.learningTreePublic = data.public
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async validateNotWeightedPointsPerQuestionWithSubmissions (assignmentId) {
      try {
        const { data } = await axios.get(`/api/assignments/validate-not-weighted-points-per-question-with-submissions/${assignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        if (data.weighted_points_per_question_with_submissions) {
          this.$noty.error('This assignment\'s points is determined by weights and already has submissions so you cannot add another question.')
          this.weightedPointsPerQuestionWithSubmissions = true
        } else {
          this.weightedPointsPerQuestionWithSubmissions = false
        }
      } catch (error) {

      }
    },
    async initCloneModal () {
      await this.getClonedQuestionsFolderId()
      await this.getNonBetaCoursesAndAssignments()
      this.showModalContents = true
    },
    async getClonedQuestionsFolderId () {
      try {
        const { data } = await axios.get('/api/saved-questions-folders/cloned-questions-folder')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.cloneForm.clone_to_folder_id = data.cloned_questions_folder_id
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    updateAssignments (courseId) {
      this.assignmentOptions = [{ value: null, text: 'Please choose an assignment' }]
      let assignments = this.assignments.find(course => course.course_id === courseId).assignments
      console.log(assignments)
      for (let i = 0; i < assignments.length; i++) {
        let assignment = assignments[i]
        let assignmentOption = { value: assignment.assignment_id, text: assignment.assignment_name }
        this.assignmentOptions.push(assignmentOption)
      }
    },
    async getNonBetaCoursesAndAssignments () {
      this.courseOptions = [{ value: null, text: 'Please choose a course' }]
      try {
        const { data } = await axios.get('/api/courses/non-beta-courses-and-assignments')
        if (this.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        for (let i = 0; i < data.courses.length; i++) {
          let course = data.courses[i]
          let courseOption = { value: course.course_id, text: course.course_name }
          this.courseOptions.push(courseOption)
        }
        this.courses = data.courses
        this.assignments = data.assignments

        if (this.assignmentId) {
          for (let i = 0; i < this.assignments.length; i++) {
            let courseAssignments = this.assignments[i]
            let assignmentInfo = courseAssignments.assignments.find(assignment => assignment.assignment_id === this.assignmentId)
            console.log(assignmentInfo)
            if (assignmentInfo) {
              this.courseId = courseAssignments.course_id
              this.updateAssignments(this.courseId)
              this.cloneForm.assignment_id = this.assignmentId
              await this.validateNotWeightedPointsPerQuestionWithSubmissions(this.cloneForm.assignment_id)
              return
            }
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    setCloneToFolderId (folderId) {
      this.cloneForm.clone_to_folder_id = folderId
      console.log(folderId)
    },
    updateActingAs () {
      this.actingAs = this.actingAs === this.user.first_name ? 'Admin' : this.user.first_name
    },
    openModalCopyQuestion () {
      if (this.learningTreeId) {
        if (this.canClone) {
          this.$bvModal.show(`modal-clone-question-${this.questionId}`)
        }
        return false
      }
      if (!this.canClone) {
        return false
      }
      this.getAllQuestionEditors()
      this.$bvModal.show(`modal-clone-question-${this.questionId}`)
    },
    async cloneQuestion () {
      this.cloneForm.acting_as = this.actingAs === 'Admin' ? 'admin' : 'instructor'
      this.cloneForm.question_id = this.questionId
      this.cloneForm.question_editor_user_id = this.actingAs === 'Admin' ? this.questionEditor.value : this.user.id
      this.cloneForm.learning_tree_id = this.learningTreeId || 0
      if (this.cloneForm.acting_as === 'admin') {
        this.cloneForm.assignment_id = 0
        this.cloneForm.clone_to_folder_id = 0
      }
      if (!this.learningTreeId && !this.cloneForm.assignment_id && this.courseId) {
        this.$noty.error('Please either choose an assignment or do not choose a course.')
        return false
      }
      this.cloning = true
      try {
        const { data } = await this.cloneForm.post('/api/questions/clone')
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.$emit('reloadQuestions')
          this.$bvModal.hide(`modal-clone-question-${this.questionId}`)
          this.cloneForm.clone_to_folder_id = null
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.cloning = false
    },
    async getAllQuestionEditors () {
      try {
        const { data } = await axios.get('/api/user/question-editors/0')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.questionEditorOptions = data.question_editors
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>

<style scoped>

</style>
