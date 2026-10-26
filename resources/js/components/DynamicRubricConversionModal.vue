<template>
  <b-modal :id="modalId"
           :title="toStatic ? 'Convert to Static Scoring' : 'Convert to Dynamic Scoring'"
           size="lg"
           no-close-on-backdrop
           @shown="choice = null; emailStudents = false"
  >
    <div v-if="preview">
      <p class="mb-1">
        {{
          toStatic
            ? `Each criterion's points become a weight, scaled to this question's ${preview.question_points} points:`
            : 'Each criterion will be worth exactly these points:'
        }}
      </p>
      <ul>
        <li v-for="(item, index) in preview.converted_items" :key="`converted-item-${index}`">
          {{ item.title }}: {{ item.points }} point<span v-if="+item.points !== 1">s</span>
        </li>
      </ul>
      <b-alert v-if="toStatic && preview.dropped_criteria && preview.dropped_criteria.length" show variant="warning" class="small">
        Static rubrics don't allow 0-point criteria, so
        {{ preview.dropped_criteria.length === 1 ? 'this feedback-only criterion' : 'these feedback-only criteria' }}
        will be removed: <strong>{{ preview.dropped_criteria.join(', ') }}</strong>.
        No score changes because of this, but students will no longer see
        {{ preview.dropped_criteria.length === 1 ? 'it' : 'them' }} in their breakdown.
      </b-alert>
      <p>
        <span v-if="preview.number_clean">
          {{ preview.number_clean }} {{ preview.number_clean === 1 ? 'student keeps the same score' : 'students keep the same score' }}:
          their criteria convert exactly.
        </span>
        <span v-else-if="!preview.students_would_change.length">No student's score will change.</span>
      </p>
      <div v-if="preview.students_would_change.length">
        <p class="mb-1">
          For {{ preview.students_would_change.length }}
          {{ preview.students_would_change.length === 1 ? 'student' : 'students' }}, the
          {{ toStatic ? 'static breakdown' : 'dynamic criteria' }} would add up to a different number than their score.
          Their score only changes if you choose to clear it below.
        </p>
        <b-table small
                 striped
                 :items="wouldChangeRows"
                 :fields="fields"
                 aria-label="Students whose scores would change"
        >
          <template #cell(math)="row">
            <b-button size="sm" variant="link" class="p-0 text-nowrap" @click="row.toggleDetails">
              {{ row.detailsShowing ? 'Hide' : 'Show' }} the math
            </b-button>
          </template>
          <template #row-details="row">
            <div class="small px-2">
              <table class="table table-sm mb-1" :aria-label="`The math for ${row.item.name}`">
                <thead>
                <tr>
                  <th scope="col">Criterion</th>
                  <th scope="col" class="text-right">{{ toStatic ? 'Now (dynamic)' : 'Now (static)' }}</th>
                  <th scope="col" class="text-right">{{ toStatic ? 'In static' : 'In dynamic' }}</th>
                </tr>
                </thead>
                <tbody v-if="toStatic">
                <tr v-for="(criterion, index) in row.item.math.criteria" :key="`math-${index}`">
                  <td>
                    <b-icon :icon="criterion.applied ? 'check-circle-fill' : 'x-circle-fill'"
                            :variant="criterion.applied ? 'success' : 'danger'"
                            aria-hidden="true"
                    />
                    <span class="sr-only">{{ criterion.applied ? 'Received:' : 'Not received:' }}</span>
                    {{ criterion.title }}
                  </td>
                  <td class="text-right">{{ formatNumber(criterion.now_points) }}</td>
                  <td class="text-right">
                    <span v-if="criterion.removed">removed (0-point criterion)</span>
                    <span v-else-if="criterion.applied">
                      {{ formatNumber(criterion.now_points) }} × {{ row.item.math.scale }} = {{ formatNumber(criterion.static_points) }}
                    </span>
                    <span v-else>0</span>
                  </td>
                </tr>
                <tr v-if="+row.item.math.adjustment !== 0">
                  <td>Adjustment</td>
                  <td class="text-right">{{ signed(row.item.math.adjustment) }}</td>
                  <td class="text-right">none (static has no adjustment)</td>
                </tr>
                <tr class="font-weight-bold">
                  <td>Total</td>
                  <td class="text-right">{{ formatNumber(row.item.math.now_total) }}</td>
                  <td class="text-right">{{ formatNumber(row.item.math.converted_total) }}</td>
                </tr>
                </tbody>
                <tbody v-else>
                <tr v-for="(criterion, index) in row.item.math.criteria" :key="`math-${index}`">
                  <td>{{ criterion.title }}</td>
                  <td class="text-right">
                    {{ formatNumber(criterion.static_points) }} of {{ formatNumber(criterion.full_points) }}
                  </td>
                  <td class="text-right">
                    <span v-if="criterion.status === 'full'">{{ formatNumber(criterion.dynamic_points) }} (full credit: applied)</span>
                    <span v-else-if="criterion.status === 'partial'">0 (partial credit: not applied)</span>
                    <span v-else>0</span>
                  </td>
                </tr>
                <tr class="font-weight-bold">
                  <td>Total</td>
                  <td class="text-right">{{ formatNumber(row.item.math.now_total) }}</td>
                  <td class="text-right">{{ formatNumber(row.item.math.converted_total) }}</td>
                </tr>
                </tbody>
              </table>
              <div class="text-muted mb-2">
                <div v-for="(note, index) in mathNotes(row.item.math)" :key="`math-note-${index}`">{{ note }}</div>
              </div>
            </div>
          </template>
        </b-table>
        <b-form-group label="What should happen to these students?" label-class="font-weight-bold">
          <b-form-radio v-model="choice" name="conversion-choice" value="keep">
            <span v-if="toStatic">
              Keep their current scores and mark them <strong>Needs review</strong> in the Open Grader. Their breakdown
              shows full points for each criterion they had, so it won't add up to their score until a grader reviews it.
            </span>
            <span v-else>
              Keep their current scores and mark them <strong>Needs review</strong> in the Open Grader. Criteria they got
              full points on are applied, and an adjustment makes up the difference.
            </span>
          </b-form-radio>
          <b-form-radio v-model="choice" name="conversion-choice" value="clear" class="mt-2">
            Clear their scores so they can be regraded. They become ungraded; their feedback is kept.
          </b-form-radio>
        </b-form-group>
        <b-form-checkbox v-if="choice === 'clear' && preview.scores_released"
                         v-model="emailStudents"
        >
          Email the students whose scores are cleared
        </b-form-checkbox>
      </div>
      <div v-if="preview.students_not_affected_override.length" class="mt-2">
        <p class="font-weight-bold mb-1">
          Not affected (has a score override)
        </p>
        <ul>
          <li v-for="(student, index) in preview.students_not_affected_override"
              :key="`conversion-not-affected-${index}`"
          >
            {{ student.name }}: stays at {{ student.old_score }} / {{ student.question_points }}
          </li>
        </ul>
      </div>
    </div>
    <template #modal-footer>
      <b-button size="sm" @click="$bvModal.hide(modalId)">
        Cancel
      </b-button>
      <b-button size="sm"
                variant="primary"
                :disabled="processing || !canConvert"
                @click="$emit('apply', { choice: choice || 'keep', emailStudents: choice === 'clear' && emailStudents })"
      >
        <b-spinner v-if="processing" small type="grow"/>
        Convert
      </b-button>
    </template>
  </b-modal>
</template>

<script>
export default {
  name: 'DynamicRubricConversionModal',
  props: {
    modalId: {
      type: String,
      default: 'modal-dynamic-rubric-conversion'
    },
    preview: {
      type: Object,
      default: null
    },
    processing: {
      type: Boolean,
      default: false
    }
  },
  data: () => ({
    choice: null,
    emailStudents: false
  }),
  computed: {
    toStatic () {
      return Boolean(this.preview) && this.preview.direction === 'to_static'
    },
    canConvert () {
      // the instructor must choose when some students' scores would change
      return Boolean(this.preview) && (!this.preview.students_would_change.length || ['keep', 'clear'].includes(this.choice))
    },
    fields () {
      return [
        { key: 'name', label: 'Student' },
        { key: 'old_score', label: 'Score now' },
        { key: 'mapped_score', label: this.toStatic ? 'Static breakdown would add up to' : 'Dynamic criteria would add up to' },
        { key: 'reason', label: 'Why' },
        { key: 'math', label: '' }
      ]
    },
    wouldChangeRows () {
      return this.preview.students_would_change.map(student => ({
        name: student.name,
        old_score: `${student.old_score} / ${student.question_points}`,
        mapped_score: `${student.mapped_score} / ${student.question_points}`,
        reason: this.toStatic ? student.reason : this.toDynamicReason(student),
        math: student.math
      }))
    }
  },
  methods: {
    formatNumber (number) {
      return number === null || number === undefined ? '-' : Math.round(+number * 100) / 100
    },
    signed (number) {
      return `${+number > 0 ? '+' : ''}${this.formatNumber(number)}`
    },
    toDynamicReason (student) {
      const partial = student.partial_criteria
      return partial
        ? `Partial credit on ${partial} criteri${partial === 1 ? 'on' : 'a'}`
        : "Their score doesn't match their criteria"
    },
    mathNotes (math) {
      const notes = []
      if (this.toStatic) {
        notes.push(`Static scales every criterion to the question's points: ${this.formatNumber(math.scale_to)} ÷ ${this.formatNumber(math.scale_from)} (the criteria's total) = ×${math.scale}.`)
        if (math.now_total !== null && Math.abs(math.now_raw - math.now_total) >= 0.005 && !(+math.late_penalty_percent > 0)) {
          notes.push(`Now, the criteria and adjustment add up to ${this.formatNumber(math.now_raw)}; the score is ${this.formatNumber(math.now_total)} because it's capped at the question's points.`)
        }
      } else {
        const staticTotal = math.criteria.reduce((sum, criterion) => sum + +criterion.static_points, 0)
        if (math.now_total !== null && Math.abs(staticTotal - math.now_total) >= 0.005 && !(+math.late_penalty_percent > 0)) {
          notes.push(`Their static criteria add up to ${this.formatNumber(staticTotal)}, but their score was set to ${this.formatNumber(math.now_total)}.`)
        }
        if (+math.keep_adjustment !== 0) {
          notes.push(`If you keep their score, an adjustment of ${this.signed(math.keep_adjustment)} brings it back to ${this.formatNumber(math.now_total)}.`)
        }
      }
      if (+math.late_penalty_percent > 0) {
        notes.push(`Both totals include the ${this.formatNumber(math.late_penalty_percent)}% late penalty.`)
      }
      if (+math.auto_graded_score > 0) {
        notes.push(`These are the open-ended points; the ${this.formatNumber(math.auto_graded_score)} auto-graded points are added on top.`)
      }
      return notes
    }
  }
}
</script>
