<template>
  <div>
    <div class="vld-parent">
      <ErrorMessage modal-id="modal-form-errors-assignment-score-overrides" :all-form-errors="allFormErrors" />
      <b-modal
        id="modal-override-assignment-score"
        title="Override Assignment Score"
      >
        <ul style="list-style:none; margin-left:0;padding-left:0">
          <li>Student: {{ firstLast }}</li>
          <li>Original Score: {{ originalScore }}</li>
        </ul>
        <RequiredText :plural="false" />
        <b-form-group
          label-cols-sm="3"
          label-cols-lg="2"
          label-for="score"
          label="Score*"
        >
          <b-form-input
            id="score"
            v-model="overrideAssignmentScoreForm.score"
            style="width: 100px"
            required
            type="text"
            :class="{ 'is-invalid':overrideAssignmentScoreForm.errors.has('score') }"
            @keydown="overrideAssignmentScoreForm.errors.clear('score')"
          />
          <has-error :form="overrideAssignmentScoreForm" field="score" />
        </b-form-group>
        <template #modal-footer>
          <b-button
            size="sm"
            class="float-right"
            @click="$bvModal.hide('modal-override-assignment-score')"
          >
            Cancel
          </b-button>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="updateOverrideAssignmentScore"
          >
            Save
          </b-button>
        </template>
      </b-modal>
      <ModalOverrideSubmissionScore :active-submission-score="activeSubmissionScore"
                                    :override-submission-score-form="overrideSubmissionScoreForm"
                                    :first-last="firstLast"
                                    :original-score="originalScore.toString()"
                                    :question-title="questionTitle"
                                    @reloadSubmissionScores="getAssignmentQuestionScoresByUser(0)"
      />
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-if="!isLoading">
        <PageTitle title="Assignment Gradebook" />
        <p class="pb-2">
          Below is a table of the scores at the question level for each student. You can override individual question
          scores by clicking on any of the entries.
        </p>
        <a
          class="float-right mb-2 btn-sm btn-primary link-outline-primary-btn"
          :href="`/api/scores/assignment/get-assignment-questions-scores-by-user/${assignmentId}/0/1`"
        >
          Download Scores
        </a>
        <TimeSpent @updateView="getAssignmentQuestionScoresByUser" />
        <p v-if="hasOutOfRangeScores" class="small mb-2">
          <span class="score-out-of-range">Highlighted</span> scores are above the question's points (or the
          assignment's total) or below 0, usually from an override or a rubric adjustment. Hover over one for details.
        </p>
        <b-table
          v-show="items.length"
          aria-label="Assignment Gradebook"
          striped
          hover
          responsive
          sticky-header="800px"
          :no-border-collapse="true"
          :fields="fields"
          :items="items"
        >
          <template v-slot:cell()="data">
            <span v-if="data.field.key !== 'total_points'">
              <span v-if="nonEditableFields.includes(data.field.key)">
                {{ data.value }} <span v-if="data.field.key === 'name'">

                  <QuestionCircleTooltip :id="`student-${data.item.userId}`" />
                  <b-tooltip :target="`student-${data.item.userId}`"
                             delay="250"
                             width="600"
                             triggers="hover focus"
                             custom-class="custom-tooltip"
                  >
                    Student ID: {{ data.item.student_id }}<br>
                    Email: {{ data.item.email }}
                  </b-tooltip>
                </span>
              </span>
              <span v-if="!nonEditableFields.includes(data.field.key)"
                    style="cursor:pointer"
                    :class="[isSubmissionScoreOverride(data) ? 'text-info' : '',
                             { 'score-out-of-range': outOfRangeMessage(data.value, data.field.points) }]"
                    :title="outOfRangeMessage(data.value, data.field.points)"
                    @click="initOverrideSubmissionScore(data)"
              >{{ data.value }}
              </span>
            </span>
            <span v-if="data.field.key === 'total_points'">
              <span style="cursor:pointer"
                    :class="[data.item.override_score ? 'text-info' : '',
                             { 'score-out-of-range': outOfRangeMessage(totalScore(data), data.field.points) }]"
                    :title="outOfRangeMessage(totalScore(data), data.field.points)"
                    @click="initOverrideAssignmentScore(data)"
              >{{ totalScore(data) }}
              </span>
            </span>
          </template>
        </b-table>
        <b-alert :show="!items.length">
          <span class="font-weight-bold">
            This course has no enrolled users so there is no gradebook to show.
          </span>
        </b-alert>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import TimeSpent from '~/components/TimeSpent'
import { mapGetters } from 'vuex'
import Form from 'vform'
import ModalOverrideSubmissionScore from '~/components/ModalOverrideSubmissionScore.vue'
import ErrorMessage from '../../../components/ErrorMessage.vue'

export default {
  middleware: 'auth',
  components: {
    ErrorMessage,
    ModalOverrideSubmissionScore,
    Loading,
    TimeSpent
  },
  metaInfo () {
    return { title: 'Assignment Gradebook' }
  },
  data: () => ({
    allFormErrors: [],
    overrideAssignmentScoreForm: new Form({
      assignment_id: 0,
      student_user_id: 0,
      first_last: '',
      score: 0
    }),
    assignmentScoreOverrides: [],
    nonEditableFields: ['name', 'total_points', 'percent_correct'],
    questionTitle: '',
    originalScore: '',
    firstLast: '',
    activeSubmissionScore: {},
    overrideSubmissionScoreForm: new Form({
      assignment_id: 0,
      question_id: 0,
      student_user_id: 0,
      first_last: '',
      question_title: '',
      score: 0
    }),
    assignmentId: 0,
    fields: [],
    items: [],
    isLoading: true,
    showTimeSpent: false,
    toggleColors: window.config.toggleColors
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    hasOutOfRangeScores () {
      return this.items.some(item => this.fields.some(field => {
        const value = field.key === 'total_points' ? (item.override_score || item.total_points) : item[field.key]
        return this.outOfRangeMessage(value, field.points)
      }))
    }
  },
  mounted () {
    if (![2, 4].includes(this.user.role)) {
      this.$router.push({ name: 'no.access' })
      return false
    }
    this.assignmentId = this.$route.params.assignmentId
    this.getAssignmentQuestionScoresByUser(0)
  },
  methods: {
    totalScore (data) {
      return data.item.override_score ? data.item.override_score : data.value
    },
    // A score cell can carry the time spent after the score ("7 3:20"), or be "-" when there's no submission.
    // Returns a message for a score above its maximum or below 0, and '' otherwise.
    outOfRangeMessage (value, maximum) {
      if (maximum === undefined || maximum === null || value === undefined || value === null) {
        return ''
      }
      const score = parseFloat(String(value).trim())
      const max = parseFloat(maximum)
      if (isNaN(score) || isNaN(max)) {
        return ''
      }
      if (score > max + 0.005) {
        return `Above the maximum of ${max} ${max === 1 ? 'point' : 'points'}`
      }
      if (score < -0.005) {
        return 'Below 0'
      }
      return ''
    },
    async updateOverrideAssignmentScore () {
      try {
        const { data } = await this.overrideAssignmentScoreForm.patch(`/api/scores/${this.assignmentId}/${this.studentUserId}`)
        if (data.type === 'success') {
          this.$noty.success(data.message)
          this.$bvModal.hide('modal-override-assignment-score')
          await this.getAssignmentQuestionScoresByUser(0)
        } else {
          this.overrideAssignmentScoreForm.errors.set('score', data.message)
          this.allFormErrors = [data.message]
          this.$bvModal.show('modal-form-errors-assignment-score-overrides')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    isSubmissionScoreOverride (obj) {
      return this.submissionScoreOverrides.filter(item => +item.user_id === +obj.item.userId && +item.question_id === +obj.field.key).length > 0
    },
    getOriginalSubmissionScore (obj) {
      const studentUserId = obj.item.userId
      const questionId = obj.field.key
      const submittedItem = this.originalSubmissionScores.find(item => +item.user_id === +studentUserId && +item.question_id === +questionId)
      return submittedItem ? submittedItem.original_score : '-'
    },
    initOverrideAssignmentScore (obj) {
      this.activeAssignmentScore = obj
      this.firstLast = this.activeAssignmentScore.item.name
      this.originalScore = obj.value
      if (this.activeAssignmentScore.item.name.includes(',')) {
        let lastFirst = this.activeAssignmentScore.item.name.split(',')
        this.firstLast = lastFirst[1] + ' ' + lastFirst[0]
      }
      this.studentUserId = obj.item.userId
      this.overrideAssignmentScoreForm = new Form({
        assignment_id: this.assignmentId,
        student_user_id: this.studentUserId,
        first_last: this.firstLast,
        score: this.activeAssignmentScore.item.override_score ? this.activeAssignmentScore.item.override_score : this.originalScore
      })
      this.$bvModal.show('modal-override-assignment-score')
    },

    initOverrideSubmissionScore (obj) {
      this.activeSubmissionScore = obj
      this.firstLast = this.activeSubmissionScore.item.name
      if (this.activeSubmissionScore.item.name.includes(',')) {
        let lastFirst = this.activeSubmissionScore.item.name.split(',')
        this.firstLast = lastFirst[1] + ' ' + lastFirst[0]
      }

      let score = ''
      if (obj.item[obj.field.key].trim() !== '-') {
        score = obj.item[obj.field.key]
        score = score.slice(0, score.indexOf(' ')) // if the user is looking at the timing, don't show this
      }
      this.overrideSubmissionScoreForm = new Form({
        assignment_id: this.assignmentId,
        question_id: obj.field.key,
        student_user_id: obj.item.userId,
        first_last: this.firstLast,
        question_title: this.questionTitle,
        score: score
      })
      this.originalScore = this.getOriginalSubmissionScore(obj)
      this.questionTitle = obj.field.label
      this.$bvModal.show('modal-override-submission-score')
    },
    async getAssignmentQuestionScoresByUser (timeSpent) {
      try {
        const { data } = await axios.get(`/api/scores/assignment/get-assignment-questions-scores-by-user/${this.assignmentId}/${timeSpent}/0`)
        console.log(data)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.items = data.rows
        this.fields = data.fields
        this.submissionScoreOverrides = data.submission_score_overrides
        this.originalSubmissionScores = data.original_submission_scores
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.isLoading = false
    }
  }
}
</script>
<style scoped>
/* a score outside 0 to its maximum: dark red text on light red, readable in the striped rows */
.score-out-of-range {
  background-color: #fde2e1;
  color: #a71d2a;
  font-weight: 600;
  padding: 1px 4px;
  border-radius: 3px;
}

.custom-tooltip .tooltip-inner {
  max-width: 800px; /* Set the desired width */
  white-space: normal;
}
</style>
