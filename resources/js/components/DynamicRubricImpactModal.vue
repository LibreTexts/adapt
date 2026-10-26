<template>
  <b-modal :id="modalId"
           title="Review Rubric Changes"
           size="lg"
           no-close-on-backdrop
           @shown="emailStudents = false"
  >
    <div v-if="preview">
      <b-alert v-if="preview.names_hidden" show variant="info">
        {{ preview.names_hidden_message }}
      </b-alert>
      <p v-if="!preview.students_changed.length">
        No student's score will change.
      </p>
      <div v-else>
        <p>
          {{ preview.students_changed.length }}
          {{ preview.students_changed.length === 1 ? "student's score" : "students' scores" }}
          will change:
        </p>
        <b-table small
                 striped
                 :items="changedRows"
                 :fields="fields"
                 aria-label="Students whose scores will change"
        />
      </div>
      <div v-if="preview.deleted_items.length">
        <p class="font-weight-bold mb-1">
          Deleted criteria
        </p>
        <ul>
          <li v-for="deletedItem in preview.deleted_items" :key="`deleted-item-${deletedItem.id}`">
            "{{ deletedItem.title }}" is applied to {{ deletedItem.students }}
            {{ deletedItem.students === 1 ? 'student' : 'students' }} and will be removed from
            {{ deletedItem.students === 1 ? 'that student' : 'those students' }}.
          </li>
        </ul>
      </div>
      <div v-if="preview.students_not_affected_override.length">
        <p class="font-weight-bold mb-1">
          Not affected (has a score override)
        </p>
        <ul>
          <li v-for="(student, index) in preview.students_not_affected_override"
              :key="`not-affected-${index}`"
          >
            {{ student.name }}: stays at {{ student.old_score }} / {{ student.question_points }}
          </li>
        </ul>
      </div>
      <b-form-checkbox v-if="preview.scores_released && preview.students_changed.length"
                       v-model="emailStudents"
      >
        Email the students whose score changed
      </b-form-checkbox>
    </div>
    <template #modal-footer>
      <b-button size="sm" @click="$bvModal.hide(modalId)">
        Cancel
      </b-button>
      <b-button size="sm"
                variant="primary"
                :disabled="processing"
                @click="$emit('apply', emailStudents)"
      >
        <b-spinner v-if="processing" small type="grow"/>
        Apply Changes
      </b-button>
    </template>
  </b-modal>
</template>

<script>
export default {
  name: 'DynamicRubricImpactModal',
  props: {
    modalId: {
      type: String,
      default: 'modal-dynamic-rubric-impact'
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
    emailStudents: false,
    fields: [
      { key: 'name', label: 'Student' },
      { key: 'old_score', label: 'Old Score' },
      { key: 'new_score', label: 'New Score' }
    ]
  }),
  computed: {
    changedRows () {
      return this.preview.students_changed.map(student => ({
        name: student.name,
        old_score: `${student.old_score} / ${student.question_points}`,
        new_score: `${student.new_score} / ${student.question_points}`
      }))
    }
  }
}
</script>
