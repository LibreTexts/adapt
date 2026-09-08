<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-errors-question-scores'"/>
    <b-modal id="modal-value-range"
             title="Value Range"
    >
      <p>Filter all students who have submission values between:</p>
      <b-form-group
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="min_score"
        label="Minimum*"
      >
        <b-form-row>
          <b-form-input
            id="min_score"
            v-model="minValue"
            style="width:100px"
            required
            type="text"
          />
        </b-form-row>
      </b-form-group>
      <b-form-group
        label-cols-sm="4"
        label-cols-lg="3"
        label-for="max_score"
        label="Maximum*"
      >
        <b-form-row>
          <b-form-input
            id="min_score"
            v-model="maxValue"
            style="width:100px"
            required
            type="text"
          />
        </b-form-row>
      </b-form-group>
      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="minValue='';maxValue='';$bvModal.hide('modal-value-range')">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary"
                  @click="updateRangeOfValues()"
        >
          Update
        </b-button>
      </template>
    </b-modal>
    <b-modal
      v-if="questions.length"
      id="modal-confirm-update-scores"
      ref="confirmUpdateScoresModal"
      title="Confirm Update Scores"
      size="lg"
    >
      <p>
        <span class="font-weight-bold">Students:</span> <span>{{ selectedStudentText }}</span><br>
        <span class="font-weight-bold">Submission:</span> <span v-show="!['submit_molecule','marker'].includes(question.qti_json_type)"><span>{{
          selectedSubmissionText
        }}</span><br></span>
        <SketcherViewer
          v-if="['submit_molecule','marker'].includes(question.qti_json_type)"
          ref="sketcherViewer"
          :key="`sketcher--1`"
          :qti-json="JSON.parse(question.qti_json)"
          :student-response="JSON.stringify(JSON.parse(items[0].submission).structure)"
          :read-only="true"
          :configuration="question.qti_json_type === 'marker' ? 'marker-only' : 'default'"
        />
        <span class="font-weight-bold">Score:</span> <span>{{ selectedScoreText }}</span><br>
        <span class="font-weight-bold">Apply To:</span> <span>
          {{
          parseInt(questionScoreForm.apply_to) === 1
            ? 'Submission scores in the filtered group'
            : 'Submission scores that are not in the filtered group'
        }}
        </span><br>
      </p>
      <p>
        Please confirm that you would like to change the student scores for <span class="font-weight-bold"
      >Question {{ selectedQuestionText }}</span> which match the above criteria to
        a new score of <span
        class="font-weight-bold"
      >
          {{ questionScoreForm.new_score }}</span>.
      </p>

      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="$bvModal.hide('modal-confirm-update-scores')">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary"
                  @click="handleUpdateScores()"
        >
          Update scores
        </b-button>
      </template>
    </b-modal>

    <b-modal
      v-if="questions.length"
      id="modal-update-scores-with-over-total-points"
      ref="invalidScoresModal"
      title="Invalid Scores"
      size="lg"
    >
      <p>
        <b-alert variant="danger" :show="true">
          <span class="font-weight-bold">By updating the score to {{
              questionScoreForm.new_score
            }}, {{ numOverMax }} students will
            be given a score over {{ questions[currentQuestionPage - 1].points }} points, which is the total number
            of points allotted to this question. Please reduce the score provided.
          </span>
        </b-alert>
      </p>
      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="$bvModal.hide('modal-update-scores-with-over-total-points')">
          Got it!
        </b-button>
      </template>
    </b-modal>

    <b-modal id="modal-submission-file"
             ref="submissionFileModal"
             title="Open-ended submission"
             size="lg"
    >
      <div v-show="submissionUrl" class="mb-2">
        <b-embed
          type="iframe"
          aspect="16by9"
          :src="submissionUrl"
          allowfullscreen
        />
      </div>
      <div v-show="submissionText" v-html="submissionText"/>
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
        <PageTitle :title="`Regrader For ${assignmentName}`"/>
        <div v-if="questions.length">
          <b-container>
            <p>
              Using the filters below, you can perform a mass update
              on the scores for any of the questions.
              This can be particularly useful if you would like to give everyone full credit or no credit on a
              particular question.
            <ConsultInsight :url="'https://commons.libretexts.org/insight/assignment-regrader'"/>
            </p>
            <hr>
            <b-row>
              <span class="font-weight-bold mr-2">Title: </span>
              <a href="" @click.stop.prevent="viewQuestion(questions[currentQuestionPage-1].question_id)">
                {{ questions[currentQuestionPage - 1].title }}
              </a>
            </b-row>
            <b-row>
              <span class="font-weight-bold mr-2">ADAPT ID: </span>
              {{ questions[currentQuestionPage - 1].assignment_id_question_id }} <span class="text-info ml-1">
                <a
                  href=""
                  class="pr-1"
                  :aria-label="`Copy ADAPT ID for ${questions[currentQuestionPage - 1].title}`"
                  @click.prevent="doCopy(questions[currentQuestionPage-1].assignment_id_question_id)"
                >
                  <font-awesome-icon :icon="copyIcon"/>
                </a>
              </span>
            </b-row>
            <b-row>
              <span class="font-weight-bold mr-2">Points: </span> {{ questions[currentQuestionPage - 1].points }}
            </b-row>
            <b-row class="mb-3">
              <span class="font-weight-bold mr-2">Submission Type: </span>
              <span v-show="!hasAutoGradedAndOpended">{{ questionScoreForm.type }}</span>
              <toggle-button
                v-show="hasAutoGradedAndOpended"
                class="mt-1"
                :width="120"
                :value="questionScoreForm.type === 'Auto-graded'"
                :sync="true"
                :font-size="14"
                :margin="4"
                :color="toggleColors"
                :labels="{checked: 'Auto-graded', unchecked: 'Open-ended'}"
                @change="toggleSubmissionType()"
              />
            </b-row>
          </b-container>
          <RequiredText/>
          <b-form-group
            id="student"
            label-cols-sm="3"
            label-cols-lg="2"
            label="Student*"
            label-for="student_id"
          >
            <b-form-row>
              <b-col lg="6">
                <b-form-select id="student_id"
                               v-model="studentId"
                               required
                               :options="studentsOptions"
                               @change="updateStudentsFilter($event)"
                />
              </b-col>
            </b-form-row>
          </b-form-group>
          <b-form-group
            label-cols-sm="3"
            label-cols-lg="2"
            label="Submission*"
            label-for="submission"
          >
            <b-form-row>
              <b-col lg="4">
                <b-form-select id="submission"
                               v-model="submission"
                               required
                               :options="submissionsOptions"
                               :disabled="openEndedView"
                               @change="updateSubmissionsFilter($event)"
                />
              </b-col>
            </b-form-row>
          </b-form-group>
          <b-form-group
            label-cols-sm="3"
            label-cols-lg="2"
            label="Score*"
            label-for="score"
          >
            <b-form-row>
              <b-col lg="3">
                <b-form-select id="score"
                               v-model="score"
                               required
                               :options="scoresOptions"
                               @change="updateScoresFilter($event)"
                />
              </b-col>
            </b-form-row>
          </b-form-group>
          <b-form-group
            id="question"
            label-cols-sm="3"
            label-cols-lg="2"
            label="Question*"
            label-for="current_question_page"
          >
            <b-form-row>
              <b-col lg="3">
                <b-form-select id="current_question_page"
                               v-model="currentQuestionPage"
                               required
                               :options="questionsOptions"
                               @change="updateQuestionsFilter()"
                />
              </b-col>
            </b-form-row>
          </b-form-group>
          <div class="vld-parent">
            <loading :active.sync="isTableLoading"
                     :can-cancel="true"
                     :is-full-page="true"
                     :width="128"
                     :height="128"
                     color="#007BFF"
                     background="#FFFFFF"
            />
            <b-form-group
              id="apply_to"
              label-cols-sm="3"
              label-cols-lg="2"
              label="Apply To*"
              label-for="Apply To"
            >
              <b-form-row>
                <b-form-radio-group
                  v-model="questionScoreForm.apply_to"
                  required
                  stacked
                >
                  <b-form-radio name="apply_to" value="1">
                    Submission scores in the filtered group
                  </b-form-radio>

                  <b-form-radio name="apply_to" value="0">
                    Submission scores that are not in the filtered group
                  </b-form-radio>
                </b-form-radio-group>
              </b-form-row>
            </b-form-group>
            <b-form-group
              label-cols-sm="3"
              label-cols-lg="2"
              label="New Score*"
              label-for="new_score"
            >
              <b-form-row>
                <b-col lg="2">
                  <b-form-input
                    id="new_score"
                    v-model="questionScoreForm.new_score"
                    required
                    lg="7"
                    type="text"
                    :class="{ 'is-invalid': questionScoreForm.errors.has('new_score') }"
                    @keydown="questionScoreForm.errors.clear('new_score')"
                  />
                  <has-error :form="questionScoreForm" field="new_score"/>
                </b-col>
                <b-col>
                  <div class="pt-1">
                    <b-button variant="primary" size="sm" @click="initUpdateScores()">
                      Update
                    </b-button>
                    <span v-if="processing">
                      <b-spinner small type="grow"/>
                      Processing...
                    </span>
                  </div>
                </b-col>
              </b-form-row>
            </b-form-group>
            <b-table
              v-if="items.length"
              striped
              hover
              :no-border-collapse="true"
              :fields="shownFields"
              :items="items"
            >
              <template v-slot:cell(submission)="data">
                <div v-if="autoGradedView">
                  <div v-if="['submit_molecule','marker'].includes(question.qti_json_type)">
                    <SketcherViewer
                      ref="sketcherViewer"
                      :key="`sketcher-${data.item.user_id}`"
                      :qti-json="JSON.parse(question.qti_json)"
                      :student-response="JSON.stringify(JSON.parse(data.item.submission).structure)"
                      :read-only="true"
                      :configuration="question.qti_json_type === 'marker' ? 'marker-only' : 'default'"
                    />
                  </div>
                  <div v-else>
                    <div v-html="data.item.submission"/>
                  </div>
                </div>
                <span v-if="openEndedView">
                  <b-button size="sm" variant="primary" @click="openSubmissionFileModal(data.item)">View</b-button>
                </span>
              </template>
            </b-table>
            <b-alert :show="!items.length" class="info">
              <span class="font-weight-bold">Nothing matches that set of filters.</span>
            </b-alert>
          </div>
        </div>
      </div>
      <b-alert :show="!questions.length && !isLoading" variant="info">
        <span class="font-weight-bold">This assignment has no assessments.</span>
      </b-alert>
    </div>
  </div>
</template>

<script>

import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { mapGetters } from 'vuex'
import { viewQuestion, doCopy, getQuestions } from '~/helpers/Questions'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import Form from 'vform'
import AllFormErrors from '~/components/AllFormErrors'

import { ToggleButton } from 'vue-js-toggle-button'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import ConsultInsight from '../../components/ConsultInsight.vue'
import QtiJsonQuestionViewer from '../../components/QtiJsonQuestionViewer.vue'
import SketcherViewer from '../../components/viewers/SketcherViewer.vue'

export default {
  components: {
    SketcherViewer,
    QtiJsonQuestionViewer,
    ConsultInsight,
    Loading,
    FontAwesomeIcon,
    ToggleButton,
    AllFormErrors
  },
  metaInfo () {
    return { title: 'Regrader' }
  },
  middleware: 'auth',
  data: () => ({
    question: {},
    minValue: '',
    maxValue: '',
    allFormErrors: [],
    toggleColors: window.config.toggleColors,
    assignmentName: '',
    isIndividualGrading: false,
    selectedStudentText: '',
    selectedSubmissionText: '',
    selectedQuestionText: '',
    selectedScoreText: '',
    numOverMax: 0,
    submissionUrl: '',
    submissionText: '',
    autoGradedSubmissionInfoByUser: [],
    openEndedSubmissionInfoByUser: [],
    autoGradedView: false,
    openEndedView: false,
    hasAutoGradedAndOpended: false,
    questionsOptions: [],
    processing: false,
    submission: null,
    score: '',
    questionScoreForm: new Form({
      new_score: null,
      apply_to: 1,
      user_ids: [],
      type: ''
    }),
    isTableLoading: false,
    copyIcon: faCopy,
    currentQuestionPage: 1,
    studentId: null,
    studentsOptions: [],
    items: [],
    submissionsOptions: [],
    scoresOptions: [],
    questions: [],
    fields: [],
    isLoading: true,
    assignmentId: '0'
  }),
  computed:
    {
      ...mapGetters({
        user: 'auth/user'
      }),
      shownFields () {
        return this.fields.filter(field => field.shown)
      }
    },
  async mounted () {
    if (![2, 4].includes(this.user.role)) {
      await this.$router.push({ name: 'no.access' })
      return false
    }
    this.assignmentId = this.$route.params.assignmentId
    this.doCopy = doCopy
    this.viewQuestion = viewQuestion
    this.getQuestions = getQuestions
    await this.getQuestions()
    if (!this.questions.length) {
      this.isLoading = false
      return false
    }
    await this.getScoresByAssignmentAndQuestion()
    this.setStudentIds()
    if (this.submissionsOptions.length) {
      this.submission = this.submissionsOptions[0].value
      this.score = this.scoresOptions[0].value
    }
    this.question = this.questions.find(question => question.order === this.currentQuestionPage)
  },

  methods: {
    getQtiJson (question) {
      try {
        return JSON.parse(this.questions.find(item => item.question_id === question.question_id).qti_json)
      } catch {
        return ''
      }
    },
    async updateRangeOfValues () {
      this.isTableLoading = true
      await this.updateFilter(this.studentId, [this.minValue, this.maxValue], this.score)
      this.isTableLoading = false
    },
    gotoIndividualGrading () {
      this.$router.push({ name: 'assignment.grading.index', params: { assignmentId: this.assignmentId } })
    },
    getTextFromOptions (value, options) {
      return options.find(option => option.value === value).text
    },
    async confirmUpdateScores () {
      this.selectedStudentText = this.getTextFromOptions(this.studentId, this.studentsOptions)
      this.selectedSubmissionText = this.submission !== 'value_range'
        ? this.getTextFromOptions(this.submission, this.submissionsOptions)
        : `Any submission between ${this.minValue} and ${this.maxValue}.`
      this.selectedQuestionText = this.getTextFromOptions(this.currentQuestionPage, this.questionsOptions)
      this.selectedScoreText = this.getTextFromOptions(this.score, this.scoresOptions)
      this.$bvModal.show('modal-confirm-update-scores')
    },
    async initUpdateScores () {
      try {
        const { data } = await this.questionScoreForm.post(`/api/scores/over-total-points/${this.assignmentId}/${this.questionId}`)
        this.numOverMax = parseInt(data.num_over_max)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }

        this.numOverMax > 0
          ? this.$bvModal.show('modal-update-scores-with-over-total-points')
          : await this.confirmUpdateScores()
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.questionScoreForm.errors.flatten()
          this.$bvModal.show('modal-errors-question-scores')
        }
      }
    },
    async openSubmissionFileModal (item) {
      this.submissionUrl = ''
      this.submissionText = ''
      try {
        const { data } = await axios.post(`/api/submission-files/get-files-from-s3/${this.assignmentId}/${item.question_id}/${item.user_id}`, { open_ended_submission_type: item.open_ended_submission_type })
        if (data.type === 'success') {
          this.submissionUrl = data.files.submission_url
          this.submissionText = data.files.submission_text
          this.$bvModal.show('modal-submission-file')
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(`We could not retrieve the files for the student. ${error.message}`)
      }
    },
    toggleSubmissionType () {
      this.questionScoreForm.type = this.questionScoreForm.type === 'Auto-graded'
        ? 'Open-ended' : 'Auto-graded'
      this.autoGradedView = this.questionScoreForm.type === 'Auto-graded'
      this.items = this.questionScoreForm.type === 'Auto-graded'
        ? this.autoGradedSubmissionInfoByUser
        : this.openEndedSubmissionInfoByUser
      this.fields.find(field => field.key === 'submission_count').shown = this.questionScoreForm.type === 'Auto-graded'
    },
    async handleUpdateScores () {
      if (!this.processing) {
        this.$bvModal.hide('modal-confirm-update-scores')
        this.processing = true
        try {
          let controller = this.autoGradedView ? 'submissions' : 'submission-files'
          const { data } = await this.questionScoreForm.patch(`/api/${controller}/${this.assignmentId}/${this.questionId}/scores`)
          this.$noty[data.type](data.message)
          if (data.type === 'success') {
            await this.getScoresByAssignmentAndQuestion()
            this.submission = this.submissionsOptions[0].value
            this.score = this.scoresOptions[0].value
          }
        } catch (error) {
          if (!error.message.includes('status code 422')) {
            this.$noty.error(error.message)
          } else {
            this.$nextTick(() => fixInvalid())
            this.allFormErrors = this.questionScoreForm.errors.flatten()
            this.$bvModal.show('modal-errors-question-scores')
          }
        }
        this.processing = false
      } else {
        this.$noty.info('Please be patient while we process this request.')
      }
    },
    async updateQuestionsFilter () {
      this.isTableLoading = true
      this.questionId = this.questions.find(question => question.order === this.currentQuestionPage).question_id
      this.question = this.questions.find(question => question.order === this.currentQuestionPage)
      await this.getScoresByAssignmentAndQuestion()
      this.studentId = null
      this.submission = null
      this.score = null
      this.setStudentIds()
      this.isTableLoading = false
    },
    async updateFilter (studentId, submission, score) {
      await this.getScoresByAssignmentAndQuestion()
      if (this.studentId !== null) {
        this.items = this.items.filter(item => item.user_id === studentId)
      }
      if (this.submission !== null) {
        this.items = Array.isArray(submission)
          ? this.items.filter(item => +item.submission >= +submission[0] && +item.submission <= +submission[1])
          : this.items = this.items.filter(item => item.submission === submission)
        this.$bvModal.hide('modal-value-range')
      }

      if (this.score !== null) {
        this.items = this.items.filter(item => item.score === score)
      }
      this.setStudentIds()
    },
    async updateStudentsFilter (value) {
      this.isTableLoading = true
      await this.updateFilter(value, this.submission, this.score)
      this.isTableLoading = false
    },
    async updateSubmissionsFilter (value) {
      if (value === 'value_range') {
        this.minValue = this.maxValue = ''
        this.$bvModal.show('modal-value-range')
        return
      }
      this.isTableLoading = true
      await this.updateFilter(this.studentId, value, this.score)
      this.isTableLoading = false
    },
    async updateScoresFilter (value) {
      this.isTableLoading = true
      await this.updateFilter(this.studentId, this.submission, value)
      this.isTableLoading = false
    },
    setStudentIds () {
      this.questionScoreForm.user_ids = []
      for (let i = 0; i < this.items.length; i++) {
        this.questionScoreForm.user_ids.push(this.items[i].user_id)
      }
    },
    compareLastFirst (a, b) {
      if (a.last_first < b.last_first) {
        return -1
      }
      if (a.last_first > b.last_first) {
        return 1
      }
      return 0
    },
    async getScoresByAssignmentAndQuestion () {
      try {
        const { data } = await axios.get(`/api/auto-graded-and-file-submissions/${this.assignmentId}/${this.questionId}/get-auto-graded-and-file-submissions-by-assignment-and-question-and-student`)
        console.log(data)
        this.isLoading = false
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.assignmentName = data.assignment_name
        this.items = []
        this.fields = [{
          key: 'last_first',
          label: 'Last, First',
          sortable: true,
          shown: true,
          isRowHeader: true
        },
          {
            key: 'email',
            sortable: true,
            shown: true
          },
          {
            key: 'submission',
            sortable: true,
            shown: true
          },
          {
            key: 'submission_count',
            label: 'Count',
            sortable: true,
            shown: true
          },
          {
            key: 'score',
            sortable: true,
            shown: true
          }]
        this.autoGradedView = false
        this.openEndedView = false
        let hasAutoGraded = data.auto_graded_submission_info_by_user.length > 0
        let hasOpenEnded = data.open_ended_submission_info_by_user.length > 0
        this.autoGradedSubmissionInfoByUser = data.auto_graded_submission_info_by_user
        this.openEndedSubmissionInfoByUser = data.open_ended_submission_info_by_user
        this.hasAutoGradedAndOpended = hasAutoGraded && hasOpenEnded
        if (!this.hasAutoGradedAndOpended) {
          this.questionScoreForm.type = hasAutoGraded ? 'Auto-graded' : 'Open-ended'
        }
        if (hasAutoGraded) {
          this.questionScoreForm.type = 'Auto-graded'
          this.items = this.autoGradedSubmissionInfoByUser
          this.autoGradedView = true
        } else if (hasOpenEnded) {
          this.questionScoreForm.type = 'Open-ended'
          this.items = this.openEndedSubmissionInfoByUser
          this.openEndedView = true
          this.fields.find(field => field.key === 'submission_count').shown = false
        }

        this.studentsOptions = [{ 'value': null, text: 'All students with submissions' }]
        this.scoresOptions = []
        this.submissionsOptions = []
        let usedSubmissions = []
        let usedScores = []
        let submission
        if (this.items) {
          this.items.sort(this.compareLastFirst)
        }
        for (let i = 0; i < this.items.length; i++) {
          let item = this.items[i]
          let student = { value: item.user_id, text: item.name }
          this.studentsOptions.push(student)
          let score = { value: item.score, text: item.score }
          if (!usedScores.includes(score.value)) {
            this.scoresOptions.push(score)
            usedScores.push(score.value)
          }
          submission = { value: item.submission, text: item.submission }
          if (!usedSubmissions.includes(submission.value)) {
            this.submissionsOptions.push(submission)
            usedSubmissions.push(submission.value)
          }
        }
        this.submissionsOptions = this.submissionsOptions.sort((a, b) => (a.value > b.value) ? 1 : -1)

        // move no submission to the end
        this.submissionsOptions.unshift({ value: 'value_range', text: 'Range of values' })
        this.submissionsOptions.unshift({ value: null, text: 'Any submission' })

        this.scoresOptions = this.scoresOptions.sort((a, b) => (a.value > b.value) ? 1 : -1)
        this.scoresOptions.unshift({ value: null, text: 'Any score' })
        console.log(this.scoresOptions)
      } catch (error) {
        this.$noty.error(error.message)
        this.isLoading = false
      }
    }
  }
}
</script>
