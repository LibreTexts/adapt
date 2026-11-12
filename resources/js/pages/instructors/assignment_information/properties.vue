<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-assignment-form'"/>
    <RecalculateLatePenaltiesModal ref="recalculateLatePenaltiesModal"/>
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-if="!isLoading">
        <PageTitle title="Assignment Properties"/>
        <b-card class="mb-4">
          <AssignmentProperties
            :key="`assignment-${assignmentKey}`"
            :assignment-groups="assignmentGroups"
            :form="form"
            :course-id="parseInt(courseId)"
            :course-start-date="courseStartDate"
            :course-end-date="assignment.course_end_date"
            :assignment-id="parseInt(assignmentId)"
            :is-beta-assignment="assignment.is_beta_assignment"
            :lms="Boolean(lms)"
            :lms-api="assignment.lms_api"
            :has-submissions-or-file-submissions="assignment.has_submissions_or_file_submissions"
            :is-alpha-course="isAlphaCourse"
            :is-formative-course="isFormativeCourse"
            :owns-all-questions="Boolean(ownsAllQuestions)"
            :is-formative-assignment="Boolean(assignment.formative)"
            :anonymous-users="Boolean(anonymousUsers)"
            :overall-status-is-not-open="assignment.overall_status !== 'Open'"
          />
          <hr>
          <span class="float-right">
            <b-button size="sm"
                      variant="primary"
                      :disabled="submittingAssignmentForm"
                      @click="handleSubmitAssignmentInfo"
            >
              Save
            </b-button>
          </span>
        </b-card>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { mapGetters } from 'vuex'
import AssignmentProperties from '~/components/AssignmentProperties'
import AllFormErrors from '~/components/AllFormErrors'
import RecalculateLatePenaltiesModal from '~/components/RecalculateLatePenaltiesModal'

import {
  editAssignmentProperties,
  getAssignmentGroups,
  prepareForm,
  assignmentForm
} from '~/helpers/AssignmentProperties'
import {
  isLocked,
  getAssignments,
  isLockedMessage,
  initAssignmentGroupOptions,
  updateAssignmentGroupFilter
} from '~/helpers/Assignments'

export default {
  middleware: 'auth',
  components: {
    Loading,
    AssignmentProperties,
    AllFormErrors,
    RecalculateLatePenaltiesModal
  },
  metaInfo () {
    return { title: 'Assignment Properties' }
  },
  data: () => ({
    anonymousUsers: false,
    assignmentKey: 0,
    submittingAssignmentForm: false,
    isAlphaCourse: false,
    isFormativeCourse: false,
    lms: false,
    ownsAllQuestions: true,
    courseStartDate: '',
    assignmentGroups: [],
    allFormErrors: [],
    form: assignmentForm,
    course: {},
    assignTosToView: [],
    assignmentId: 0,
    courseId: 0,
    isLoading: true,
    assignment: {},
    items: []
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  created () {
    this.assignmentId = this.$route.params.assignmentId
    this.getAssignments = getAssignments
    this.isLocked = isLocked
    this.isLockedMessage = isLockedMessage
    this.initAssignmentGroupOptions = initAssignmentGroupOptions
    this.updateAssignmentGroupFilter = updateAssignmentGroupFilter
  },
  async mounted () {
    window.addEventListener('keydown', this.quickSave)
    this.editAssignmentProperties = editAssignmentProperties
    this.getAssignmentGroups = getAssignmentGroups
    this.prepareForm = prepareForm
    if (![2, 4, 5].includes(this.user.role)) {
      await this.$router.push({ name: 'no.access' })
      return false
    }
    const { data } = await axios.get(`/api/assignments/${this.assignmentId}/summary`)
    this.courseId = data.assignment.course_id
    this.courseStartDate = data.assignment.course_start_date
    this.lms = data.assignment.lms
    this.isAlphaCourse = data.assignment.is_alpha_course
    this.isFormativeCourse = data.assignment.is_formative_course
    this.anonymousUsers = data.assignment.anonymous_users
    this.ownsAllQuestions = data.assignment.owns_all_questions
    this.assignmentGroups = await getAssignmentGroups(this.courseId, this.$noty)
    await this.getAssignments()

    this.assignment = this.assignments.find(assignment => parseInt(assignment.id) === parseInt(this.assignmentId))
    console.log(this.assignment)
    this.editAssignmentProperties(this.assignment, this)
    this.assignmentKey++
  },
  beforeDestroy () {
    window.removeEventListener('keydown', this.quickSave)
  },
  methods: {
    quickSave (event) {
      if (event.ctrlKey && event.key === 'S') {
        this.handleSubmitAssignmentInfo()
      }
    },
    async handleSubmitAssignmentInfo () {
      this.submittingAssignmentForm = true
      this.prepareForm(this.form)
      try {
        this.form.course_id = this.courseId
        const { data } = await this.form.patch(`/api/assignments/${this.assignmentId}`)
        let timeout = data.timeout ? data.timeout : 4000
        this.$noty[data.type](data.message, { timeout: timeout })
        if (data.type === 'success' && data.check_late_penalties) {
          await this.$refs.recalculateLatePenaltiesModal.check(this.assignmentId, this.form.name)
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.form.errors.flatten()
          this.$bvModal.show('modal-form-errors-assignment-form')
        }
      }
      this.submittingAssignmentForm = false
    }
  }
}
</script>

<style scoped>

</style>
