<template>
  <div>
    <b-modal id="modal-email-students-with-learning-tree-submissions"
             title="Email Students with Submissions"
             size="xl"
             no-close-on-backdrop
             @hidden="emitReload"
    >
      <p>
        You can contact the affected students either by copy/pasting their email addresses into your own email
        application or ADAPT can send the email for you.
      </p>
      <p>
        Emails of students with submissions:
        <span id="learning-tree-student-emails">
          {{ formattedStudentEmailsAssociatedWithSubmissions }} <a href=""
                                                                   aria-label="Copy student emails"
                                                                   @click.prevent="doCopy('learning-tree-student-emails')"
        >
            <font-awesome-icon :icon="copyIcon" />
          </a>
        </span>
        <ErrorMessage :message="studentsWithSubmissionsForm.errors.get('emails')" />
      </p>
      <ckeditor
        id="learning_tree_email_to_send"
        v-model="studentsWithSubmissionsForm.message"
        tabindex="0"
        rows="6"
        :config="richEditorConfig"
        max-rows="6"
        @namespaceloaded="onCKEditorNamespaceLoaded"
        @ready="handleFixCKEditor()"
      />
      <ErrorMessage :message="studentsWithSubmissionsForm.errors.get('message')" />
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  @click="emailStudentsWithSubmissions"
        >
          Send my students the above email
        </b-button>
        <b-button size="sm"
                  @click="$bvModal.hide('modal-email-students-with-learning-tree-submissions')"
        >
          I'll contact the students myself
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-update-learning-tree-revision"
             :key="`modal-update-learning-tree-revision`"
             title="Update Learning Tree to Latest Revision"
             size="lg"
    >
      <b-alert show variant="info">
        This Learning Tree's structure has changed since it was added to this assignment, or at least one of its
        node questions has a newer revision available. Updating will re-sync the tree to how it currently looks,
        including the latest revision of every node's question.
      </b-alert>
      <b-alert variant="danger" show>
        <b-form-checkbox
          id="learning-tree-checkbox-1"
          v-model="understandStudentSubmissionsRemoved"
          name="learning_tree_student_submissions_removed"
          :value="true"
          :unchecked-value="false"
          @hidden="understandStudentSubmissionsRemoved=false"
        >
          I understand that all student submissions for this Learning Tree in this assignment (the root question
          and every node) will be removed and their scores for this assignment will be updated. Submissions for
          this Learning Tree in any other assignment are not affected. Please inform your class to resubmit.
        </b-form-checkbox>
      </b-alert>
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  @click="updateTheLearningTreeRevision"
        >
          Update
        </b-button>
        <b-button size="sm"
                  @click="$bvModal.hide('modal-update-learning-tree-revision')"
        >
          Cancel
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script>
import axios from 'axios'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { doCopy } from '~/helpers/Copy'
import { fixCKEditor } from '~/helpers/accessibility/fixCKEditor'
import CKEditor from 'ckeditor4-vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Form from 'vform'
import { mapGetters } from 'vuex'
import ErrorMessage from '../ErrorMessage.vue'

export default {
  name: 'UpdateLearningTreeRevision',
  components: {
    ErrorMessage,
    FontAwesomeIcon,
    ckeditor: CKEditor.component
  },
  props: {
    questionNumber: {
      type: Number,
      default: 0
    },
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
    }
  },
  data: () => ({
    studentsWithSubmissionsForm: new Form({
      message: '',
      emails: []
    }),
    richEditorConfig: {
      toolbar: [],
      removeButtons: '',
      height: 200
    },
    copyIcon: faCopy,
    formattedStudentEmailsAssociatedWithSubmissions: '',
    understandStudentSubmissionsRemoved: false,
    newRootQuestionId: null
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  methods: {
    doCopy,
    onCKEditorNamespaceLoaded (CKEDITOR) {
      CKEDITOR.addCss('.cke_editable { font-size: 15px; }')
    },
    handleFixCKEditor () {
      fixCKEditor(this)
    },
    async emailStudentsWithSubmissions () {
      try {
        this.studentsWithSubmissionsForm.assignment_id = this.assignmentId
        const { data } = await this.studentsWithSubmissionsForm.post(`/api/question-revisions/email-students-with-submissions`)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        this.$bvModal.hide('modal-email-students-with-learning-tree-submissions')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    showUpdateLearningTreeRevision () {
      this.understandStudentSubmissionsRemoved = false
      this.$bvModal.show('modal-update-learning-tree-revision')
    },
    async updateTheLearningTreeRevision () {
      if (!this.understandStudentSubmissionsRemoved) {
        this.$noty.info('Please check the box stating that you understand that all existing student submissions for this Learning Tree in this assignment will be removed and their scores for this assignment will be updated.')
        return false
      }
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/learning-tree/${this.learningTreeId}/update-to-latest-revision`,
          { understand_student_submissions_removed: this.understandStudentSubmissionsRemoved })
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        // EK: the success toast gets cut off by the navigation in
        // reloadAfterLearningTreeUpdate() before it can render, so stash the
        // message and let the reloaded page show it once it's actually there
        // to be seen - see questions_view.vue's mounted().
        localStorage.setItem('learningTreeUpdateMessage', data.message)
        this.newRootQuestionId = data.new_root_question_id
        this.$bvModal.hide('modal-update-learning-tree-revision')
        this.initEmailStudentsWithSubmissions(data)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    emitReload () {
      this.$emit('reloadSingleQuestion', this.newRootQuestionId)
    },
    initEmailStudentsWithSubmissions (data) {
      if (data.student_emails_associated_with_submissions && data.student_emails_associated_with_submissions.length) {
        this.studentsWithSubmissionsForm.emails = data.student_emails_associated_with_submissions
        this.formattedStudentEmailsAssociatedWithSubmissions = data.student_emails_associated_with_submissions.join(', ')
        let lastName = this.user.last_name
        this.studentsWithSubmissionsForm.message = `<p>Hi,</p><p>There was an issue with the Learning Tree in Assignment ${this.assignmentName}.&nbsp; Because of this, you'll need to redo your work in this Learning Tree.</p><p>-Professor ${lastName}</p>`
        this.$bvModal.show('modal-email-students-with-learning-tree-submissions')
      } else {
        this.emitReload()
      }
    }
  }
}
</script>
