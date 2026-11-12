<template>
  <div>
    <p v-if="!state.exists" class="small text-muted">
      {{
        scoresReleased
          ? 'You have not been scored with this rubric yet.'
          : 'The criteria you received will be checked once your instructor releases scores.'
      }}
    </p>
    <p class="small mb-2" data-testid="checked-items-explanation">
      {{ explanation }}
    </p>
    <table class="table table-striped mb-0 student-rubric-table" aria-label="Rubric">
      <thead>
      <tr>
        <th scope="col">
          Criterion
        </th>
        <th scope="col" class="text-right" style="width:140px">
          {{ pointsHeading }}
        </th>
      </tr>
      </thead>
      <tbody>
      <tr v-if="takeOffPoints && state.exists && total.outOf !== null">
        <td>Starting points (full credit)</td>
        <td class="text-right">
          {{ formatNumber(total.outOf) }}
        </td>
      </tr>
      <tr v-for="rubricItem in rubricItems" :key="`student-rubric-item-${rubricItem.id}`">
        <td>
          <span v-if="state.exists" class="mr-1">
            <span v-if="takeOffPoints && isApplied(rubricItem.id)">
              <b-icon icon="dash-circle-fill" variant="danger" aria-hidden="true"/>
              <span class="sr-only">Points taken off:</span>
            </span>
            <span v-else-if="takeOffPoints">
              <b-icon icon="check-circle-fill" variant="success" aria-hidden="true"/>
              <span class="sr-only">No points taken off:</span>
            </span>
            <span v-else-if="isApplied(rubricItem.id)">
              <b-icon icon="check-circle-fill" variant="success" aria-hidden="true"/>
              <span class="sr-only">Received:</span>
            </span>
            <span v-else>
              <b-icon icon="x-circle-fill" variant="danger" aria-hidden="true"/>
              <span class="sr-only">Not received:</span>
            </span>
          </span>
          {{ rubricItem.title }}
          <div v-if="rubricItem.description" class="small text-muted">
            {{ rubricItem.description }}
          </div>
        </td>
        <td class="text-right">
          <span v-if="state.exists && takeOffPoints" :class="{ 'text-danger': isApplied(rubricItem.id) }">
            {{ isApplied(rubricItem.id) ? `−${formatNumber(rubricItem.points)}` : 0 }}
          </span>
          <span v-else-if="state.exists">
            {{ isApplied(rubricItem.id) ? formatNumber(rubricItem.points) : 0 }} / {{ formatNumber(rubricItem.points) }}
          </span>
          <span v-else>{{ takeOffPoints ? '−' : '+' }}{{ formatNumber(rubricItem.points) }}</span>
        </td>
      </tr>
      <tr v-if="state.exists && +state.adjustment !== 0">
        <td>Adjustment</td>
        <td class="text-right">
          {{ +state.adjustment > 0 ? '+' : '' }}{{ formatNumber(state.adjustment) }}
        </td>
      </tr>
      </tbody>
      <tfoot v-if="state.exists && total.final !== null">
      <tr class="font-weight-bold">
        <td>Total</td>
        <td class="text-right">
          {{ formatNumber(total.final) }}<span v-if="total.outOf !== null"> / {{ formatNumber(total.outOf) }}</span>
        </td>
      </tr>
      </tfoot>
    </table>
    <div v-if="state.exists && notes.length" class="small text-muted mt-2">
      <div v-for="(note, index) in notes" :key="`rubric-total-note-${index}`">
        {{ note }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DynamicRubricStudentView',
  props: {
    rubric: {
      type: String,
      default: ''
    },
    state: {
      type: Object,
      default: () => ({ exists: false, applied_item_ids: [], adjustment: 0 })
    },
    scoresReleased: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    parsedRubric () {
      try {
        return JSON.parse(this.rubric) || {}
      } catch (error) {
        return {}
      }
    },
    rubricItems () {
      return this.parsedRubric.rubric_items || []
    },
    // checked items take points off full credit instead of adding them up from 0
    takeOffPoints () {
      return this.parsedRubric.checked_items === 'take_off'
    },
    explanation () {
      if (!this.takeOffPoints) {
        return 'Points are added for each criterion you received.'
      }
      const outOf = this.total.outOf
      return outOf !== null
        ? `You started with full credit (${this.formatNumber(outOf)} ${this.formatNumber(outOf) === 1 ? 'point' : 'points'}). Points are taken off for each criterion marked below.`
        : 'You start with full credit. Points are taken off for each criterion marked below.'
    },
    pointsHeading () {
      if (this.takeOffPoints) {
        return this.state.exists ? 'Points Taken Off' : 'Points'
      }
      return this.state.exists ? 'Points Earned' : 'Points'
    },
    // the server's calculation (cap and late penalty included); a plain sum if it isn't there
    total () {
      const score = this.state.score
      if (score) {
        return { final: +score.final, outOf: +score.out_of }
      }
      if (this.takeOffPoints) {
        return { final: null, outOf: null } // full credit comes from the server
      }
      const earned = this.rubricItems.filter(item => this.isApplied(item.id)).reduce((sum, item) => sum + +item.points, 0)
      return { final: Math.max(0, earned + +(this.state.adjustment || 0)), outOf: null }
    },
    notes () {
      const score = this.state.score
      if (!score) {
        return []
      }
      const notes = []
      if (score.capped) {
        notes.push(this.takeOffPoints
          ? `Your total would be more than the ${this.formatNumber(score.out_of)} points available, so it's capped at ${this.formatNumber(score.out_of)}.`
          : `Your criteria add up to more than the ${this.formatNumber(score.out_of)} points available, so the total is capped at ${this.formatNumber(score.out_of)}.`)
      }
      if (+score.late_penalty_percent > 0) {
        notes.push(`The total includes a ${this.formatNumber(score.late_penalty_percent)}% late penalty.`)
      }
      if (score.has_override) {
        notes.push('Your instructor set your score for this question directly, so your score may differ from this total.')
      }
      return notes
    }
  },
  methods: {
    formatNumber (number) {
      return Math.round(+number * 100) / 100
    },
    isApplied (itemId) {
      return (this.state.applied_item_ids || []).map(String).includes(String(itemId))
    }
  }
}
</script>

<style scoped>
/* the dialog's title already has a line under it */
.student-rubric-table thead th {
  border-top: 0;
}
</style>
