<template>
  <b-container>
    <b-row v-if="submissionHistory && submissionHistory.length > 1" class="mb-2">
      <label class="col-form-label col-form-label-sm text-right pr-2" for="submission-history">Current View:</label>
      <b-form-select
        id="submission-history"
        v-model="questionSubmissionHistoryId"
        size="sm"
        style="width: 300px"
        :options="submissionHistoryOptions"
        @input="getSubmissionArrayByHistory($event)"
      />
    </b-row>
    <div v-if="showFullHistory">
      <div v-for="(value,submissionHistoryIndex) in  submissionHistory"
           :key="`active-discussion-${submissionHistoryIndex}`"
      >
        <SubmissionArrayTable
          :key="`submission-array-table-${submissionHistoryIndex}`"
          :created-at="value.created_at"
          :current-question-submission-array="value.submission_array"
          :scoring-type="scoringType"
          :small-table="smallTable"
          :penalties="penalties"
          :user-role="userRole"
          :technology="technology"
          :show-correct-answer="showCorrectAnswer"
        />
        <hr v-if="submissionHistory && submissionHistoryIndex !== submissionHistory.length -1">
      </div>
    </div>
    <div v-if="!showFullHistory">
      <SubmissionArrayTable
        :current-question-submission-array="currentQuestionSubmissionArray"
        :scoring-type="scoringType"
        :small-table="smallTable"
        :penalties="penalties"
        :user-role="userRole"
        :technology="technology"
        :show-correct-answer="showCorrectAnswer"
      />
    </div>
  </b-container>
</template>

<script>
import axios from 'axios'
import SubmissionArrayTable from './SubmissionArrayTable.vue'

export default {
  name: 'SubmissionArray',
  components: { SubmissionArrayTable },
  props: {
    renderMathJax: {
      type: Boolean,
      default: true
    },
    solutionsReleased: {
      type: Boolean,
      default: false
    },
    userId: {
      type: Number,
      default: 0
    },
    questionId: {
      type: Number,
      default: 0
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    penalties: {
      type: Array,
      default: () => {
      }
    },
    smallTable: {
      type: Boolean,
      default: false
    },
    submissionArray: {
      type: Array,
      default: () => {
      }
    },
    questionSubmissionArray: {
      type: Array,
      default: () => {
      }
    },
    technology: {
      type: String,
      default: ''
    },
    scoringType: {
      type: String,
      default: ''
    },
    userRole: {
      type: Number,
      default: 3
    }
  },
  data: () => ({
    showFullHistory: false,
    submissionHistory: [],
    submissionHistoryOptions: [],
    questionSubmissionHistoryId: null,
    currentQuestionSubmissionArray: [],
    showCorrectAnswer: false
  }),
  async mounted () {
    await this.getSubmissionHistory()
    await this.getSubmissionArrayByHistory(this.questionSubmissionHistoryId)
  },
  methods: {
    async getSubmissionArrayByHistory (submissionHistoryId) {
      this.showFullHistory = submissionHistoryId === -1
      if (submissionHistoryId !== -1) {
        try {
          const { data } = await axios.post(`/api/submissions/submission-array/assignment/${this.assignmentId}/question/${this.questionId}`,
            { user_id: this.userId, submission_history_id: this.questionSubmissionHistoryId })
          if (data.type === 'error') {
            this.$noty.error(data.message)
          }
          this.currentQuestionSubmissionArray = []
          this.showCorrectAnswer = data.show_correct_answer
          await this.$nextTick(() => {
            this.currentQuestionSubmissionArray = data.submission_array
          })
        } catch (error) {
          this.error(error.message)
        }
      }
      if (this.renderMathJax) {
        await this.typesetMath()
      }
    },
    async getSubmissionHistory () {
      try {
        const { data } = await axios.post(`/api/submission-history/assignment/${this.assignmentId}/question/${this.questionId}`,
          { user_id: this.userId })
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
        this.submissionHistory = data.submission_history
        this.submissionHistoryOptions = []
        if (this.submissionHistory && this.submissionHistory.length) {
          this.questionSubmissionHistoryId = this.submissionHistory[0].id
          for (let i = 0; i < this.submissionHistory.length; i++) {
            const submissionHistory = this.submissionHistory[i]
            const text = i === 0 ? `${submissionHistory.created_at} (Current)` : submissionHistory.created_at
            this.submissionHistoryOptions.push({ text: text, value: submissionHistory.id })
          }
          this.submissionHistoryOptions.push({ text: '--------', disabled: true })
          this.submissionHistoryOptions.push({ text: 'Full History', value: -1 })
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>

<style scoped>

</style>
