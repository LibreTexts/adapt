<template>
  <div>
    <PageTitle title="Overrides"/>
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
        <div v-if="enrollments.length">
          <b-card v-if="isTimedAssignment"
                  header-html="<h2 class=&quot;h7&quot;>Manage Timer (per student)</h2>"
                  class="mb-4"
          >
            <b-card-text>
              <p>
                View or adjust an individual student's personal time limit clock for this assignment.
              </p>
              <b-form-group
                label-cols-sm="3"
                label-cols-lg="2"
                label-for="timer_apply_to"
              >
                <template slot="label">
                  Student*
                </template>
                <b-form-row>
                  <div class="mt-1">
                    <b-form-select id="timer_apply_to"
                                   v-model="timerApplyTo"
                                   required
                                   size="sm"
                                   :options="enrollments"
                                   @change="getStudentTimerStatus"
                    />
                  </div>
                </b-form-row>
              </b-form-group>
              <div v-if="timerApplyTo && studentTimerStatus.time_limit">
                <p class="mb-2">
                  <span class="font-weight-bold">Time Limit:</span>
                  {{ studentTimerStatus.time_limit_override || studentTimerStatus.time_limit }}
                  <span v-if="studentTimerStatus.time_limit_override" class="text-muted">
                    (override - default is {{ studentTimerStatus.time_limit }})
                  </span>
                  <br>
                  <span v-if="studentTimerStatus.started_at">
                    <span class="font-weight-bold">Started:</span>
                    {{ $moment(studentTimerStatus.started_at).format('M/D/YY h:mm A') }}
                    <br>
                    <span class="font-weight-bold">Expires:</span>
                    {{ $moment(studentTimerStatus.expires_at).format('M/D/YY h:mm A') }}
                    <span v-if="studentTimerStatus.seconds_left > 0" class="text-success">
                      (<countdown :time="studentTimerStatus.seconds_left * 1000" @end="getStudentTimerStatus">
                        <template v-slot="props">{{ formattedCountdownText(props) }} remaining</template>
                      </countdown>)
                    </span>
                    <span v-else class="text-danger">
                      (Expired)
                    </span>
                    <span v-if="studentTimerStatus.extended_past_due" class="text-warning">
                      (extended past due)
                    </span>
                  </span>
                  <span v-else>This student has not started the assignment yet.</span>
                </p>
                <b-form-group label-size="sm">
                  <template #label>
                    Add minutes to the current clock
                    <QuestionCircleTooltip id="add-time-tooltip"/>
                    <b-tooltip target="add-time-tooltip" delay="250" triggers="hover focus">
                      Adds time to whatever's currently running. The fastest option for a quick, small extension -
                      no need to know or calculate the current total.
                    </b-tooltip>
                  </template>
                  <b-input-group>
                    <b-form-input v-model.number="minutesToAddForStudent" type="number" min="1" size="sm" style="max-width: 120px;"/>
                    <b-input-group-append>
                      <span id="add-time-button-wrapper" tabindex="0" class="d-inline-block">
                        <b-button size="sm" variant="primary" :disabled="addingTimeForStudent || !studentTimerStatus.started_at" @click="addTimeForStudent"
                                  :style="{ 'pointer-events': (addingTimeForStudent || !studentTimerStatus.started_at) ? 'none' : 'auto' }"
                        >
                          Add Time
                        </b-button>
                      </span>
                      <b-tooltip v-if="!studentTimerStatus.started_at" target="add-time-button-wrapper" delay="250" triggers="hover focus">
                        This student hasn't started yet, so there's no running clock to add time to. Use Set Time
                        below to start one on their behalf.
                      </b-tooltip>
                    </b-input-group-append>
                  </b-input-group>
                  <small v-if="!studentTimerStatus.started_at" class="text-muted">
                    This student hasn't started yet, so there's no running clock to add time to. Use Set Time below
                    to start one on their behalf.
                  </small>
                </b-form-group>
                <b-form-group label-size="sm">
                  <template #label>
                    Set a new total duration (e.g. '1 hour', '90 seconds')
                    <QuestionCircleTooltip id="set-time-tooltip"/>
                    <b-tooltip target="set-time-tooltip" delay="250" triggers="hover focus">
                      Sets a brand-new total duration, replacing the current clock. If the student hasn't started
                      yet, this starts their clock immediately, right now - unlike Reset, it doesn't wait for them
                      to click Start.
                    </b-tooltip>
                  </template>
                  <b-input-group>
                    <b-form-input v-model="newTimeLimitForStudent" type="text" size="sm" placeholder="e.g. 1 hour"
                                  style="max-width: 220px;"
                                  :state="newTimeLimitForStudent ? looksLikeValidDuration(newTimeLimitForStudent) : null"
                    />
                    <b-input-group-append>
                      <span id="set-time-button-wrapper" tabindex="0" class="d-inline-block">
                        <b-button size="sm" variant="primary"
                                  :disabled="settingTimeForStudent || !newTimeLimitForStudent || !looksLikeValidDuration(newTimeLimitForStudent)"
                                  @click="setTimeForStudent"
                                  :style="{ 'pointer-events': (settingTimeForStudent || !newTimeLimitForStudent || !looksLikeValidDuration(newTimeLimitForStudent)) ? 'none' : 'auto' }"
                        >
                          Set Time
                        </b-button>
                      </span>
                      <b-tooltip v-if="setTimeDisabledReason()" target="set-time-button-wrapper" delay="250" triggers="hover focus">
                        {{ setTimeDisabledReason() }}
                      </b-tooltip>
                    </b-input-group-append>
                    <b-form-invalid-feedback :state="newTimeLimitForStudent ? looksLikeValidDuration(newTimeLimitForStudent) : null">
                      Enter a duration like "1 hour", "90 seconds", or "2 minutes".
                    </b-form-invalid-feedback>
                  </b-input-group>
                </b-form-group>
                <b-button size="sm" variant="outline-danger" :disabled="resettingStudentTimer" @click="resetStudentTimer">
                  Reset Timer Entirely
                </b-button>
                <QuestionCircleTooltip id="reset-timer-tooltip"/>
                <b-tooltip target="reset-timer-tooltip" delay="250" triggers="hover focus">
                  Wipes the clock completely. The student sees the Start Assignment screen again and can begin on
                  their own timing whenever they're ready - unlike Set Time, which starts the clock immediately.
                </b-tooltip>
              </div>
              <b-alert v-else-if="timerApplyTo && !studentTimerStatusLoading" :show="true" variant="info" class="font-weight-bold">
                This assignment does not have a time limit configured for this student.
              </b-alert>
            </b-card-text>
          </b-card>
          <b-card v-if="isTimedAssignment"
                  header-html="<h2 class=&quot;h7&quot;>Active Timers</h2>"
                  class="mb-4"
          >
            <b-card-text>
              <p v-if="allStudentStatuses.length">
                Students who have started their personal timer for this assignment. Click Manage to adjust or
                reset a student's clock above.
              </p>
              <p v-else-if="!allStudentStatusesLoading">
                No students have started their timer for this assignment yet.
              </p>
              <b-table v-if="allStudentStatuses.length" :items="allStudentStatuses" :fields="timerStatusFields" small responsive>
                <template #cell(started_at)="row">
                  {{ $moment(row.item.started_at).format('M/D/YY h:mm A') }}
                </template>
                <template #cell(remaining)="row">
                  <span v-if="row.item.seconds_left > 0" class="text-success">
                    <countdown :time="row.item.seconds_left * 1000" @end="getAllStudentStatuses">
                      <template v-slot="props">{{ formattedCountdownText(props) }} remaining</template>
                    </countdown>
                  </span>
                  <span v-else class="text-danger">Expired</span>
                  <span v-if="row.item.extended_past_due" class="text-warning ml-1">(past due)</span>
                </template>
                <template #cell(select)="row">
                  <b-button size="sm" variant="outline-primary" @click="manageStudentTimer(row.item.user_id)">
                    Manage
                  </b-button>
                </template>
              </b-table>
            </b-card-text>
          </b-card>
          <b-card
            header-html="<h2 class=&quot;h7&quot;>Allow Submitting, Uploading PDF, and Assigning (all questions in assignment)</h2>"
            class="mb-4"
          >
            <b-card-text>
              <p>
                Optionally allow a subset of your class to re-submit any auto-graded or open-ended question
                after an assignment has been closed. Students may also upload a Compiled PDF and Set Pages if the
                Compiled
                PDF option is set for the assignment. If this option is set, it will take precedence over any individual
                override
                set below.
              </p>
              <b-form>
                <RequiredText/>
                <b-form-group
                  label-cols-sm="3"
                  label-cols-lg="2"
                  label-for="assignment_level_apply_to"
                >
                  <template slot="label">
                    Apply To*
                  </template>
                  <b-form-row>
                    <div class="d-flex mt-1">
                      <b-form-select id="assignment_level_apply_to"
                                     v-model="assignmentLevelApplyTo"
                                     cols="5"
                                     required
                                     size="sm"
                                     class="mr-2"
                                     :options="enrollments"
                      />
                      <b-button variant="primary"
                                size="sm"
                                @click="updateOverrides(assignmentLevelOverrides,'assignment-level', assignmentLevelApplyTo)"
                      >
                        Update
                      </b-button>
                    </div>
                  </b-form-row>
                </b-form-group>
              </b-form>
              <div v-if="assignmentLevelOverrides.length">
                <ul v-for="assignmentLevelOverride in assignmentLevelOverrides"
                    :key="`assignment_level_override_${assignmentLevelOverride.value}`"
                >
                  <li>
                    {{ assignmentLevelOverride.text }}
                    <a href="" @click.prevent="removeOverride(assignmentLevelOverride,'assignment-level')">
                      <b-icon-trash class="text-muted"
                                    :aria-label="`Remove assignment level override: ${assignmentLevelOverride.text}`"
                      />
                    </a>
                  </li>
                </ul>
                <b-button variant="danger"
                          size="sm"
                          @click="removeOverride({value: -1},'assignment-level')"
                >
                  Remove All Assignment Level Overrides
                </b-button>
              </div>
              <div v-else>
                <b-alert :show="true" variant="info" class="font-weight-bold">
                  No students have been selected.
                </b-alert>
              </div>
            </b-card-text>
          </b-card>
          <div v-if="fileUploadMode !== 'individual_assessment'">
            <b-card
              header-html="<h2 class=&quot;h7&quot;>Allow Uploading PDF and Assigning (all questions in assignment)</h2>"
              class="mb-4"
            >
              <b-card-text>
                <p>
                  Optionally allow a subset of your class to upload their compiled PDF and set pages even if
                  an assignment has been closed. This can be useful if individual students with the uploading process.
                </p>
                <b-form>
                  <RequiredText/>
                  <b-form-group
                    label-cols-sm="3"
                    label-cols-lg="2"
                    label-for="compiled_pdf_apply_to"
                  >
                    <template slot="label">
                      Apply To*
                    </template>
                    <b-form-row>
                      <div class="d-flex mt-1">
                        <b-form-select id="compiled_pdf_apply_to"
                                       v-model="compiledPDFApplyTo"
                                       required
                                       cols="5"
                                       size="sm"
                                       class="mr-2"
                                       :options="enrollments"
                        />
                        <b-button variant="primary"
                                  size="sm"
                                  @click="updateOverrides(compiledPDFOverrides,'compiled-pdf', compiledPDFApplyTo)"
                        >
                          Update
                        </b-button>
                      </div>
                    </b-form-row>
                  </b-form-group>
                </b-form>
                <div v-if="compiledPDFOverrides.length">
                  <ul v-for="compiledPDFOverride in compiledPDFOverrides"
                      :key="`compiled_pdf_override_${compiledPDFOverride.value}`"
                  >
                    <li>
                      {{ compiledPDFOverride.text }}
                      <a href="" @click.prevent="removeOverride(compiledPDFOverride,'compiled-pdf')">
                        <b-icon-trash class="text-muted"
                                      :aria-label="`Remove compiled PDF override: ${compiledPDFOverride.text}`"
                        />
                      </a>
                    </li>
                  </ul>
                  <b-button variant="danger"
                            size="sm"
                            @click="removeOverride({value: -1},'compiled-pdf')"
                  >
                    Remove All Compiled PDF Overrides
                  </b-button>
                </div>
                <div v-else>
                  <b-alert :show="true" variant="info" class="font-weight-bold">
                    No students have been selected.
                  </b-alert>
                </div>
              </b-card-text>
            </b-card>
            <b-card
              header-html="<h2 class=&quot;h7&quot;>Allow Assigning to Existing Uploaded PDF (all questions in assignment)</h2>"
              class="mb-4"
            >
              <b-card-text>
                <p>
                  Optionally allow a subset of your class to set pages in their compiled PDF even if
                  an assignment has been closed. Students will not be allowed to upload a new compiled PDF.
                  This can be useful if individual students forgot to do this after
                  uploading their compiled PDF.
                </p>
                <b-form>
                  <RequiredText/>
                  <b-form-group
                    label-cols-sm="3"
                    label-cols-lg="2"
                    label-for="set_page_apply_to"
                  >
                    <template slot="label">
                      Apply To*
                    </template>
                    <b-form-row>
                      <div class="d-flex mt-1">
                        <b-form-select id="set_page_apply_to"
                                       v-model="setPageApplyTo"
                                       required
                                       size="sm"
                                       class="mr-2"
                                       :options="enrollments"
                        />
                        <b-button variant="primary"
                                  size="sm"
                                  @click="updateOverrides(setPageOverrides,'set-page-only', setPageApplyTo)"
                        >
                          Update
                        </b-button>
                      </div>
                    </b-form-row>
                  </b-form-group>
                </b-form>
                <div v-if="setPageOverrides.length">
                  <ul v-for="setPageOverride in setPageOverrides"
                      :key="`set_page_override_${setPageOverride.value}`"
                  >
                    <li>
                      {{ setPageOverride.text }}
                      <a href="" @click.prevent="removeOverride(setPageOverride,'set-page-only')">
                        <b-icon-trash class="text-muted"
                                      :aria-label="`Remove set page override: ${setPageOverride.text}`"
                        />
                      </a>
                    </li>
                  </ul>
                  <b-button variant="danger"
                            size="sm"
                            @click="removeOverride({value: -1},'set-page-only')"
                  >
                    Remove All Set Page Overrides
                  </b-button>
                </div>
                <div v-else>
                  <b-alert :show="true" variant="info" class="font-weight-bold">
                    No students have been selected.
                  </b-alert>
                </div>
              </b-card-text>
            </b-card>
          </div>
          <b-card header-html="<h2 class=&quot;h7&quot;>Allow Full Submitting (single question in assignment)</h2>"
                  class="mb-4"
          >
            <b-card-text>
              <p>
                Optionally allow a subset of your class to resubmit questions regardless of whether the assignment is
                closed.
              </p>
              <b-form>
                <RequiredText/>
                <b-form-group
                  label-cols-sm="3"
                  label-cols-lg="2"
                  label-for="questions"
                >
                  <template slot="label">
                    Question*
                  </template>
                  <b-form-row>
                    <div class="mt-1">
                      <b-form-select id="questions"
                                     v-model="currentQuestionPage"
                                     required
                                     :options="questionsOptions"
                                     cols="2"
                                     size="sm"
                                     @change="updateQuestionSubmissionTypes"
                      />
                    </div>
                  </b-form-row>
                </b-form-group>
                <b-form-group
                  label-cols-sm="3"
                  label-cols-lg="2"
                  label="Apply to"
                >
                  <template slot="label">
                    Type<span v-if="showQuestionSubmissionTypes">*</span>
                  </template>
                  <div class="mt-2">
                    <b-form-checkbox-group
                      v-if="showQuestionSubmissionTypes"
                      id="submission_options"
                      v-model="selectedSubmissionTypes"
                      required
                      name="submission options"
                    >
                      <b-form-checkbox value="auto-graded">
                        auto-graded
                      </b-form-checkbox>
                      <b-form-checkbox value="open-ended">
                        open-ended
                      </b-form-checkbox>
                    </b-form-checkbox-group>
                    <span v-if="justAutoGraded">Auto-graded</span>
                    <span v-if="justOpenEnded">Open-ended</span>
                  </div>
                </b-form-group>
                <b-form-group
                  label-cols-sm="3"
                  label-cols-lg="2"
                  label-for="question_level_apply_to"
                >
                  <template slot="label">
                    Apply To*
                  </template>
                  <b-form-row>
                    <div class="d-flex mt-1">
                      <b-form-select id="question_level_apply_to"
                                     v-model="questionLevelApplyTo"
                                     :options="enrollments"
                                     required
                                     size="sm"
                                     cols="5"
                                     class="mr-2"
                      />

                      <b-button variant="primary"
                                size="sm"
                                @click="updateOverrides(questionLevelOverrides,'question-level', questionLevelApplyTo)"
                      >
                        Update
                      </b-button>
                    </div>
                  </b-form-row>
                </b-form-group>
              </b-form>
              <div v-if="questionLevelOverrides.length">
                <ul v-for="questionLevelOverride in questionLevelOverrides"
                    :key="`question_level_override_${questionLevelOverride.value}`"
                >
                  <li>
                    {{ questionLevelOverride.text }}
                    <a href=""
                       @click.prevent="removeOverride(questionLevelOverride,'question-level',questionLevelOverride.question_id)"
                    >
                      <b-icon-trash class="text-muted"
                                    :aria-label="`Remove question level override: ${questionLevelOverride.text }`"
                      />
                    </a>
                  </li>
                </ul>
                <b-button variant="danger"
                          size="sm"
                          @click="removeOverride({value: -1},'question-level')"
                >
                  Remove All Question Level Overrides
                </b-button>
              </div>
              <div v-else>
                <b-alert :show="true" variant="info" class="font-weight-bold">
                  No students have been selected.
                </b-alert>
              </div>
            </b-card-text>
          </b-card>
        </div>
      </div>
    </div>
    <b-modal id="modal-confirm-timer-action"
             title="Please Confirm"
             ok-title="Continue"
             cancel-title="Cancel"
             button-size="sm"
             @ok="runPendingTimerConfirmAction"
    >
      <p class="mb-0">{{ confirmTimerModalMessage }}</p>
    </b-modal>
    <div v-if="!enrollments.length">
      <b-alert :show="true" variant="info">
        <span class="font-weight-bold">
          You will be able to provide submission overrides to students once
          students are enrolled in this course.</span>
      </b-alert>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { getQuestions } from '~/helpers/Questions'
import Form from 'vform'

export default {
  components: { Loading },
  metaInfo () {
    return { title: 'Assignment Overrides' }
  },
  data: () => ({
    fileUploadMode: '',
    isTimedAssignment: false,
    justAutoGraded: false,
    justOpenEnded: false,
    selectedSubmissionTypes: [],
    showQuestionSubmissionTypes: false,
    questionId: 0,
    currentQuestionPage: null,
    questions: [],
    questionsOptions: [],
    assignmentLevelOverrides: [],
    compiledPDFOverrides: [],
    setPageOverrides: [],
    questionLevelOverrides: [],
    assignmentLevelApplyTo: null,
    questionLevelApplyTo: null,
    compiledPDFApplyTo: null,
    setPageApplyTo: null,
    enrollments: [{ 'text': 'Select a student', 'value': null }],
    isLoading: true,
    assignmentId: 0,
    timerApplyTo: null,
    studentTimerStatus: {},
    studentTimerStatusLoading: false,
    minutesToAddForStudent: null,
    addingTimeForStudent: false,
    newTimeLimitForStudent: '',
    settingTimeForStudent: false,
    resettingStudentTimer: false,
    confirmTimerModalMessage: '',
    pendingTimerConfirmAction: null,
    allStudentStatuses: [],
    allStudentStatusesLoading: false,
    timerStatusFields: [
      { key: 'name', label: 'Student' },
      { key: 'time_limit', label: 'Duration' },
      { key: 'started_at', label: 'Started' },
      { key: 'remaining', label: 'Time Remaining' },
      { key: 'select', label: '' }
    ]
  }),
  async mounted () {
    this.assignmentId = this.$route.params.assignmentId
    await this.getAssignmentSummary()
    this.getQuestions = getQuestions
    await this.getQuestions()
    this.currentQuestionPage = 1
    await this.getEnrolledStudentsFromAssignment()
    if (this.enrollments.length && this.questions.length) {
      await this.getOverrides()
      this.updateQuestionSubmissionTypes()
    }
    if (this.isTimedAssignment) {
      await this.getAllStudentStatuses()
    }

    this.isLoading = false
  },
  methods: {
    async getAssignmentSummary () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/summary`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.fileUploadMode = data.assignment.file_upload_mode
        this.isTimedAssignment = Boolean(
          data.assignment.assign_tos && data.assignment.assign_tos.some(assignTo => assignTo.time_limit)
        )
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    updateQuestionSubmissionTypes () {
      let question = this.questions.find(question => question.order === this.currentQuestionPage)
      this.showQuestionSubmissionTypes = question.is_auto_graded && question.is_open_ended
      this.justAutoGraded = question.is_auto_graded && !question.is_open_ended
      if (this.justAutoGraded) {
        this.selectedSubmissionTypes = ['auto-graded']
      }
      this.justOpenEnded = !question.is_auto_graded && question.is_open_ended
      if (this.justOpenEnded) {
        this.selectedSubmissionTypes = ['open-ended']
      }

      this.questionId = question.question_id
    },
    async getOverrides () {
      try {
        const { data } = await axios.get(`/api/submission-overrides/${this.assignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.compiledPDFOverrides = data.compiled_pdf_overrides
        this.setPageOverrides = data.set_page_overrides
        this.questionLevelOverrides = data.question_level_overrides
        this.assignmentLevelOverrides = data.assignment_level_overrides
        this.assignmentLevelOverride = null
        this.compiledPDFOverride = null
        this.setPageOverride = null
        this.questionLevelOverride = null
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async removeOverride (student, type, questionId = null) {
      try {
        let url = `/api/submission-overrides/${this.assignmentId}/${student.value}/${type}`
        if (questionId) {
          url += `/${questionId}`
        }
        const { data } = await axios.delete(url)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        await this.getOverrides()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async canCreateOverride () {
      try {
        const { data } = await axios.get(`/api/auto-release/statuses/${this.assignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        const autoReleaseStatuses = data.auto_release_statuses

        if (autoReleaseStatuses.solutions_released) {
          const message = 'Please visit the Control Panel and un-release the solutions before adding giving this override.'
          this.$noty.error(message)
          return false
        }
        return true
      } catch (error) {
        this.$noty.error(error.message)
      }
      return false
    },
    async updateOverrides (overrides, type, applyTo) {
      let student = this.enrollments.find(student => student.value === applyTo)
      let alreadyExistsAsOverride = overrides.find(override => student.value === override.value)
      let everybodyChosen = overrides.find(override => override.value === -1)

      if (student.value === null) {
        this.$noty.info('Please choose a student.')
        return false
      }
      if (!await this.canCreateOverride()) {
        return false
      }
      if (type !== 'question-level' && alreadyExistsAsOverride) {
        this.$noty.info(`${student.text} is already on your list.`)
        return false
      }
      if (type === 'question-level' && this.showQuestionSubmissionTypes && !this.selectedSubmissionTypes.length) {
        this.$noty.info('Please choose at least one of the submission types.')
        return false
      }

      if (type !== 'question-level' && everybodyChosen) {
        this.$noty.info('All students have already been chosen.')
        return false
      }
      let submissionOverrideData = {
        student: student,
        type: type
      }
      if (type === 'question-level') {
        submissionOverrideData.selected_submission_types = this.selectedSubmissionTypes
        submissionOverrideData.question_id = this.questionId
        submissionOverrideData.question_order = this.currentQuestionPage
      }

      try {
        const { data } = await axios.patch(`/api/submission-overrides/${this.assignmentId}`,
          submissionOverrideData)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        await this.getOverrides()
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
    ,
    async getEnrolledStudentsFromAssignment () {
      try {
        const { data } = await axios.get(`/api/enrollments/${this.assignmentId}/from-assignment`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.enrollments = data.enrollments.length > 2 ? data.enrollments : []
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getStudentTimerStatus () {
      this.studentTimerStatus = {}
      if (!this.timerApplyTo) {
        return
      }
      this.studentTimerStatusLoading = true
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/time-limit/${this.timerApplyTo}/status`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        this.studentTimerStatus = data
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.studentTimerStatusLoading = false
      }
    },
    async getAllStudentStatuses () {
      this.allStudentStatusesLoading = true
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/time-limit/statuses`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        this.allStudentStatuses = data.statuses
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.allStudentStatusesLoading = false
      }
    },
    manageStudentTimer (userId) {
      this.timerApplyTo = userId
      this.getStudentTimerStatus()
      this.$nextTick(() => {
        const select = document.getElementById('timer_apply_to')
        if (select) {
          select.scrollIntoView({ behavior: 'smooth', block: 'center' })
        }
      })
    },
    setTimeDisabledReason () {
      if (this.settingTimeForStudent) {
        return ''
      }
      if (!this.newTimeLimitForStudent) {
        return 'Enter a duration first, like "1 hour" or "90 seconds".'
      }
      if (!this.looksLikeValidDuration(this.newTimeLimitForStudent)) {
        return 'That doesn\'t look like a valid duration. Try something like "1 hour", "90 seconds", or "2 minutes".'
      }
      return ''
    },
    formattedCountdownText (props) {
      const parts = []
      if (props.hours > 0) parts.push(`${props.hours}h`)
      if (props.minutes > 0) parts.push(`${props.minutes}m`)
      parts.push(`${props.seconds}s`)
      return parts.join(' ')
    },
    looksLikeValidDuration (value) {
      if (!value) {
        return false
      }
      const trimmed = value.trim()
      // ISO 8601 duration, e.g. PT1H30M
      if (/^P(\d+Y)?(\d+M)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?$/i.test(trimmed) && trimmed.toUpperCase() !== 'P') {
        return true
      }
      // Human phrases like "2 minutes", "1 hour, 30 minutes", "90 seconds"
      return /^\d+\s*(second|minute|hour|day|week)s?(\s*,?\s*\d+\s*(second|minute|hour|day|week)s?)*$/i.test(trimmed)
    },
    showTimerConfirm (message, action) {
      this.confirmTimerModalMessage = message
      this.pendingTimerConfirmAction = action
      this.$bvModal.show('modal-confirm-timer-action')
    },
    async runPendingTimerConfirmAction () {
      if (this.pendingTimerConfirmAction) {
        await this.pendingTimerConfirmAction()
        this.pendingTimerConfirmAction = null
      }
    },
    async addTimeForStudent () {
      const alreadyExpired = this.studentTimerStatus.started_at && this.studentTimerStatus.seconds_left <= 0
      const message = alreadyExpired
        ? `This student's time limit has already expired. Adding ${this.minutesToAddForStudent} minute(s) will give them that much time starting now. Continue?`
        : `Add ${this.minutesToAddForStudent} minute(s) to this student's current clock?`
      this.showTimerConfirm(message, () => this.performAddTimeForStudent())
    },
    async performAddTimeForStudent () {
      this.addingTimeForStudent = true
      try {
        const { data } = await axios.post(
          `/api/assignments/${this.assignmentId}/time-limit/${this.timerApplyTo}/add-time`,
          { minutes: this.minutesToAddForStudent }
        )
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        await this.getStudentTimerStatus()
        await this.getAllStudentStatuses()
        this.minutesToAddForStudent = null
        this.$noty.success(
          data.extended_past_due
            ? `Time added. Note: this student's time now extends past the assignment due date.`
            : 'Time added.'
        )
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.addingTimeForStudent = false
      }
    },
    async setTimeForStudent () {
      if (!this.newTimeLimitForStudent || !this.looksLikeValidDuration(this.newTimeLimitForStudent)) {
        return
      }
      this.showTimerConfirm(
        'This will set a new total duration for this student, replacing their current clock. Continue?',
        () => this.performSetTimeForStudent()
      )
    },
    async performSetTimeForStudent () {
      this.settingTimeForStudent = true
      try {
        const { data } = await axios.patch(
          `/api/assignments/${this.assignmentId}/time-limit/${this.timerApplyTo}`,
          { time_limit: this.newTimeLimitForStudent }
        )
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        await this.getStudentTimerStatus()
        await this.getAllStudentStatuses()
        this.newTimeLimitForStudent = ''
        this.$noty.success(
          data.extended_past_due
            ? `Time set. Note: this student's time now extends past the assignment due date.`
            : 'Time set.'
        )
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.settingTimeForStudent = false
      }
    },
    async resetStudentTimer () {
      this.showTimerConfirm(
        'This will fully reset this student\'s timer. They will need to click Start Assignment again for a fresh duration. Continue?',
        () => this.performResetStudentTimer()
      )
    },
    async performResetStudentTimer () {
      this.resettingStudentTimer = true
      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/time-limit/${this.timerApplyTo}/reset`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        await this.getStudentTimerStatus()
        await this.getAllStudentStatuses()
        this.$noty.success('Timer reset for this student.')
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.resettingStudentTimer = false
      }
    }
  }
}
</script>

<style scoped>

</style>
