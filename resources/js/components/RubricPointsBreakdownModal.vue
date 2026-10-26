<template>
  <span>
  <b-modal id="modal-rubric-points-breakdown"
           size="lg"
           :title="rubricPointsBreakdownExists ? 'Rubric Points Breakdown' : 'Rubric'"
           hide-footer
  >
    <DynamicRubricStudentView
      v-if="isDynamic"
      :rubric="rubric"
      :state="dynamicRubricState"
      :scores-released="scoresReleased"
    />
    <RubricPointsBreakdown
      v-else
      :user-id="userId"
      :assignment-id="+assignmentId"
      :original-rubric="rubric"
      :question-id="questionId"
      :question-points="questionPoints"
      :rubric-points-breakdown-exists="rubricPointsBreakdownExists"
      @setScoreInputType="setScoreInputType"
    />
  </b-modal>
  <b-button size="sm"
            variant="info"
            v-show="rubricShown"
            @click="$bvModal.show('modal-rubric-points-breakdown')"
  >View Rubric</b-button>
    </span>
</template>

<script>
import RubricPointsBreakdown from './RubricPointsBreakdown.vue'
import DynamicRubricStudentView from './DynamicRubricStudentView.vue'
import axios from 'axios'

export default {
  name: 'RubricPointsBreakdownModal',
  components: { RubricPointsBreakdown, DynamicRubricStudentView },
  props: {
    questionPoints: {
      type: Number,
      default: 0
    },
    userId: {
      type: Number,
      default: 0
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    questionId: {
      type: Number,
      default: 0
    },
    rubric: {
      type: String,
      default: ''
    }
  },
  data: () => ({
    rubricShown: false,
    rubricPointsBreakdownExists: false,
    scoreInputType: null,
    // dynamic rubrics: the server only sends applied criteria once scores are released
    isDynamic: false,
    dynamicRubricState: { exists: false, applied_item_ids: [], adjustment: 0 },
    scoresReleased: false
  }),
  mounted () {
    this.getRubricPointsBreakdown()
  },
  methods: {
    setScoreInputType (scoreInputType) {
      this.scoreInputType = scoreInputType
    },
    async getRubricPointsBreakdown () {
      try {
        const { data } = await axios.get(`/api/rubric-points-breakdown/assignment/${this.assignmentId}/question/${this.questionId}/user/${this.userId}`)
        if (data.type === 'success') {
          this.rubricPointsBreakdownExists = data.rubric_points_breakdown_exists
          this.rubricShown = data.rubric_shown
          this.isDynamic = Boolean(data.is_dynamic)
          this.dynamicRubricState = data.dynamic_rubric_state || this.dynamicRubricState
          this.scoresReleased = Boolean(data.scores_released)
        } else {
          this.$noty.error(data.message)
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
