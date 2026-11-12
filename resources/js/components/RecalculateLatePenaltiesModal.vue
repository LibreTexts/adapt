<template>
  <b-modal :id="modalId"
           title="Assignment Saved: Recalculate Late Penalties?"
           size="lg"
           no-close-on-backdrop
           no-close-on-esc
           hide-header-close
  >
    <div v-if="preview">
      <p>
        Your changes to <strong>{{ assignmentName }}</strong> are saved. Because the due date changed,
        late penalties change for
        {{ preview.students.length }} {{ preview.students.length === 1 ? 'student' : 'students' }}.
        Would you like to recalculate {{ preview.students.length === 1 ? 'their score' : 'their scores' }}?
      </p>
      <b-alert v-if="numDecreases" show variant="danger" class="small">
        {{ numDecreases }} {{ numDecreases === 1 ? "student's score goes" : "students' scores go" }}
        down, shown in red below.
      </b-alert>
      <b-table small
               striped
               :items="rows"
               :fields="fields"
               aria-label="Students whose scores will change"
      >
        <template #cell(new_score)="data">
          <span :class="{ 'text-danger font-weight-bold': data.item.decrease }">{{ data.value }}</span>
        </template>
        <template #cell(change)="data">
          <span :class="{ 'text-danger font-weight-bold': data.item.decrease }">{{ data.value }}</span>
        </template>
      </b-table>
      <p v-if="hasPendingChanges" class="small">
        Scores marked "pending approval" belong to submitted work that hasn't been approved yet; they'll
        count toward the HW score once it is.
      </p>
      <b-alert v-if="preview.num_skipped" show variant="info" class="small">
        {{ preview.num_skipped }} {{ preview.num_skipped === 1 ? 'score' : 'scores' }} will be left as
        {{ preview.num_skipped === 1 ? 'it is' : 'they are' }}: for example, a score with an override,
        or one that was regraded by hand. Adjust {{ preview.num_skipped === 1 ? 'it' : 'those' }}
        yourself if needed.
      </b-alert>
      <b-alert v-if="preview.submitted_after_final_deadline.length" show variant="warning" class="small">
        {{ preview.submitted_after_final_deadline.length === 1 ? 'This student' : 'These students' }}
        submitted after the final submission deadline. Their scores are kept and recalculated like the
        rest; adjust them yourself if needed.
        <ul class="mb-0 mt-1">
          <li v-for="(student, index) in preview.submitted_after_final_deadline"
              :key="`after-final-deadline-${index}`"
          >
            {{ student.name }}: {{ student.questions.map(question => `Q${question}`).join(', ') }}
          </li>
        </ul>
      </b-alert>
      <b-alert v-if="preview.num_hand_applied_late_penalties" show variant="info" class="small">
        {{ preview.num_hand_applied_late_penalties }} open-ended
        {{ preview.num_hand_applied_late_penalties === 1 ? 'submission has' : 'submissions have' }}
        a late penalty applied by a grader. Those aren't changed; review them in the grader if needed.
      </b-alert>
      <p v-if="preview.lms_grade_passback === 'automatic'" class="small">
        The new scores will be passed back to your LMS.
      </p>
      <p v-if="preview.lms_grade_passback === 'manual'" class="small">
        Your LMS grade passback is manual, so pass the grades back when you're ready.
      </p>
    </div>
    <template #modal-footer>
      <b-button size="sm" @click="$bvModal.hide(modalId)">
        Keep Current Scores
      </b-button>
      <b-button size="sm"
                variant="primary"
                :disabled="processing"
                @click="recalculate"
      >
        <b-spinner v-if="processing" small type="grow"/>
        Recalculate Scores
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import axios from 'axios'

/**
 * After an assignment save reports check_late_penalties, the page calls check(). The modal opens only
 * if recalculating would change some scores, and emits "recalculated" once the new scores are saved.
 */
export default {
  name: 'RecalculateLatePenaltiesModal',
  props: {
    modalId: {
      type: String,
      default: 'modal-recalculate-late-penalties'
    }
  },
  data: () => ({
    assignmentId: 0,
    assignmentName: '',
    preview: null,
    processing: false,
    fields: [
      { key: 'name', label: 'Student' },
      { key: 'current_score', label: 'HW Score Now' },
      { key: 'new_score', label: 'New HW Score' },
      { key: 'change', label: 'Change' }
    ]
  }),
  computed: {
    rows () {
      return this.preview.students.map(student => ({
        name: student.name,
        current_score: student.current_score,
        new_score: student.new_score,
        change: this.describeChange(student),
        decrease: student.change < 0 || student.pending_change < 0
      }))
    },
    numDecreases () {
      return this.rows.filter(row => row.decrease).length
    },
    hasPendingChanges () {
      return this.preview.students.some(student => student.pending_change)
    }
  },
  methods: {
    async check (assignmentId, assignmentName) {
      this.assignmentId = assignmentId
      this.assignmentName = assignmentName
      try {
        const { data } = await axios.get(`/api/assignments/${assignmentId}/late-penalty-recalculation`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return
        }
        if (data.students.length) {
          this.preview = data
          this.$bvModal.show(this.modalId)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async recalculate () {
      this.processing = true
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/late-penalty-recalculation`)
        if (data.type === 'success') {
          // The recalculation runs in the background; the server says when to expect it.
          const numStudents = this.preview.students.length
          const message = `Recalculating late penalties for ${numStudents} ${numStudents === 1 ? 'student' : 'students'}. ${data.message}`
          this.$noty.success(message, { timeout: 8000 })
        } else {
          this.$noty[data.type](data.message, { timeout: 8000 })
        }
        if (data.type !== 'error') {
          this.$bvModal.hide(this.modalId)
          this.$emit('recalculated')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processing = false
    },
    describeChange (student) {
      const signed = value => (value > 0 ? '+' : '') + value
      let change = student.change ? signed(student.change) : '0'
      if (student.pending_change) {
        change += ` (pending approval: ${signed(student.pending_change)})`
      }
      return change
    }
  }
}
</script>
