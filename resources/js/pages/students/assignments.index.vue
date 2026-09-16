<template>
  <div>
    <RedirectToClickerModal
      :key="`redirect-to-clicker-modal-${clickerAssignmentId}-${clickerQuestionId}-${redirectToClickerModalKey}`"
      :assignment-id="clickerAssignmentId"
      :question-id="clickerQuestionId"
      @resetClickerAssignmentIdClickerQuestionId="resetClickerAssignmentIdClickerQuestionId()"
    />
    <b-modal
      id="modal-progress-report"
      title="Progress Report"
      hide-footer
      size="lg"
    >
      <p v-show="!atLeastOneAssignmentNotIncludedInWeightedAverage">
        The progress report only includes scores for assignments in which the scores are already released.
      </p>
      <p v-show="atLeastOneAssignmentNotIncludedInWeightedAverage">
        The progress report only includes released scores for those assignments which are included in your final
        average.
      </p>
      <b-table
        v-show="scoreInfoByAssignmentGroup.length"
        aria-label="Progress Report"
        striped
        hover
        :no-border-collapse="true"
        :items="scoreInfoByAssignmentGroup"
        :fields="scoreInfoByAssignmentGroupFields"
      />
    </b-modal>
    <b-modal
      id="modal-z-score"
      title="Explanation of the Z-Score"
      hide-footer
    >
      <p>
        The z-score tells you how many standard deviations you are away from the mean.
        A z-score of 0 tells you that your score was the exact mean of the distribution.
      </p>
      <p>
        For bell-shaped data, about 95% of observations will fall within 2 standard deviations of the mean;
        z-scores outside of this range are considered atypical.
      </p>
    </b-modal>
    <b-modal
      id="modal-upload-file"
      ref="modal"
      title="Upload File"
      ok-title="Submit"
      @ok="handleOk"
      @hidden="resetModalForms"
    >
      <b-form ref="form">
        <p>Accepted file types are: {{ getAcceptedFileTypes() }}.</p>
        <b-form-file
          ref="assignmentFileInput"
          v-model="form.assignmentFile"
          placeholder="Choose a file or drop it here..."
          drop-placeholder="Drop file here..."
          :accept="getAcceptedFileTypes()"
        />
        <div v-if="uploading">
          <b-spinner small type="grow"/>
          Uploading file...
        </div>
        <input type="hidden" class="form-control is-invalid">
        <div class="help-block invalid-feedback">
          {{ form.errors.get('assignmentFile') }}
        </div>
      </b-form>
    </b-modal>
    <b-modal id="modal-status"
             title="Explanation of Statuses"
             size="lg"
             hide-footer
    >
      <table class="table table-striped">
        <thead>
        <tr>
          <th>Status</th>
          <th>Explanation</th>
        </tr>
        </thead>
        <tr>
          <td>
            <span :class="getStatusTextClass('Upcoming')">Upcoming</span>
          </td>
          <td>The assignment is not yet open.</td>
        </tr>
        <tr>
          <td>
            <span :class="getStatusTextClass('Open')">Open</span>
          </td>
          <td>The assignment is open and you can submit responses.</td>
        </tr>
        <tr>
          <td>
            <span :class="getStatusTextClass('Late')">Late</span>
          </td>
          <td>You may still submit responses but there may be a late penalty. Enter the assignment for details.</td>
        </tr>
        <tr>
          <td>
            <span :class="getStatusTextClass('Closed')">Closed</span>
          </td>
          <td>The assignment is past due and you may no longer submit responses.</td>
        </tr>
      </table>
    </b-modal>
    <b-modal
      id="modal-assignment-submission-feedback"
      ref="modal"
      size="xl"
      title="Assignment Submission Feedback"
      ok-only
      ok-variant="primary"
      ok-title="Close"
      @ok="closeAssignmentSubmissionFeedbackModal"
    >
      <b-card title="Summary">
        <b-card-text>
          <p>
            Submitted File:
            <b-button variant="link" style="padding:0px; padding-bottom:3px"
                      @click="downloadSubmissionFile(assignmentFileInfo.assignment_id, assignmentFileInfo.submission, assignmentFileInfo.original_filename)"
            >
              {{ assignmentFileInfo.original_filename }}
            </b-button>
            <br>
            Score: {{ assignmentFileInfo.submission_file_score }}<br>
            Date submitted: {{ assignmentFileInfo.date_submitted }}<br>
            Date graded: {{ assignmentFileInfo.date_graded }}<br>
            Text feedback: {{ assignmentFileInfo.text_feedback }}<br>
          </p>
          <hr>
        </b-card-text>
      </b-card>
      <div v-if="assignmentFileInfo.file_feedback_url">
        <div class="d-flex justify-content-center mt-5">
          <iframe width="600" height="600" :src="this.assignmentFileInfo.file_feedback_url"/>
        </div>
      </div>
    </b-modal>
    <PageTitle v-if="canViewAssignments" :title="title"/>
    <div class="vld-parent">
      <!--Use loading instead of isLoading because there's both the assignment and scores loading-->
      <loading :active.sync="loading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-if="hasAssignments && !loading">
        <div v-if="lmsOnlyEntry && !user.is_instructor_logged_in_as_student">
          <b-alert show variant="info">
            All assignments are served through your LMS such as Canvas, Blackboard, or Brightspace. Please log in to
            your LMS
            to access your assignments.
          </b-alert>
        </div>
        <div v-if="!lmsOnlyEntry || user.is_instructor_logged_in_as_student">
          <div class="text-center">
            <div class="text-center">
              <p v-show="letterGradesReleased" class="font-weight-bold">
                Your current letter grade is
                "{{ letterGrade }}".
              </p>
            </div>
            <p v-show="studentsCanViewWeightedAverage" class="font-weight-bold">
              Your current weighted average
              is {{ weightedAverage }}.
            </p>
            <p v-if="zScore !== false" class="font-weight-bold">
              Your current z-score for the course is {{ zScore }}.
              <a id="course-z-score-tooltip"
                 href="#"
              >
                <b-icon class="text-muted" icon="question-circle" aria-label="Explanation of z-score"/>
              </a>
              <b-tooltip target="course-z-score-tooltip"
                         triggers="hover focus"
                         delay="250"
              >
                The z-score for the course is computed using all assignments that are for credit, not including extra
                credit assignments.
                Your overall weighted average is then compared with those of your peers in order to compute your
                relative
                standing in the course.
              </b-tooltip>
            </p>
          </div>
          <b-container>
            <b-row class="mb-4">
              <b-col lg="3">
                <b-form-select v-if="assignmentGroupOptions.length>1"
                               v-model="chosenAssignmentGroup"
                               title="Filter by assignment group"
                               size="sm"
                               :options="assignmentGroupOptions"
                               @input="updateAssignmentGroupFilter();getAssignmentsWithinChosenAssignmentGroup()"
                />
              </b-col>
              <b-col lg="3">
                <b-form-select v-model="chosenAssignmentStatus"
                               title="Filter by assignment status"
                               size="sm"
                               :options="assignmentStatusOptions"
                               @input="getAssignmentsWithChosenAssignmentStatus()"
                />
              </b-col>
              <b-col class="pt-1">
                <b-button v-show="scoreInfoByAssignmentGroup.length && showProgressReport"
                          variant="primary"
                          size="sm"
                          @click="$bvModal.show('modal-progress-report')"
                >
                  Progress Report
                </b-button>
              </b-col>
            </b-row>
          </b-container>
          <div v-show="showNoMatchingMessage">
            <b-alert show variant="info">
              There are no assignments that match this criteria.
            </b-alert>
          </div>
          <p v-show="atLeastOneAssignmentNotIncludedInWeightedAverage && assignments.length>1">
            Submissions for assignments marked with an asterisk (<span class="text-danger">*</span>) will not be
            included
            in your final weighted average.
          </p>
          <b-table
            v-show="assignments.length"
            id="summary_of_questions_and_submissions"
            aria-label="Assignments"
            striped
            hover
            small
            :no-border-collapse="true"
            :items="assignments"
            :fields="assignmentFields"
          >
            <template v-slot:head(z_score)="data">
              Z-Score
              <QuestionCircleTooltipModal :aria-label="'z-score-explained'" :modal-id="'modal-z-score'"/>
            </template>

            <template v-slot:head(status)="data">
              Status
              <QuestionCircleTooltipModal :aria-label="'status-explained'" :modal-id="'modal-status'"/>
            </template>
            <template #cell(name)="data">
              <span v-if="data.item.time_limit" :id="`time-limit-tooltip-${data.item.id}`" class="mr-1" style="color: #0f6674;" aria-label="This assignment has a time limit">
                <b-icon icon="stopwatch" font-scale="1.1"/>
              </span>
              <b-tooltip v-if="data.item.time_limit" :target="`time-limit-tooltip-${data.item.id}`"
                         delay="250"
                         triggers="hover focus"
              >
                This assignment has a time limit of {{ data.item.time_limit }} once started.
              </b-tooltip>
              <span v-show="data.item.is_available">
                <a href="" @click.prevent="getAssignmentSummaryView(data.item)">{{ data.item.name }}</a>
              </span>
              <span v-show="!data.item.is_available">
                {{ data.item.name }}
              </span>
              <span v-show="!data.item.include_in_weighted_average"
                    :id="`not-included-tooltip-${data.item.id}`" class="text-danger"
              >*</span>
              <b-tooltip :target="`not-included-tooltip-${data.item.id}`"
                         delay="250"
                         triggers="hover focus"
              >
                {{ data.item.name }} will not be included when computing your final weighted average.
              </b-tooltip>
            </template>
            <template #cell(available_from)="data">
              <span v-show="data.item.assessment_type !== 'clicker'">
                {{ $moment(data.item.available_from, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY') }}<br>
                {{ $moment(data.item.available_from, 'YYYY-MM-DD HH:mm:ss A').format('h:mm A') }}
              </span>
              <span v-show="data.item.assessment_type === 'clicker'">
                N/A</span>
            </template>
            <template #cell(due)="data">
              <span v-show="data.item.assessment_type !== 'clicker'">
                {{ $moment(data.item.due.due_date, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY') }}<br>
                {{ $moment(data.item.due.due_date, 'YYYY-MM-DD HH:mm:ss A').format('h:mm A') }}
                {{ data.item.due.is_extension ? '(Extension)' : '' }}
              </span>
              <span v-show="data.item.assessment_type === 'clicker'">
                N/A
              </span>
            </template>
            <template #cell(status)="data">
              <span v-if="assignmentLevelOverrides.includes(data.item.id)">
                 <span class="text-success"> Open</span>
              </span>
              <span v-else :class="getStatusTextClass(data.item.status)"> {{ data.item.status }}</span>
            </template>
            <template #cell(score)="data">
              <span v-if="data.item.score === 'Not yet released'">Not yet released</span>
              <span v-if="data.item.score !== 'Not yet released'"> {{ data.item.score }}/{{
                  data.item.total_points
                }}</span>
            </template>
          </b-table>
        </div>
      </div>
      <div v-else>
        <b-alert :show="showNoAssignmentsAlert" variant="warning">
          <a href="#" class="alert-link">This course currently
            has
            no assignments.</a>
        </b-alert>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Form from 'vform'
import { downloadFile, downloadSolutionFile } from '~/helpers/DownloadFiles'
import { submitUploadFile, getAcceptedFileTypes } from '~/helpers/UploadFiles'

import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { initAssignmentGroupOptions, updateAssignmentGroupFilter } from '~/helpers/Assignments'
import QuestionCircleTooltipModal from '~/components/QuestionCircleTooltipModal'
import { mapGetters } from 'vuex'
import { initCentrifuge } from '~/helpers/Centrifuge'
import { getStatusTextClass } from '~/helpers/AssignTosStatus'
import RedirectToClickerModal from '../../components/RedirectToClickerModal.vue'
import {
  initClickerAssignmentsForEnrolledAndOpenCourses,
  resetClickerAssignmentIdClickerQuestionId
} from '../../helpers/clicker'

export default {
  components: {
    RedirectToClickerModal,
    Loading,
    QuestionCircleTooltipModal
  },
  metaInfo () {
    return { title: 'My Assignments' }
  },
  data: () => ({
    assignmentLevelOverrides: [],
    redirectToClickerModalKey: 0,
    clickerAssignmentId: 0,
    clickerQuestionId: 0,
    showNoMatchingMessage: false,
    chosenAssignmentStatus: null,
    assignmentStatuses: [],
    centrifugo: {},
    lmsOnlyEntry: false,
    showProgressReport: false,
    atLeastOneAssignmentNotIncludedInWeightedAverage: false,
    scoreInfoByAssignmentGroup: [],
    scoreInfoByAssignmentGroupFields: [
      {
        key: 'assignment_group',
        isRowHeader: true
      },
      {
        key: 'sum_of_scores',
        label: 'Points Received',
        tdClass: 'text-center',
        thClass: 'text-center'
      },
      {
        key: 'total_points',
        label: 'Points Possible',
        tdClass: 'text-center',
        thClass: 'text-center'
      },
      {
        key: 'percent',
        tdClass: 'text-center',
        thClass: 'text-center'
      },
      {
        key: 'z_score',
        label: 'Z-Score',
        tdClass: 'text-center',
        thClass: 'text-center'
      }
    ],
    assignmentFields: [
      {
        key: 'name',
        label: 'Assignment Name',
        sortable: true,
        isRowHeader: true
      },
      {
        key: 'assignment_group',
        label: 'Group',
        sortable: true
      },
      {
        key: 'available_from',
        sortable: true,
        thStyle: { width: '170px' }
      },
      {
        key: 'due',
        sortable: true,
        thStyle: { width: '170px' }
      },
      {
        key: 'status',
        sortable: true,
        thStyle: { width: '95px' }
      },
      {
        key: 'number_submitted',
        label: 'Submitted'
      },
      'score',
      {
        key: 'z_score',
        thStyle: { width: '95px' }
      }
    ],
    assignmentGroupOptions: [],
    assignmentStatusOptions: [{ value: null, text: 'All Statuses' },
      { value: 'Upcoming', text: 'Upcoming' },
      { value: 'Open', text: 'Open' },
      { value: 'Closed', text: 'Closed' },
      { value: 'Late', text: 'Late' },
      { value: 'Completed', text: 'Completed' },
      { value: 'Not Completed', text: 'Not Completed' }],
    chosenAssignmentGroupText: null,
    chosenAssignmentGroup: null,
    loading: true,
    studentsCanViewWeightedAverage: false,
    letterGradesReleased: false,
    zScore: false,
    weightedAverage: '',
    letterGrade: '',
    title: '',
    form: new Form({
      assignmentFile: null,
      assignmentId: null
    }),
    assignmentFileInfo: {},
    uploading: false,
    assignments: [],
    courseId: false,
    hasAssignments: false,
    showNoAssignmentsAlert: false,
    canViewAssignments: false,
    originalAssignments: []
  }),
  middleware: 'auth',
  computed: mapGetters({
    user: 'auth/user'
  }),
  beforeDestroy () {
    try {
      if (this.centrifuge) {
        this.centrifuge.disconnect()
      }
    } catch (error) {
      // won't be a function for all the other ones that haven't been defined on the page
    }
  },
  async mounted () {
    this.courseId = this.$route.params.courseId
    await this.getAssignmentStatusesByCourseAndUser()
    await this.getScoresByUser()
    await this.initClickerAssignmentsForEnrolledAndOpenCourses()
  },
  methods: {
    downloadSolutionFile,
    downloadFile,
    submitUploadFile,
    getAcceptedFileTypes,
    initAssignmentGroupOptions,
    updateAssignmentGroupFilter,
    getStatusTextClass,
    resetClickerAssignmentIdClickerQuestionId,
    initClickerAssignmentsForEnrolledAndOpenCourses,
    async getAssignmentStatusesByCourseAndUser () {
      try {
        const { data } = await axios.get(`/api/courses/${this.courseId}/assignment-statuses`)
        if (data.type === 'success') {
          this.assignmentStatuses = data.assignment_statuses
          this.assignmentLevelOverrides = data.assignment_level_overrides
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    filterAssignmentsWithChosenAssignmentStatus () {
      if (('Completed', 'Not Completed').includes(this.chosenAssignmentStatus)) {
        const assignmentsLength = this.assignments.length
        let assignments
        assignments = []
        for (let i = 0; i < assignmentsLength; i++) {
          const assignment = this.assignments[i]
          if (assignment.number_submitted.includes('/')) {
            const numberSubmittedArr = assignment.number_submitted.split('/')
            const numberSubmitted = numberSubmittedArr[0]
            const numberQuestions = numberSubmittedArr[1]
            const include = this.chosenAssignmentStatus === 'Completed' ? numberSubmitted === numberQuestions : numberSubmitted !== numberQuestions
            if (include) {
              assignments.push(assignment)
            }
          }
        }
        this.assignments = assignments
      } else {
        this.assignments = this.assignments.filter(assignment => assignment.status === this.chosenAssignmentStatus)
      }
    },
    getAssignmentsWithinChosenAssignmentGroup () {
      this.assignments = this.chosenAssignmentGroup === null
        ? this.originalAssignments
        : this.originalAssignments.filter(assignment => assignment.assignment_group === this.chosenAssignmentGroupText)
      if (this.chosenAssignmentStatus) {
        this.filterAssignmentsWithChosenAssignmentStatus()
      }
      this.showNoMatchingMessage = !this.assignments.length
    },
    getAssignmentsWithChosenAssignmentStatus () {
      this.assignments = this.originalAssignments
      if (this.chosenAssignmentStatus) {
        this.filterAssignmentsWithChosenAssignmentStatus()
      }
      if (this.chosenAssignmentGroup) {
        this.assignments = this.assignments.filter(assignment => assignment.assignment_group === this.chosenAssignmentGroupText)
      }
      this.showNoMatchingMessage = !this.assignments.length
    },
    async getScoresByUser () {
      try {
        const { data } = await axios.get(`/api/scores/${this.courseId}/get-course-scores-by-user`)
        console.log(data)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          this.loading = false
          return false
        }
        this.scoreInfoByAssignmentGroup = data.score_info_by_assignment_group
        const zScoresByAssignmentGroup = data.z_scores_by_assignment_group
        this.canViewAssignments = true
        this.hasAssignments = data.assignments.length > 0
        this.lmsOnlyEntry = this.hasAssignments && data.assignments[0].lms && data.assignments[0].lms_only_entry
        this.showNoAssignmentsAlert = !this.hasAssignments
        this.assignments = data.assignments
        for (let i = 0; i < this.assignments.length; i++) {
          if (!this.assignments[i].include_in_weighted_average) {
            this.atLeastOneAssignmentNotIncludedInWeightedAverage = true
          }
          const assignmentStatus = this.assignmentStatuses.find(item => item.assignment_id === this.assignments[i].id)
          this.assignments[i].status = assignmentStatus ? assignmentStatus.status : 'N/A'
        }

        const groupTotals = {}
        for (let i = 0; i < this.assignments.length; i++) {
          const assignment = this.assignments[i]
          if (assignment.include_in_weighted_average && assignment.show_scores) {
            if (!groupTotals[assignment.assignment_group]) {
              groupTotals[assignment.assignment_group] = {
                assignment_group: assignment.assignment_group,
                sum_of_scores: 0,
                total_points: 0,
                percent: '0%'
              }
            }
            groupTotals[assignment.assignment_group].sum_of_scores += +assignment.score
            groupTotals[assignment.assignment_group].total_points += +assignment.total_points
            if (groupTotals[assignment.assignment_group].total_points > 0) {
              groupTotals[assignment.assignment_group].percent = Number(100 * groupTotals[assignment.assignment_group].sum_of_scores / groupTotals[assignment.assignment_group].total_points).toFixed(0) + '%'
            }
            if (zScoresByAssignmentGroup[assignment.assignment_group]) {
              groupTotals[assignment.assignment_group].z_score = zScoresByAssignmentGroup[assignment.assignment_group]
            }
          }
        }

        this.scoreInfoByAssignmentGroup = Object.values(groupTotals)
        for (let i = 0; i < this.scoreInfoByAssignmentGroup.length; i++) {
          this.scoreInfoByAssignmentGroup[i].sum_of_scores = +Number(this.scoreInfoByAssignmentGroup[i].sum_of_scores).toFixed(4)
        }
        console.error(this.scoreInfoByAssignmentGroup)

        this.originalAssignments = this.assignments
        this.initAssignmentGroupOptions(this.assignments)

        this.title = `${data.course.name} Assignments`
        this.studentsCanViewWeightedAverage = Boolean(data.course.students_can_view_weighted_average)
        this.letterGradesReleased = Boolean(data.course.letter_grades_released)
        this.showProgressReport = data.show_progress_report
        if (this.studentsCanViewWeightedAverage || this.letterGradesReleased) {
          this.weightedAverage = data.weighted_score
          this.letterGrade = data.letter_grade
        }
        this.zScore = data.z_score
        if (!this.zScore) {
          this.assignmentFields = this.assignmentFields.filter(item => item.key !== 'z_score')
          this.scoreInfoByAssignmentGroupFields = this.scoreInfoByAssignmentGroupFields.filter(item => item.key !== 'z_score')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }

      this.loading = false
    },
    downloadSubmissionFile (assignmentId, submission, originalFilename) {
      let data =
        {
          'assignment_id': assignmentId,
          'submission': submission
        }
      let url = '/api/submission-files/download'
      this.downloadFile(url, data, originalFilename, this.$noty)
    },
    closeAssignmentSubmissionFeedbackModal () {
      this.$nextTick(() => {
        this.$bvModal.hide('modal-assignment-submission-feedback')
      })
    },
    async getAssignmentFileInfo (assignmentId) {
      try {
        const { data } = await axios.get(`/api/assignment-files/assignment-file-info-by-student/${assignmentId}`)
        this.assignmentFileInfo = data.assignment_file_info
        if (!this.assignmentFileInfo) {
          this.$noty.info('You can\'t have any feedback if you haven\'t submitted a file!')
          return false
        }
        console.log(this.assignmentFileInfo)

        this.$root.$emit('bv::show::modal', 'modal-assignment-submission-feedback')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          this.$nextTick(() => {
            this.$bvModal.hide('modal-assignment-submission-feedback')
          })
          return false
        }
      } catch (error) {
        if (error.message.includes('status code 413')) {
          error.message = 'The maximum size allowed is 10MB.'
        }
        this.$noty.error(error.message)
      }
      // get the text comments
      // get the score
      // the the temporary url of the feedback
      // get the download url of your current submission
    },
    async handleOk (bvModalEvt) {
      // Prevent modal from closing
      bvModalEvt.preventDefault()
      // Trigger submit handler
      if (this.uploading) {
        this.$noty.info('Please be patient while the file is uploading.')
        return false
      }
      this.uploading = true
      try {
        await this.submitUploadFile('assignment', this.form, this.$noty, this.$nextTick, this.$bvModal, '/api/submission-files')
      } catch (error) {
      }
      this.uploading = false
    },

    resetModalForms () {
      // alert('reset modal')
    },
    getAssignmentSummaryView (assignment) {
      if (assignment.textbook_url) {
        window.open(assignment.textbook_url)
        return false
      }
      if (Boolean(assignment.lms) && !assignment.lms_only_entry && !assignment.lti_assignments_and_grades_url) {
        this.$noty.info('This assignment is not yet linked to your LMS.  Please ask your instructor to link it to ADAPT.')
        return false
      }
      if (assignment.source === 'x') {
        this.$noty.info('This assignment has no questions to view because it is an external assignment.  Please contact your instructor for more information.')
        return false
      }
      this.$router.push(`/students/assignments/${assignment.id}/summary`)
    },
    metaInfo () {
      return { title: this.$t('home') }
    }
  }
}
</script>
