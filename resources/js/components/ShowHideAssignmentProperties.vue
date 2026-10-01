<template>
  <div>
    <div v-if="property ==='solutions_released'">
    <b-modal :id="`modal-confirm-release-assignment-solutions-with-non-released-questions-${assignment.id}`"
             title="Override non-released question solutions"
             no-close-on-esc
    >
      <p>
        You are about to release the solutions. In this assignment, some of your clicker questions can be viewed by your
        students but
        you are currently not sharing the solution with them.
      </p>
      <p>
        By releasing the solutions at the assignment level, the individual solutions
        will be released as well.
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide(`modal-confirm-release-assignment-solutions-with-non-released-questions-${assignment.id}`);assignment['solutions_released'] = false"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="handleSubmitShowHideAssignmentProperty()"
        >
          Release All Solutions
        </b-button>
      </template>
    </b-modal>
    </div>
    <div v-if="thingToHide">
      <b-modal :id="`modal-deactivate-auto-release-${assignment.id}-${property}`"
               :title="`Deactivate Auto-Release for ${assignment.name}`"
               no-close-on-esc
      >
        <p>
          You are about to manually hide your {{ thingToHide }}. {{ autoReleaseTimingMessage }}
        </p>
        <p>
          In addition to hiding your {{ thingToHide }} would you like to deactivate this auto-release?
        </p>
        <b-form-radio-group
          id="remove-auto-release"
          v-model="deactivateAutoRelease"
          class="mt-2"
          stacked
          label="Deactivate Auto-Release"
        >
          <b-form-radio value="1">
            Yes
          </b-form-radio>
          <b-form-radio value="0">
            No
          </b-form-radio>
        </b-form-radio-group>
        <template #modal-footer>
          <b-button
            size="sm"
            class="float-right"
            @click="$bvModal.hide(`modal-deactivate-auto-release-${assignment.id}-${property}`)"
          >
            Cancel
          </b-button>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="handleSubmitShowHideAssignmentProperty()"
          >
      Submit
          </b-button>
        </template>
      </b-modal>
    </div>
    <b-modal :id="`modal-show-assignment-statistics-${assignment.id}`"
             title="Show Assignment Statistics"
    >
      <p>
        It looks like you want to show the assignment statistics but your students can't view their own scores.
        Are you sure that you want to show the assignment statistics?
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide(`modal-show-assignment-statistics-${assignment.id}`)"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="submitShowHideAssignmentProperty('students_can_view_assignment_statistics')"
        >
          Yes, I want to show the assignment statistics.
        </b-button>
      </template>
    </b-modal>

    <b-modal :id="`modal-hide-scores-${assignment.id}-${property}`"
             title="Hide Scores"
    >
      <p>
        This is a "<strong>{{ assessmentType }}</strong>" assignment. Typically
        for this type of assignment, students get immediate feedback.
        Are you sure that you want to hide the scores?
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide(`modal-hide-scores-${assignment.id}-${property}`)"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="submitShowHideAssignmentProperty('show_scores')"
        >
          Yes, I want to hide the scores!
        </b-button>
      </template>
    </b-modal>

    <b-modal :id="`modal-hide-solutions-${assignment.id}`"
             title="Show Solutions"
    >
      <p>
        This is a "<strong>{{ assessmentType }}</strong>" assignment. By releasing the solutions, all students will be
        able to see them immediately,
        even if you chose "Automatic" as your option for Solutions Availability (see the Properties tab). Are you sure
        you want to show the solutions?
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide(`modal-hide-solutions-${assignment.id}`)"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="submitShowHideAssignmentProperty('solutions_released')"
        >
          Yes, I want to show the solutions!
        </b-button>
      </template>
    </b-modal>
    <div v-if="scoresAlwaysReleased">
      Always released
      <QuestionCircleTooltip :id="`scores-always-released-tooltip-${assignment.id}`" />
      <b-tooltip :target="`scores-always-released-tooltip-${assignment.id}`"
                 delay="250"
                 triggers="hover focus"
      >
        Scores are always released for {{ assignment.assessment_type }} assignments since students receive immediate
        feedback on each submission.
      </b-tooltip>
    </div>
    <div v-else>
      <b-form-checkbox
        :id="`${property}-${assignment.id}`"
        :key="property"
        v-model="assignment[property]"
        :name="`${property}`"
        value="1"
        unchecked-value="0"
        class="custom-checkbox"
        @change="initShowHideAssignmentProperty()"
      >
        Manual
      </b-form-checkbox>
      <b-form-checkbox
        :id="`auto-release-activated-${property}-${assignment.id}`"
        :key="`auto-release-activated-${property}`"
        v-model="assignment['auto_release_activated_' + property]"
        :name="`auto-release-activated-${property}`"
        value="1"
        :disabled="disabled"
        unchecked-value="0"
        @change="updateAutoReleaseActivated()"
      >
        Auto
      </b-form-checkbox>
    </div>
  </div>
</template>

<script>
import 'vue-loading-overlay/dist/vue-loading.css'
import axios from 'axios'
import QuestionCircleTooltip from './QuestionCircleTooltip.vue'

export default {
  name: 'ShowHideAssignmentProperties',
  components: { QuestionCircleTooltip },
  props: {
    assignment:
      {
        type: Object,
        default: function () {
        }
      },
    property: {
      type: String,
      default: ''
    }
  },
  data: () => ({
    disabled: false,
    toggleColors: window.config.toggleColors,
    assessmentType: '',
    deactivateAutoRelease: 1,
    thingToHide: '',
    autoReleaseTimingMessage: ''
  }),
  computed: {
    scoresAlwaysReleased () {
      return this.property === 'show_scores' && ['real time', 'learning tree'].includes(this.assignment.assessment_type)
    }
  },
  mounted () {
    this.assessmentType = this.assignment.assessment_type
    this.disabled = this.assignment.assessment_type === 'clicker' || !this.assignment['auto_release_' + this.property]
  },
  methods: {
    async updateAutoReleaseActivated () {
      this.assignment['auto_release_activated_' + this.property] = !this.assignment['auto_release_activated_' + this.property]
      try {
        const { data } = await axios.patch(`/api/auto-release/activated/${this.assignment.id}`, {
          property: this.property
        })
        if (data.type === 'error') {
          this.assignment['auto_release_activated_' + this.property] = !this.assignment['auto_release_activated_' + this.property]
        } else {
          data.message += this.addAutoReleaseMessage(this.property)
        }
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
        this.assignment['auto_release_activated_' + this.property] = !this.assignment['auto_release_activated_' + this.property]
      }
    },
    async initShowHideAssignmentProperty () {
      this.deactivateAutoRelease = 0
      if (this.property === 'solutions_released' && !this.assignment[this.property]) {
        const allSolutionsReleasedWhenClosed = await this.allSolutionsReleasedWhenClosed()
        if (allSolutionsReleasedWhenClosed === 'error') {
          return
        }
        if (!allSolutionsReleasedWhenClosed) {
          this.$bvModal.show(`modal-confirm-release-assignment-solutions-with-non-released-questions-${this.assignment.id}`)
          return
        }
      }
      await this.handleSubmitShowHideAssignmentProperty()
    },
    async allSolutionsReleasedWhenClosed () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignment.id}/all-solutions-released-when-closed`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return 'error'
        } else {
          return data.all_solutions_released_when_closed
        }
      } catch (error) {
        this.$noty.error(error.message)
        return 'error'
      }
    },
    async handleSubmitShowHideAssignmentProperty () {
      switch (this.property) {
        case ('shown'):
          await this.submitShowHideAssignmentProperty('shown')
          break
        case ('solutions_released'):
          this.assessmentType === 'real time' && !this.assignment.solutions_released && this.assignment.solutions_availability === 'automatic'
            ? this.$bvModal.show(`modal-hide-solutions-${this.assignment.id}`)
            : await this.submitShowHideAssignmentProperty('solutions_released')
          break
        case ('show_scores'):
          this.assessmentType !== 'delayed' && Boolean(this.assignment.show_scores)
            ? this.$bvModal.show(`modal-hide-scores-${this.assignment.id}-${this.property}`)
            : await this.submitShowHideAssignmentProperty('show_scores')
          break
        case ('students_can_view_assignment_statistics'):
          !this.assignment.students_can_view_assignment_statistics && !this.assignment.show_scores
            ? this.$bvModal.show(`modal-show-assignment-statistics-${this.assignment.id}`)
            : await this.submitShowHideAssignmentProperty('students_can_view_assignment_statistics')
          break
      }
    },
    addAutoReleaseMessage (property) {
      let message
      message = ''
      if (Boolean(this.assignment[property]) && this.assignment['auto_release_activated_' + property]) {
        message += '<br><br>Note that since this property is manually shown, auto-release has no effect.'
      }
      return message
    },
    async submitShowHideAssignmentProperty (property) {
      let url = ''
      let initModal = ''
      try {
        switch (property) {
          case ('shown'):
            url = `/api/assignments/${this.assignment.id}/show-assignment`
            break
          case ('solutions_released'):
            url = `/api/assignments/${this.assignment.id}/solutions-released`
            initModal = `modal-hide-solutions-${this.assignment.id}`
            break
          case ('show_scores'):
            url = `/api/assignments/${this.assignment.id}/show-scores`
            initModal = `modal-hide-scores-${this.assignment.id}`
            break
          case ('students_can_view_assignment_statistics'):
            url = `/api/assignments/${this.assignment.id}/show-assignment-statistics`
            initModal = `modal-show-assignment-statistics-${this.assignment.id}`
            break
          default:
            alert(`${property} is not a valid toggle property.`)
        }
        this.assignment[property] = !this.assignment[property]
        if (this.deactivateAutoRelease) {
          this.assignment['auto_release_activated_' + property] = '0'
        }
        const { data } = await axios.patch(url, { deactivate_auto_release: this.deactivateAutoRelease })
        if (data.type === 'success') {
          data.message += this.addAutoReleaseMessage(property)
        }
        this.$noty[data.type](data.message)

        if (data.type === 'error') {
          this.$bvModal.hide(initModal)
          this.$bvModal.hide(`modal-deactivate-auto-release-${this.assignment.id}-${this.property}`)
          this.assignment[property] = !this.assignment[property]
          if (this.deactivateAutoRelease) {
            this.assignment['auto_release_activated_' + property] = '1'
          }
          return false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.$bvModal.hide(initModal)
      this.$bvModal.hide(`modal-deactivate-auto-release-${this.assignment.id}-${this.property}`)
      this.$emit('refreshPage')
    }
  }
}
</script>
