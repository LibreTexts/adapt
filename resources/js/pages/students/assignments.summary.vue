<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-file-upload'"/>
    <RedirectToClickerModal
      :key="`redirect-to-clicker-modal-${clickerAssignmentId}-${clickerQuestionId}-${redirectToClickerModalKey}`"
      :assignment-id="clickerAssignmentId"
      :question-id="clickerQuestionId"
      @resetClickerAssignmentIdClickerQuestionId="resetClickerAssignmentIdClickerQuestionId()"
    />
    <b-modal id="modal-last-open-ended-submission"
             title="Last Open-Ended Submission"
             hide-footer
    >
      Open-ended questions are questions which will require a grader. Some examples are file uploads, text
      based responses, audio uploads, and submissions through Forge.
    </b-modal>
    <b-modal id="modal-last-auto-graded-submission"
             title="Last Auto-graded Submission"
             hide-footer
    >
      Auto-graded questions are questions which can be graded automatically by ADAPT.
      Some examples are multiple choice, true false, numeric based and matching.
    </b-modal>
    <b-modal id="modal-number-of-allowed-attempts"
             title="Number of Allowed Attempts"
             hide-footer
    >
      The number of allowed attempts tells you how many times you can re-submit each question before the
      due date.
    </b-modal>
    <b-modal id="modal-per-attempt-penalty"
             title="Per Attempt Penalty"
             hide-footer
    >
      After your first attempt, a penalty of {{ numberOfAllowedAttemptsPenalty }}% will be applied per
      attempt.
      As an example, if a question is worth 10 points then on the second attempt,
      you will
      receive {{ 10 * (1 - parseFloat(numberOfAllowedAttemptsPenalty) / 100) }} points.
    </b-modal>
    <b-modal
      id="modal-completed-assignment"
      ref="modalThumbsUp"
      hide-footer
      title="Congratulations!"
    >
      <b-alert variant="info" show>
        <span style="font-size: large">
          All responses successfully submitted.
        </span>
      </b-alert>
    </b-modal>
    <b-modal
      id="modal-submission-accepted"
      ref="modalThumbsUp"
      hide-footer
      title="Submission Accepted"
    >
      <b-alert variant="info" show>
        <span style="font-size: large">
          {{ successMessage }}
        </span>
      </b-alert>
    </b-modal>
    <b-modal
      id="modal-thumbs-down"
      ref="modalThumbsUp"
      hide-footer
      size="lg"
      title="Submission Not Accepted"
    >
      <b-alert variant="danger" :show="true">
        <span class="font-weight-bold" style="font-size: large" v-html="errorMessage"/>
      </b-alert>
    </b-modal>
    <b-modal id="modal-confirm-set-page"
             ref="confirmSetPage"
             title="Confirm Set Page"
    >
      <p>
        You already have submitted a file for this question. If you set the page again, your submission file
        will be updated with the associated page from your compiled PDF.
      </p>
      <p>Would you like to update your submission with a page from your compiled PDF?</p>
      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="$bvModal.hide('modal-confirm-set-page')">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary" @click="handleSetPageAsSubmission">
          Yes, set the page!
        </b-button>
      </template>
    </b-modal>

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
        <PageTitle :title="name"
                   :assessment-type="assessmentType"
        />
        <b-container>
          <div v-if="!showNoAccessMessage && (assessmentType !== 'clicker' || solutionsReleased)">
            <b-row align-h="end">
              <b-button class="ml-3 mb-2" variant="primary" size="sm" @click="getStudentView(assignmentId)">
                View Assessments
              </b-button>
            </b-row>
            <hr>
          </div>
          <div v-show="isInstructorLoggedInAsStudent" class="mb-2">
            <LoggedInAsStudent :student-name="user.first_name + ' ' + user.last_name"/>
          </div>
          <div v-if="assessmentType === 'clicker' && !solutionsReleased">
            Please wait for your instructor to open up this assignment.
          </div>
          <div v-show="showNoAccessMessage">
            <b-alert variant="danger" show>
              {{ showNoAccessMessage }}
            </b-alert>
          </div>
          <b-card v-show="!showNoAccessMessage && assessmentType !== 'clicker'" header="default"
                  header-html="<h2 class=&quot;h5&quot;>Important Information</h2>"
          >
            <b-card-text>
              <ul style="list-style: none;">
                <li v-if="public_description" class="mb-2">
                  <span class="font-weight-bold">Description: </span> <span v-html="public_description"/>
                </li>
                <li v-if="instructions.length" class="mb-2">
                  <span v-html="instructions"/>
                </li>
                <li class="mb-2">
                  <span class="font-weight-bold">Number of Points: </span>
                  <span>This assignment is worth a total of {{
                      totalPoints
                    }} point{{ totalPoints !== 1 ? 's' : '' }}.</span>
                </li>
                <li class="mb-2">
                  <span class="font-weight-bold">Number of Questions: </span>
                  <span>This assignment has {{
                      items.length
                    }} question{{ items.length !== 1 ? 's' : '' }}.</span>
                </li>
                <li v-show="assessmentType === 'real time'" class="mb-2">
                  <span class="font-weight-bold">Number of Allowed Attempts: </span>
                  {{ numberOfAllowedAttempts }}
                  <QuestionCircleTooltipModal :aria-label="'Number of Allowed Attempts'"
                                              :modal-id="'modal-number-of-allowed-attempts'"
                  />
                </li>
                <li
                  v-show="assessmentType === 'real time' && numberOfAllowedAttempts !== '1' && numberOfAllowedAttemptsPenalty>0"
                  class="mb-2"
                >
                  <span class="font-weight-bold">Per Attempt Penalty: </span>
                  {{ numberOfAllowedAttemptsPenalty }}%
                  <QuestionCircleTooltipModal :aria-label="'Per Attempt Penalty'"
                                              :modal-id="'modal-per-attempt-penalty'"
                  />
                </li>
                <li class="mb-2">
                  <span class="font-weight-bold">Due Date: </span>
                  <span v-if="hasDifferentForgeDueDates">
    This assignment has questions with multiple distinct due dates. Please review them in the table below.
  </span>
                  <span v-else>
    This assignment is due {{ formattedDue }}.
    <span v-if="extension">(You have an extension until {{ extension }}).</span>
  </span>
                </li>
                <li class="mb-2">
                  <span class="font-weight-bold">Late Policy: </span>
                  <span> {{ formattedLatePolicy }}</span>
                </li>
                <li v-if="bothFileUploadMode && hasAtLeastOneFileUpload" class="mb-2">
                  <span class="font-weight-bold">
                    Open-ended submissions:</span>
                  <span>
                    Upload a single compiled PDF of the questions and assign pages and/or submit individual submissions on each page.
                  </span>
                </li>
              </ul>
            </b-card-text>
          </b-card>

          <b-card v-show="items.length && (compiledPdf || (bothFileUploadMode && hasAtLeastOneFileUpload))"
                  class="mt-3 mb-3"
                  header="default"
                  header-html="<h2 class=&quot;h5&quot;>Upload Compiled PDF Submission</h2>"
          >
            Upload your compiled PDF and then set each submission.
            <file-upload
              ref="upload"
              v-model="files"
              accept="application/pdf"
              put-action="/put.method"
              @input-file="inputFile"
              @input-filter="inputFilter"
              @click="submissionFileForm.errors.clear('submission')"
            />
            <input type="hidden" class="form-control is-invalid">
            <div v-if="submissionFileForm.errors.has('submission')"
                 class="help-block invalid-feedback"
            >
              {{ submissionFileForm.errors.errors.submission[0] }}
            </div>
            <div class="upload mt-3">
              <ul v-if="files.length && (preSignedURL !== '')">
                <li v-for="file in files" :key="file.id">
                  <span :class="file.success ? 'text-success font-weight-bold' : ''">{{
                      file.name
                    }}</span> -
                  <span>{{ formatFileSize(file.size) }} </span>
                  <span v-if="file.size > 10000000">Note: large files may take up to a minute to process.</span>
                  <span v-if="file.error" class="text-danger">Error: {{ file.error }}</span>
                  <span v-else-if="file.active" class="ml-2">
                    <b-spinner small type="grow"/>
                    Uploading File...
                  </span>
                  <span v-if="processingFile">
                    <b-spinner small type="grow"/>
                    Processing file...
                  </span>
                  <b-button v-if="!processingFile && (preSignedURL !== '') && (!$refs.upload || !$refs.upload.active)"
                            variant="success"
                            size="sm"
                            style="vertical-align: top"
                            @click.prevent="$refs.upload.active = true"
                  >
                    Start Upload
                  </b-button>
                </li>
              </ul>
            </div>
            <div v-show="fullPdfUrl" class="mb-2">
              <b-embed
                :key="fullPdfUrlKey"
                type="iframe"
                aspect="16by9"
                aria-label="Compiled PDF"
                :src="getFullPdfUrlAtPage(fullPdfUrl, questionSubmissionPageForm.page)"
                allowfullscreen
              />
            </div>
          </b-card>
          <b-card v-show="items.length && assessmentType !== 'clicker'"
                  id="questions_card"
                  class="mt-3 mb-3"
                  header="default"
                  header-html="<h2 class=&quot;h5&quot;>Questions</h2>"
          >
            <div class="vld-parent" style="min-height: 100px;">
              <loading :active.sync="questionsLoading"
                       :can-cancel="false"
                       :is-full-page="false"
                       :width="64"
                       :height="64"
                       color="#007BFF"
                       background="#FFFFFF"
              />
              <div v-show="!questionsLoading">
                <b-alert variant="success" :show="completedAllAssignmentQuestions">
                  <span class="font-weight-bold">You have completed all assessments on this assignment!</span>
                </b-alert>
                <div v-if="!setAllPages && fullPdfUrl">
                  <b-alert show variant="info">
                    Please remember to set the Initial Pages for each of the questions below.
                  </b-alert>
                </div>
                <b-table
                  id="summary_of_questions_and_submissions"
                  aria-label="Summary of questions and submissions"
                  striped
                  hover
                  table-class="'table-layout-fixed'"
                  :no-border-collapse="true"
                  :fields="shownFields"
                  :items="items"
                >
                  <template #cell(question_number)="data">
                    <a href="" @click.stop.prevent="viewQuestion(data.item.question_id)">&nbsp;
                      {{ data.item.question_number }}. {{ data.item.title }}</a>
                  </template>
                  <template v-slot:head(last_question_submission)="data">
                    Last Auto-Graded Submission
                    <QuestionCircleTooltipModal :aria-label="'Last Auto-Graded Submission'"
                                                :modal-id="'modal-last-auto-graded-submission'"
                    />
                  </template>
                  <template #cell(last_question_submission)="data">
          <span
            :class="{ 'table-text-danger': data.item.questionSubmissionRequired && !data.item.showThumbsUpForQuestionSubmission }"
          >
            {{ data.item.last_question_submission }}
          </span>
                    <font-awesome-icon v-show="data.item.showThumbsUpForQuestionSubmission" class="text-success"
                                       :icon="checkIcon"
                    />
                  </template>
                  <template #cell(last_open_ended_submission)="data">
          <span
            :class="{ 'table-text-danger': data.item.openEndedSubmissionRequired && !data.item.showThumbsUpForOpenEndedSubmission }"
          >
            <span v-if="!data.item.showThumbsUpForOpenEndedSubmission">{{
                data.item.last_open_ended_submission
              }}</span>
            <span v-if="data.item.showThumbsUpForOpenEndedSubmission">
              <span v-if="data.item.is_forge" class="d-flex align-items-center">
              {{ data.item.last_open_ended_submission }}
                 <font-awesome-icon v-show="data.item.showThumbsUpForOpenEndedSubmission"
                                    class="text-success ml-1"
                                    :icon="checkIcon"
                 />
              </span>
              <span v-else>
                <a :href="data.item.submission_file_url" target="_blank">{{
                    data.item.last_open_ended_submission
                  }}</a><font-awesome-icon v-show="data.item.showThumbsUpForOpenEndedSubmission"
                                           class="text-success ml-1"
                                           :icon="checkIcon"
              />
              </span>
            </span>
          </span>
                  </template>
                  <template v-slot:head(last_open_ended_submission)="data">
                    Last Open-Ended Submission
                    <QuestionCircleTooltipModal :aria-label="'Last Open-Ended Submission'"
                                                :modal-id="'modal-last-open-ended-submission'"
                    />
                  </template>
                  <template #cell(solution_file_url)="data">
                    <SolutionFileHtml :questions="items"
                                      :modal-id="`solution-${data.item.question_number}`"
                                      :current-page="data.item.question_number"
                                      assignment-name="Question"
                    />
                  </template>
                  <template #cell(forge_due_dates)="data">
                    <ForgeDueDates
                      v-if="data.item.is_forge"
                      class="mr-2"
                      :question-title="data.item.title"
                      :assignment-id="+assignmentId"
                      :key="`forge-due-dates-${data.item.question_id}`"
                      :parent-question-id="data.item.forge_source_id || data.item.question_id"
                      :current-draft-question-id="data.item.question_id"
                      :color-coded-due-date="true"
                      @due-date-loaded="checkForgeDueDate"
                    />
                    <span v-else :class="assignmentDueDateClass()">
  {{ formattedDue }}
</span>
                  </template>
                  <template #cell(page)="data">
                    <div v-if="data.item.isOpenEndedFileSubmission">
                      <b-input-group>
                        <div class="d-flex">
                          <b-form-input :id="`set_page_for_question_${data.item.question_number}`"
                                        v-model="data.item.page"
                                        type="text"
                                        style="width: 60px"
                                        placeholder=""
                                        :class="{ 'is-invalid': data.item.question_id === questionSubmissionPageForm.questionId && questionSubmissionPageForm.errors.has('page') }"
                                        @keydown="questionSubmissionPageForm.errors.clear('page')"
                          />
                          <b-button variant="primary"
                                    size="sm"
                                    class="ml-1"
                                    :disabled="!fullPdfUrl"
                                    @click="confirmSetPageAsSubmission(data.item.question_number, data.item.question_id, data.item.page)"
                          >
                            <label :for="`set_page_for_question_${data.item.question_number}`" style="margin-bottom:0">Set
                              Page</label>
                          </b-button>
                          <has-error v-show="data.item.question_id === questionSubmissionPageForm.questionId"
                                     :form="questionSubmissionPageForm" field="page"
                          />
                        </div>
                      </b-input-group>
                    </div>
                    <div v-else>
                      N/A
                    </div>
                  </template>
                </b-table>
              </div>
            </div>
          </b-card>

          <b-card v-if="canViewAssignmentStatistics && !questionsLoading" class="mb-5" header="default"
                  header-html="<h2 class=&quot;h5&quot;>Statistics</h2>"
          >
            <AssignmentStatistics/>
          </b-card>
        </b-container>
      </div>
    </div>
  </div>
</template>

<script>

import { mapGetters } from 'vuex'
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import AssignmentStatistics from '~/components/AssignmentStatistics'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck } from '@fortawesome/free-solid-svg-icons'
import Vue from 'vue'
import Form from 'vform'
import { getFullPdfUrlAtPage } from '~/helpers/DownloadFiles'
import LoggedInAsStudent from '~/components/LoggedInAsStudent'
import AllFormErrors from '~/components/AllFormErrors'
import SolutionFileHtml from '~/components/SolutionFileHtml'
import QuestionCircleTooltipModal from '~/components/QuestionCircleTooltipModal'
import { makeFileUploaderAccessible } from '~/helpers/accessibility/makeFileUploaderAccessible'
import RedirectToClickerModal from '../../components/RedirectToClickerModal.vue'
import {
  initClickerAssignmentsForEnrolledAndOpenCourses,
  resetClickerAssignmentIdClickerQuestionId
} from '../../helpers/clicker'
import ForgeViewer from '~/components/viewers/ForgeViewer.vue'
import ForgeDueDates from '../../components/ForgeDueDates.vue'

const VueUploadComponent = require('vue-upload-component')
Vue.component('file-upload', VueUploadComponent)

export default {
  components: {
    ForgeDueDates,
    ForgeViewer,
    RedirectToClickerModal,
    AssignmentStatistics,
    Loading,
    FontAwesomeIcon,
    LoggedInAsStudent,
    AllFormErrors,
    SolutionFileHtml,
    QuestionCircleTooltipModal
  },
  metaInfo () {
    return { title: 'Assignment - Summary' }
  },
  middleware: 'auth',
  data: () => ({
    finalSubmissionDeadline: '',
    questionsLoading: true,
    hasDifferentForgeDueDates: false,
    forgeDueDatesChecked: 0,
    forgeQuestionCount: 0,
    redirectToClickerModalKey: 0,
    clickerAssignmentId: 0,
    clickerQuestionId: 0,
    showNoAccessMessage: '',
    solutionsReleased: false,
    assignmentId: 0,
    setAllPages: false,
    cacheKey: 0,
    numberOfAllowedAttempts: '',
    numberOfAllowedAttemptsPenalty: '',
    hasAtLeastOneFileUpload: false,
    allFormErrors: [],
    extension: null,
    public_description: null,
    isInstructorLoggedInAsStudent: false,
    bothFileUploadMode: false,
    compiledPdf: false,
    totalPoints: '',
    completedAllAssignmentQuestions: false,
    successMessage: '',
    errorMessage: '',
    openEndedSubmissionQuestionOptions: [],
    question: {},
    fullPdfUrl: '',
    fullPdfUrlKey: 0,
    questionSubmissionPageForm: new Form({
      question_number: '',
      questionId: 0,
      page: ''
    }),
    processingFile: false,
    preSignedURL: '',
    files: [],
    submissionFileForm: new Form({
      questionFile: null,
      assignmentId: null,
      questionId: null
    }),
    checkIcon: faCheck,
    fields: [],
    items: [],
    clickerPollingSetInterval: null,
    assessmentUrlType: '',
    assessmentType: '',
    isLoading: true,
    name: '',
    instructions: '',
    formattedLatePolicy: '',
    formattedDue: '',
    canViewAssignmentStatistics: false,
    assignmentInfo: {}
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    shownFields () {
      return this.fields.filter(field => {
        if (field.key === 'forge_due_dates') {
          if (this.questionsLoading) return true
          return this.hasDifferentForgeDueDates
        }
        return field.shown
      })
    }
  },
  created () {
    this.getFullPdfUrlAtPage = getFullPdfUrlAtPage
  },
  async mounted () {
    this.assignmentId = this.$route.params.assignmentId
    if (await this.getAssignmentSummary() === false) {
      this.isLoading = false
      return false
    }

    await this.$nextTick(() => {
      this.getSelectedQuestions(this.assignmentId)
    })

    this.isLoading = false
    this.$nextTick(function () {
      this.resizeHandler()
      makeFileUploaderAccessible()
    })
    window.addEventListener('resize', this.resizeHandler)
    await this.initClickerAssignmentsForEnrolledAndOpenCourses()
  },
  beforeDestroy () {
    window.removeEventListener('resize', this.resizeHandler)
    try {
      if (this.centrifuge) {
        this.centrifuge.disconnect()
      }
    } catch (error) {
      // won't be a function for all the other ones that haven't been defined on the page
    }
  },
  methods: {
    resetClickerAssignmentIdClickerQuestionId,
    initClickerAssignmentsForEnrolledAndOpenCourses,
    formatDate (dateString) {
      if (!dateString) return ''
      const d = new Date(dateString.replace(' at ', ' '))
      const month = d.getMonth() + 1
      const day = d.getDate()
      const year = String(d.getFullYear()).slice(-2)
      let hours = d.getHours()
      const minutes = String(d.getMinutes()).padStart(2, '0')
      const ampm = hours >= 12 ? 'pm' : 'am'
      hours = hours % 12 || 12
      return `${month}/${day}/${year} ${hours}:${minutes}${ampm}`
    },
    parseFormattedDate (dateStr) {
      if (!dateStr) return null
      const parts = dateStr.match(/(\d+)\/(\d+)\/(\d+)\s+(\d+):(\d+)(am|pm)/i)
      if (!parts) return null
      let [, month, day, year, hours, minutes, ampm] = parts
      hours = parseInt(hours)
      if (ampm.toLowerCase() === 'pm' && hours !== 12) hours += 12
      if (ampm.toLowerCase() === 'am' && hours === 12) hours = 0
      return new Date(2000 + parseInt(year), parseInt(month) - 1, parseInt(day), hours, parseInt(minutes))
    },
    assignmentDueDateClass () {
      const due = this.parseFormattedDate(this.formattedDue)
      if (!due) return 'dark-red'
      const now = new Date()
      if (now <= due) return 'text-success'
      if (this.finalSubmissionDeadline) {
        const finalDeadline = this.parseFormattedDate(this.finalSubmissionDeadline)
        if (finalDeadline && now <= finalDeadline) return 'text-warning'
      }
      return 'dark-red'
    },
    checkForgeDueDate (forgeDueDate) {
      this.forgeDueDatesChecked++
      const normalize = (s) => s ? s.replace(/\s+(am|pm)/i, '$1').trim() : ''
      const normalizedForgeDue = normalize(this.formatDate(forgeDueDate))
      const normalizedAssignmentDue = normalize(this.formattedDue)
      if (normalizedForgeDue !== normalizedAssignmentDue) {
        this.hasDifferentForgeDueDates = true
      }
      if (this.forgeDueDatesChecked >= this.forgeQuestionCount) {
        this.questionsLoading = false
      }
    },
    async getDiscussItQuestions (assignmentId) {
      try {
        const { data } = await axios.get(`/api/assignments/${assignmentId}/questions/discuss-it`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        } else {
          return data.discuss_it_question_info
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      return false
    },
    getThumbsUpWidth () {
      return this.inIFrame ? 150 : 275
    },
    resizeHandler () {
      let table = document.getElementById('summary_of_questions_and_submissions')
      if (table && this.zoomGreaterThan(1.2)) {
        document.getElementById('questions_card').style.width = (parseInt(table.offsetWidth) + 100).toString() + 'px'
      } else {
        document.getElementById('questions_card').style.width = ''
      }
    },
    confirmSetPageAsSubmission (questionNumber, questionId, page) {
      this.questionSubmissionPageForm.questionId = questionId
      this.questionSubmissionPageForm.page = page
      this.questionSubmissionPageForm.question_number = questionNumber
      let question = this.items.find(question => question.question_id === questionId)
      question.submission_file_exists
        ? this.$bvModal.show('modal-confirm-set-page')
        : this.handleSetPageAsSubmission()
    },
    async handleSetPageAsSubmission () {
      try {
        const { data } = await this.questionSubmissionPageForm.patch(`/api/submission-files/${this.assignmentId}/${this.questionSubmissionPageForm.questionId}/page`)
        this.$bvModal.hide('modal-confirm-set-page')
        if (data.type === 'error') {
          this.errorMessage = data.message
          this.$bvModal.show('modal-thumbs-down')
          return false
        }
        let openEndedSubmission
        openEndedSubmission = this.items.find(question => question.question_id === this.questionSubmissionPageForm.questionId)
        openEndedSubmission.submission_file_exists = true
        openEndedSubmission.submission_file_url = data.submission_file_url
        openEndedSubmission.last_open_ended_submission = data.date_submitted
        if (openEndedSubmission.hasOwnProperty('total_score')) {
          openEndedSubmission.total_score = data.total_score
        }
        openEndedSubmission.showThumbsUpForOpenEndedSubmission = true
        this.successMessage = data.message
        this.completedAllAssignmentQuestions = data.completed_all_assignment_questions
        this.cacheKey++
        if (this.completedAllAssignmentQuestions) {
          this.$bvModal.show('modal-completed-assignment')
        }
        this.$bvModal.show('modal-submission-accepted')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.errorMessage = error.message
          this.$bvModal.hide('modal-confirm-set-page')
          this.$bvModal.show('modal-thumbs-down')
        }
      }
    },
    formatFileSize (size) {
      let sizes = [' Bytes', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB']
      for (let i = 1; i < sizes.length; i++) {
        if (size < Math.pow(1024, i)) return (Math.round((size / Math.pow(1024, i - 1)) * 100) / 100) + sizes[i - 1]
      }
      return size
    },
    inputFile (newFile, oldFile) {
      if (newFile && oldFile && !newFile.active && oldFile.active) {
        if (newFile.xhr) {
          console.log('status', newFile.xhr.status)
          if (newFile.xhr.status === 200) {
            if (!this.handledOK) {
              this.handledOK = true
              console.log(this.handledOK)
              this.handleOK()
            }
          } else {
            this.$noty.error('We were not able to save your file to our server.  Please try again or contact us if the problem persists.')
          }
        } else {
          this.$noty.error('We were not able to save your file to our server.  Please try again or contact us if the problem persists.')
        }
      }
    },
    async handleOK () {
      this.submissionFileForm.uploadLevel = 'assignment'
      this.submissionFileForm.s3_key = this.s3Key
      this.submissionFileForm.original_filename = this.originalFilename
      if (this.uploading) {
        this.$noty.info('Please be patient while the file is uploading.')
        return false
      }
      this.processingFile = true

      try {
        let formData = new FormData()
        formData.append('submission', this.submissionFileForm)
        formData.append('assignmentId', this.assignmentId)
        formData.append('questionId', '0')
        formData.append('type', 'submission')
        formData.append('s3_key', this.submissionFileForm.s3_key)
        formData.append('original_filename', this.submissionFileForm.original_filename)
        formData.append('uploadLevel', this.submissionFileForm.uploadLevel)
        formData.append('_method', 'put')

        const { data } = await axios.post('/api/submission-files', formData)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.fullPdfUrl = data.full_pdf_url
          this.fields.find(field => field.key === 'page').shown = true
          this.setAllPages = false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processingFile = false
      this.files = []
    },
    handleCancel () {
      this.$refs.upload.active = false
      this.files = []
      this.processingFile = false
    },
    async inputFilter (newFile, oldFile, prevent) {
      this.submissionFileForm.errors.clear()
      if (newFile && !oldFile) {
        if (parseInt(newFile.size) > 20000000) {
          let message = '20 MB max allowed.  Your file is too large.  '
          if (/\.(pdf)$/i.test(newFile.name)) {
            message += 'You might want to try an online PDF compressor such as https://smallpdf.com/compress-pdf to reduce the size.'
          }
          this.submissionFileForm.errors.set('submission', message)
          this.allFormErrors = this.submissionFileForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-file-upload')
          return prevent()
        }
        let validUploadTypesMessage = `The file type must be .pdf`
        let validExtension = /\.(pdf)$/i.test(newFile.name)

        if (!validExtension) {
          this.submissionFileForm.errors.set('submission', validUploadTypesMessage)
          this.allFormErrors = this.submissionFileForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-file-upload')
          return prevent()
        } else {
          try {
            this.preSignedURL = ''
            let uploadFileData = {
              assignment_id: this.assignmentId,
              upload_file_type: 'submission',
              file_name: newFile.name
            }
            const { data } = await axios.post('/api/s3/pre-signed-url', uploadFileData)
            if (data.type === 'error') {
              this.$noty.error(data.message)
              return false
            }
            this.preSignedURL = data.preSignedURL
            newFile.putAction = this.preSignedURL
            this.s3Key = data.s3_key
            this.originalFilename = newFile.name
            this.handledOK = false
          } catch (error) {
            this.$noty.error(error.message)
            return false
          }
        }
      }

      newFile.blob = ''
      let URL = window.URL || window.webkitURL
      if (URL && URL.createObjectURL) {
        newFile.blob = URL.createObjectURL(newFile.file)
      }
    },
    viewQuestion (questionId) {
      this.$router.push({ path: `/assignments/${this.assignmentId}/questions/view/${questionId}` })
      return false
    },
    initClickerPolling () {
      let self = this
      this.submitClickerPolling(this.assignmentId)
      this.clickerPollingSetInterval = setInterval(function () {
        self.submitClickerPolling(self.assignmentId)
      }, 3000)
    },
    async getAssignmentSummary () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/summary`)
        console.log(data)
        if (data.type === 'error') {
          if (data.message === 'You are not allowed to retrieve this summary.') {
            this.showNoAccessMessage = 'You are not allowed to retrieve this summary.'
          } else {
            this.$noty.error(data.message)
          }
          return false
        }
        let assignment = data.assignment
        this.finalSubmissionDeadline = assignment.final_submission_deadline
        this.isInstructorLoggedInAsStudent = assignment.is_instructor_logged_in_as_student
        this.public_description = assignment.public_description
        this.instructions = assignment.instructions
          ? assignment.instructions.replace('<p>', '<p><span class="font-weight-bold">Instructions: </span>')
          : ''
        this.formattedLatePolicy = assignment.formatted_late_policy
        this.formattedDue = assignment.formatted_due
        this.formattedAttemptsPolicy = assignment.formatted_attempts_policy
        this.totalPoints = assignment.total_points
        this.numberOfAllowedAttempts = assignment.number_of_allowed_attempts
        this.numberOfAllowedAttemptsPenalty = assignment.number_of_allowed_attempts_penalty
        this.compiledPdf = assignment.file_upload_mode === 'compiled_pdf'
        this.bothFileUploadMode = assignment.file_upload_mode === 'both'
        this.assessmentType = assignment.assessment_type
        this.extension = assignment.extension
        this.name = assignment.name
        this.solutionsReleased = Boolean(assignment.solutions_released)
        this.canViewAssignmentStatistics = assignment.can_view_assignment_statistics
        this.fullPdfUrl = assignment.full_pdf_url
        this.fullPdfUrlKey++
        this.completedAllAssignmentQuestions = assignment.completed_all_assignment_questions
        this.fields = [
          {
            key: 'question_number',
            label: 'Question',
            thStyle: { minWidth: '200px', maxWidth: '350px' },
            tdAttr: { style: 'max-width: 350px; word-wrap: break-word' },
            isRowHeader: true,
            shown: true
          },
          {
            key: 'last_question_submission',
            shown: true
          },
          {
            key: 'last_open_ended_submission',
            shown: true
          },
          {
            key: 'forge_due_dates',
            label: 'Due Dates',
            thStyle: { minWidth: '165px' },
            shown: true
          },
          {
            key: 'page',
            label: 'Initial Page',
            shown: this.fullPdfUrl !== null,
            thStyle: { 'width': '165px' }
          },
          {
            key: 'points',
            label: 'Question Points',
            shown: assignment.show_points_per_question
          },
          {
            key: 'total_score',
            shown: true
          },
          {
            key: 'solution_file_url',
            label: 'Solution',
            shown: true
          }
        ]
      } catch (error) {
        this.$noty.error(error.message)
        this.name = 'Assignment Summary'
      }
    },
    async getSelectedQuestions (assignmentId) {
      const discussItQuestions = await this.getDiscussItQuestions(this.assignmentId)

      try {
        const { data } = await axios.get(`/api/assignments/${assignmentId}/questions/view`)
        console.log(data)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.hasAtLeastOneFileUpload = false
        for (let i = 0; i < data.questions.length; i++) {
          let question = data.questions[i]
          let lastOpenEndedSubmission = 'N/A'
          let lastSubmitted = 'N/A'
          let openEndedSubmissionRequired = false
          let showThumbsUpForOpenEndedSubmission = false
          let questionSubmissionRequired = false
          let showThumbsUpForQuestionSubmission = false
          if (question.open_ended_submission_type !== '0' || (question.qti_json && ['forge', 'forge_iteration'].includes(question.qti_json_type))) {
            openEndedSubmissionRequired = true
            const questionSubmitted = question.date_submitted !== 'N/A' && typeof question.date_submitted !== 'undefined'
            lastOpenEndedSubmission = !questionSubmitted
              ? 'Nothing submitted yet.'
              : question.date_submitted
            showThumbsUpForOpenEndedSubmission = questionSubmitted
            this.openEndedSubmissionQuestionOptions.push({ value: question.id, text: i + 1 })
          }
          if (question.open_ended_submission_type === 'file') {
            this.hasAtLeastOneFileUpload = true
          }
          if (question.technology_iframe || (question.qti_json && !['forge', 'forge_iteration'].includes(question.qti_json_type))) {
            questionSubmissionRequired = true
            lastSubmitted = question.last_submitted === 'N/A' && typeof question.last_submitted !== 'undefined'
              ? 'Nothing submitted yet.'
              : question.last_submitted
            showThumbsUpForQuestionSubmission = question.last_submitted !== 'N/A'
          }
          console.log(question.technology_iframe)
          console.log(question.technology === 'webwork')
          console.log(this.solutionsReleased)
          let solutionHtml
          if (question.solution_type === 'html') {
            solutionHtml = question.solution_html
            if (!solutionHtml && question.answer_html) {
              solutionHtml = question.answer_html
            }
          }
          let questionInfo = {
            question_id: question.id,
            question_number: i + 1,
            is_forge: question.qti_json && ['forge', 'forge_iteration'].includes(question.qti_json_type),
            forge_source_id: question.forge_source_id,
            last_question_submission: lastSubmitted,
            questionSubmissionRequired: questionSubmissionRequired,
            technology_iframe_src: question.technology_iframe,
            technology_iframe: question.technology_iframe,
            render_webwork_solution: question.render_webwork_solution,
            showThumbsUpForQuestionSubmission: showThumbsUpForQuestionSubmission,
            openEndedSubmissionRequired: openEndedSubmissionRequired,
            last_open_ended_submission: lastOpenEndedSubmission,
            isOpenEndedFileSubmission: question.open_ended_submission_type === 'file',
            showThumbsUpForOpenEndedSubmission: showThumbsUpForOpenEndedSubmission,
            page: question.submission_file_page ? question.submission_file_page : null,
            submission_file_exists: question.submission_file_exists,
            submission_file_url: question.submission_file_url ? question.submission_file_url : null,
            solution_type: question.solution_type,
            title: question.title,
            solution_html: solutionHtml,
            solution_file_url: question.solution_file_url ? question.solution_file_url : null,
            solution: question.solution ? question.solution : null,
            points: question.points ? question.points : 'N/A',
            total_score: question.hasOwnProperty('total_score') ? question.total_score : 'N/A',
            solution_file: question.solution_file_url
          }
          const discussItQuestion = discussItQuestions.find(item => +item.id === +questionInfo.question_id)
          if (discussItQuestion) {
            questionInfo.last_open_ended_submission = discussItQuestion.last_submitted
            questionInfo.total_score = discussItQuestion.total_score
          }
          this.items.push(questionInfo)
        }

        this.forgeQuestionCount = this.items.filter(item => item.is_forge).length
        if (this.forgeQuestionCount === 0) {
          this.questionsLoading = false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getStudentView (assignmentId) {
      this.$router.push(`/assignments/${assignmentId}/questions/view`)
    },
    metaInfo () {
      return { title: this.$t('home') }
    }
  }
}
</script>
<style>
.table-layout-fixed {
  table-layout: fixed;
}
</style>
