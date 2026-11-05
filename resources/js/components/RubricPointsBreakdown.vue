<template>
  <div>
    <b-card-text>
      <table v-show="showTable" class="table table-striped" :class="user.role === 2 ? 'small' : ''"
             aria-label="Rubric Points Breakdown"
      >
        <thead>
        <tr v-show="user.role === 'instructor'">
          <span class="text-muted">Rubric points breakdown</span>
        </tr>
        <tr>
          <th scope="col" :style="+user.role === 2 ? '' : 'width:70%'">
            <span :style="+user.role === 2? 'font-size:12px;' : ''">Title</span>
          </th>
          <th scope="col" :style="user.role === 2 ? 'width:110px;font-size:12px' : ''">
            Max<br>
            Points
          </th>
          <th scope="col" v-show="showPointsAwardedInfo">
            Points<br>
            Awarded
          </th>
          <th scope="col" v-show="showPointsAwardedInfo">
            Percentage<br>
            Score
          </th>
        </tr>
        </thead>
        <tbody>
        <tr
          v-for="( rubricItem,rubricItemIndex) in rubricPointsBreakdown"
          :key="`rubric-criterion-${rubricItemIndex}`"
        >
          <td>
            {{ rubricItem.title }}
            <QuestionCircleTooltip v-show="rubricItem.description"
                                   :id="`rubric-item-tooltip-${rubricItemIndex}`"
            />
            <b-tooltip :target="`rubric-item-tooltip-${rubricItemIndex}`"
                       delay="250"
                       triggers="hover focus"
            >
              {{ rubricItem.description }}
            </b-tooltip>
          </td>
          <td>
            {{ originalRubricPoints[rubricItemIndex] }}
          </td>
          <td v-show="user.role === 3 && showPointsAwardedInfo || user.role !== 3">
            <div v-if="user.role === 3 && showPointsAwardedInfo">
              {{ rubricItem.points }}
            </div>
            <div v-if="user.role !== 3">
              <b-form-input
                v-model="rubricItem.points"
                type="text"
                size="sm"
                style="width:80px"
                placeholder=""
                required
                :class="getClass(rubricItem, rubricItemIndex)"
                @input="recomputeOpenEndedPoints"
              />
              <ErrorMessage
                v-if="rubricItem.points !== '' && roundToDecimalSigFig(+rubricItem.points) > roundToDecimalSigFig(originalRubricPoints[rubricItemIndex])"
                :message="`Max of ${roundToDecimalSigFig(originalRubricPoints[rubricItemIndex]) }`"
              />
            </div>
          </td>
          <td v-show="showPointsAwardedInfo">
            {{ roundToDecimalSigFig(100 * +rubricItem.points / originalRubricPoints[rubricItemIndex]) }}%
          </td>
        </tr>
        <tr style="font-weight: bold">
          <td role="rowheader">
            Total:
          </td>
          <td>{{ roundToDecimalSigFig(totalMaxPoints, 2) }}</td>
          <td v-show="showPointsAwardedInfo">{{ roundToDecimalSigFig(totalPointsGiven, 2) }}</td>
          <td v-show="showPointsAwardedInfo">{{ roundToDecimalSigFig(totalPercentage, 2) }}%</td>
        </tr>
        </tbody>
      </table>
    </b-card-text>
  </div>
</template>

<script>
import axios from 'axios'
import ErrorMessage from './ErrorMessage.vue'
import { roundToDecimalSigFig } from '../helpers/Math'
import { mapGetters } from 'vuex'

export default {
  name: 'RubricPointsBreakdown',
  components: { ErrorMessage },
  props: {
    rubricPointsBreakdownExists: {
      type: Boolean,
      default: false
    },
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
    originalRubric: {
      type: String,
      default: ''
    }
  },
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    totalPointsGiven () {
      return this.rubricPointsBreakdown.reduce((sum, item) => {
        return sum + Number(item.points || 0)
      }, 0)
    },
    totalMaxPoints () {
      return this.originalRubricPoints.reduce((sum, val) => sum + Number(val || 0), 0)
    },
    totalPercentage () {
      const max = this.totalMaxPoints
      return max > 0 ? (this.totalPointsGiven / max) * 100 : 0
    }
  },
  data: () => ({
    showPointsAwardedInfo: false,
    showTable: false,
    errors: [],
    rubricPointsBreakdown: [],
    originalRubricPoints: [],
    originalMaxPoints: 0
  }),
  async mounted () {
    await this.getRubricPointsBreakdown()
    this.showPointsAwardedInfo = this.user.role === 2 || (this.user.role === 3 && this.rubricPointsBreakdownExists)
    console.error('setting original rubric')
    console.error(this.originalRubric)
    const originalRubric = JSON.parse(this.originalRubric)
    const rubricItems = originalRubric.rubric_items
    this.originalMaxPoints = 0
    for (let i = 0; i < rubricItems.length; i++) {
      const points = +rubricItems[i].points
      this.originalMaxPoints += points
    }
    const scale = this.questionPoints / this.originalMaxPoints
    for (let i = 0; i < rubricItems.length; i++) {
      const points = roundToDecimalSigFig(+rubricItems[i].points * scale, 2)
      this.originalRubricPoints.push(points)
    }

    let rubricWithMaxes = []
    for (let i = 0; i < rubricItems.length; i++) {
      let pointsItem = rubricItems[i]
      pointsItem.points = this.originalRubricPoints[i]
      rubricWithMaxes.push(pointsItem.points)
    }
    this.$emit('setOriginalRubricWithMaxes', rubricWithMaxes)
    setTimeout(() => {
      this.showTable = true
    }, 750)
  },
  methods: {
    roundToDecimalSigFig,
    setRubricPointsBreakdown () {
      this.$emit('setRubricPointsBreakdown', this.rubricPointsBreakdown)
    },
    getClass (rubricItem, rubricItemIndex) {
      if (rubricItem.points !== '' && rubricItem.points !== null) {
        if (+rubricItem.points < 0) {
          return 'is-invalid'
        }
        return this.roundToDecimalSigFig(+rubricItem.points, 2) <= this.roundToDecimalSigFig(this.getMax(rubricItemIndex), 2) ? 'is-valid' : 'is-invalid'
      }
    },
    recomputeOpenEndedPoints () {
      let points = 0
      for (let i = 0; i <= this.rubricPointsBreakdown.length - 1; i++) {
        if (this.rubricPointsBreakdown[i].points !== '') {
          const itemPoints = this.rubricPointsBreakdown[i].points
          if (isNaN(itemPoints) || +itemPoints < 0 || +itemPoints > this.getMax(i)) {
            this.$emit('invalidRubricPoints')
            return false
          }
          points = points + +itemPoints
        }
      }
      this.$emit('updateOpenEndedSubmissionScore', this.rubricPointsBreakdown, this.roundToDecimalSigFig(points, 2))
    },
    getMax (rubricItemIndex) {
      const points = this.originalRubricPoints[rubricItemIndex]

      if (isNaN(points)) return false
      if (+points < 0) return false
      return +points
    },
    async getRubricPointsBreakdown () {
      try {
        const { data } = await axios.get(`/api/rubric-points-breakdown/assignment/${this.assignmentId}/question/${this.questionId}/user/${this.userId}`)
        if (data.type === 'success') {
          console.error('setting rubric points breakdown')
          this.rubricPointsBreakdown = JSON.parse(data.rubric_points_breakdown).rubric_items
          console.error(this.rubricPointsBreakdown)
          console.error('setting score input type')
          if (data.rubric_points_breakdown_exists) {
            this.$emit('setRubricPointsBreakdown', this.rubricPointsBreakdown)
          }
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
