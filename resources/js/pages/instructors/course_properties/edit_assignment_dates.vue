<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-shift-dates'"/>
    <b-modal id="modal-shifting-period-examples"
             title="Shifting Period Examples"
             hide-footer
    >
      <p style="font-size: 0.8rem">
        The shifting period should be a "human readable" period of time. Negative signs
        indicate moving the time backwards.
      </p>
      <table class="table table-striped table-sm" style="font-size: 0.8rem">
        <thead>
        <tr>
          <th scope="col">
            Shifting Period
          </th>
          <th scope="col">
            Result
          </th>
        </tr>
        </thead>
        <tbody>
        <tr>
          <td>
            3 days and 1 hour
          </td>
          <td> 3 days and 1 hour forward</td>
        </tr>
        <tr>
          <td>
            2 days and -1 hour
          </td>
          <td> 1 day and 23 hours forward</td>
        </tr>
        <tr>
          <td>
            -3 days and -1 hour
          </td>
          <td> 3 days back and 1 hour back</td>
        </tr>
        </tbody>
      </table>
    </b-modal>
    <b-modal id="modal-shift-dates"
             title="Shift Dates"
             size="xl"
             no-close-on-backdrop
    >
      <b-form-radio-group
        v-model="shiftDatesForm.shifting_method"
        class="mb-3"
        label="Shifting Method"
        stacked
        @input="resetShiftDatesForm"
      >
        <b-form-radio
          name="shifting-method"
          value="first available on"
        >
          Provide a new First Available On and shift all other assignments based on the difference between the two
          Available Ons
        </b-form-radio>
        <b-form-radio
          name="shifting-method"
          value="period of time"
        >
          A period of time such as 1 hour or -10 days
        </b-form-radio>
        <b-form-radio
          name="shifting-method"
          value="assignment date property"
        >
          Provide a single common date for the Available On, the Due Date, or the Final Submission
        </b-form-radio>
      </b-form-radio-group>

      <div v-show="['first available on','assignment date property'].includes(shiftDatesForm.shifting_method)">
        <b-form-group
          :label-cols-sm="shiftDatesForm.shifting_method === 'assignment date property' ? 4 :3"
          :label-cols-lg="shiftDatesForm.shifting_method === 'assignment date property' ? 3 :2"
          label-for="date"
          label-align="end"
        >
          <template v-slot:label>
            <div v-if="shiftDatesForm.shifting_method === 'assignment date property'" class="pb-2">
              <b-form-select v-model="assignmentDateProperty"
                             :options="assignmentDatePropertyOptions"
                             size="sm"
                             @change="assignmentDatePropertyError = ''"
              />
              <ErrorMessage :message="assignmentDatePropertyError"/>
            </div>
            <div v-if="shiftDatesForm.shifting_method === 'first available on'">
              First Available On
            </div>
          </template>
          <b-form-row>
            <b-col lg="6">
              <b-form-datepicker
                id="date"
                :key="`date-key-${dateTimeKey}`"
                v-model="changeDateForm.date"
                required
                tabindex="0"
                :class="{ 'is-invalid': changeDateForm.errors.has('date') }"
                class="datepicker"
                @shown="changeDateForm.errors.clear('date')"
              />
              <has-error :form="changeDateForm" field="date"/>
            </b-col>
            <b-col>
              <vue-timepicker id="time"
                              :key="`time-key-${dateTimeKey}`"
                              v-model="changeDateForm.time"
                              format="h:mm A"
                              manual-input
                              drop-direction="up"
                              :class="{ 'is-invalid': changeDateForm.errors.has('date') }"
                              input-class="custom-timepicker-class"
              >
                <template v-slot:icon>
                  <b-icon-clock/>
                </template>
              </vue-timepicker>
              <has-error :form="changeDateForm" field="time"/>
            </b-col>
            <b-col>
              <div class="mt-1">
                <b-button variant="info" size="sm" @click="previewShiftDates">
                  Preview
                </b-button>
                <b-button variant="info" size="sm" @click="resetDates">
                  Reset
                </b-button>
                <b-button variant="primary" size="sm" @click="saveShiftDates">
                  Save
                </b-button>
              </div>
            </b-col>
          </b-form-row>
        </b-form-group>
      </div>
      <b-form-group
        v-show="shiftDatesForm.shifting_method === 'period of time'"
        label-cols-sm="3"
        label-cols-lg="2"
        label-for="shift-by"
        label-align="center"
        label="Shift by"
      >
        <template v-slot:label>
          Shifting Period
          <span @mouseover="showShiftingPeriodExamplesModal()" @mouseout="mouseOverShiftingPeriodExamples = false">
            <QuestionCircleTooltip/>
          </span>
        </template>
        <b-form-row>
          <b-col lg="3">
            <b-form-input
              id="shift-by"
              v-model="shiftDatesForm.shift_by"
              required
              type="text"
              :class="{ 'is-invalid': shiftDatesForm.errors.has('shift_by') }"
              @keydown="shiftDatesForm.errors.clear('shift_by')"
            />
            <has-error :form="shiftDatesForm" field="shift_by"/>
          </b-col>
          <b-col>
            <div class="mt-1">
              <b-button variant="info" size="sm" @click="previewShiftDates">
                Preview
              </b-button>
              <b-button variant="info" size="sm" @click="resetDates">
                Reset
              </b-button>
              <b-button variant="primary" size="sm" @click="saveShiftDates">
                Save
              </b-button>
            </div>
          </b-col>
        </b-form-row>
      </b-form-group>
      <table class="table table-striped table-sm" style="font-size: 0.8rem">
        <thead>
        <tr>
          <th scope="col">
            Name
          </th>
          <th scope="col">
            Available On
          </th>
          <th scope="col">
            Due Date
          </th>
          <th scope="col">
            Final Submission Deadline
          </th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="(assignmentDate, assignmentDateIndex) in chosenAssignments"
            :key="`assignment-dates-${assignmentDateIndex}`"
        >
          <td>
            {{ assignmentDate.name }}
          </td>
          <td>{{ formatDate(assignmentDate.available_from) }}</td>
          <td>{{ formatDate(assignmentDate.due) }}</td>
          <td>{{ formatDate(assignmentDate.final_submission_deadline) }}</td>
        </tr>
        </tbody>
      </table>
      <template #modal-footer>
        <b-button variant="primary" size="sm" class="float-right" @click="saveShiftDates">
          Save
        </b-button>
        <b-button size="sm" class="float-right" @click="$bvModal.hide('modal-shift-dates')">
          Cancel
        </b-button>
      </template>
    </b-modal>
    <b-card header="default" header-html="<h2 class=&quot;h7&quot;>Edit Assignment Dates</h2>">
      <b-card-text>
        <p class="mb-2">
          You can shift dates in bulk by selecting any of the assignments below. The assigments listed below are ones
          which have been
          assigned to Everybody and are non-clicker assignments.
        </p>
        <div class="float-right mb-2">
          <b-button size="sm" variant="primary" @click="openShiftDatesModal">
            Shift Dates
          </b-button>
        </div>
        <div>
          <table class="table table-striped table-sm" style="font-size: 0.8rem">
            <thead>
            <tr>
              <th scope="col" style="width:300px">
                <b-form-checkbox id="select-all"
                                 @input="selectAll()"
                >
                  Name
                </b-form-checkbox>
              </th>
              <th scope="col">
                Available On
              </th>
              <th scope="col">
                Due Date
              </th>
              <th scope="col">
                Final Submission Deadline
              </th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(assignmentDate, assignmentDateIndex) in assignmentDates"
                :key="`assignment-dates-${assignmentDateIndex}`"
            >
              <td>
                <input v-model="selectedAssignmentIds"
                       type="checkbox"
                       :value="assignmentDate.assignment_id"
                       class="selected-assignment-id"
                > {{ assignmentDate.name }}
              </td>
              <td>{{ formatDate(assignmentDate.available_from) }}</td>
              <td>{{ formatDate(assignmentDate.due) }}</td>
              <td>{{ formatDate(assignmentDate.final_submission_deadline, true) }}</td>
            </tr>
            </tbody>
          </table>
        </div>
      </b-card-text>
    </b-card>
  </div>
</template>

<script>
import axios from 'axios'
import Form from 'vform'
import AllFormErrors from '~/components/AllFormErrors.vue'
import VueTimepicker from 'vue2-timepicker'
import 'vue2-timepicker/dist/VueTimepicker.css'
import { mapGetters } from 'vuex'
import ErrorMessage from '../../../components/ErrorMessage.vue'

export default {
  components: { ErrorMessage, AllFormErrors, VueTimepicker },
  data: () => ({
    hitPreview: false,
    showAssignmentsTable: true,
    dateTimeKey: 0,
    assignmentDatePropertyError: '',
    assignmentDateProperty: null,
    assignmentDatePropertyOptions: [
      {
        text: 'Choose a Property', value: null
      },
      {
        text: 'Available On', value: 'available_from'
      },
      {
        text: 'Due Date', value: 'due'
      },
      {
        text: 'Final Submission Deadline', value: 'final_submission_deadline'
      }
    ],
    offsetDifference: 0,
    changeDateForm: new Form({
      date: '',
      time: ''
    }),
    allFormErrors: [],
    courseId: 0,
    mouseOverShiftingPeriodExamples: false,
    chosenAssignments: [],
    assignmentDates: [],
    selectedAssignmentIds: [],
    shiftDatesForm: new Form({
      shift_by: '',
      offset_difference: 0,
      shifting_method: 'first available on'
    })
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  mounted () {
    this.courseId = parseInt(this.$route.params.courseId)
    this.getAssignmentDates()
  },
  methods: {
    resetShiftDatesForm (option) {
      this.$nextTick(() => {
        this.assignmentDateProperty = null
        this.shiftDatesForm = new Form({
          shift_by: '',
          offset_difference: 0,
          shifting_method: option
        })
        this.changeDateForm = new Form({
          date: '',
          time: ''
        })
      })
      this.dateTimeKey++
    },
    showShiftingPeriodExamplesModal () {
      this.mouseOverShiftingPeriodExamples = true
      setTimeout(() => {
        if (this.mouseOverShiftingPeriodExamples) {
          this.$bvModal.show('modal-shifting-period-examples')
        }
      }, 500)
    },
    resetDates () {
      this.hitPreview = false
      this.shiftDatesForm.shift_by = null
      this.chosenAssignments = this.assignmentDates.filter(assignment => this.selectedAssignmentIds.includes(assignment.assignment_id))
      this.$noty.success('The dates have been reset.')
    },
    async saveShiftDates () {
      this.changeDateForm.errors.clear()
      if (!this.preValidateForm()) {
        return
      }
      this.hitPreview = false
      try {
        this.shiftDatesForm.assignment_ids = this.selectedAssignmentIds
        this.shiftDatesForm.assignment_date_property = this.assignmentDateProperty
        this.shiftDatesForm.change_date_form = this.changeDateForm
        const { data } = await this.shiftDatesForm.post(`/api/assignments/${this.courseId}/shift-dates`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          await this.getAssignmentDates()
          if (this.shiftDatesForm.shifting_method !== 'assignment date property') {
            this.$bvModal.hide('modal-shift-dates')
          } else {
            this.chosenAssignments = this.assignmentDates.filter(assignment => this.selectedAssignmentIds.includes(assignment.assignment_id))
          }
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.shiftDatesForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-shift-dates')
        }
      }
    },
    getChosenDateTimeMoment () {
      const { date, time } = this.changeDateForm
      if (!date || !time) {
        return null
      }
      const time24 = this.$moment(time, ['h:mm A']).format('HH:mm')
      const chosenMoment = this.$moment(`${date} ${time24}`)
      return chosenMoment.isValid() ? chosenMoment : null
    },
    showInvalidDateTimeError () {
      const errorMessage = 'Please be sure to enter a valid date and time.'
      this.changeDateForm.errors.set('date', errorMessage)
      this.allFormErrors = [errorMessage]
      this.$bvModal.show('modal-form-errors-shift-dates')
    },
    getEarliestSelectedAvailableFrom () {
      // use the saved dates (not chosenAssignments, which holds previewed dates after a preview)
      // and the earliest of the selected assignments rather than whichever row happens to come first
      const availableFroms = this.assignmentDates
        .filter(assignment => this.selectedAssignmentIds.includes(assignment.assignment_id))
        .map(assignment => this.$moment(assignment.available_from))
        .filter(availableFrom => availableFrom.isValid())
      return availableFroms.length ? this.$moment.min(availableFroms) : null
    },
    computeShiftByForDate () {
      const chosenMoment = this.getChosenDateTimeMoment()
      const earliestAvailableFrom = this.getEarliestSelectedAvailableFrom()
      if (chosenMoment && earliestAvailableFrom) {
        const firstAvailableMoment = chosenMoment
        const availableFromMoment = earliestAvailableFrom
        const firstAvailableOffset = firstAvailableMoment.utcOffset()
        const availableFromOffset = availableFromMoment.utcOffset()

        this.shiftDatesForm.offset_difference = firstAvailableOffset - availableFromOffset
        this.differenceInMinutes = firstAvailableMoment.diff(availableFromMoment, 'minutes')
        if (this.shiftDatesForm.offset_difference === 60 || this.shiftDatesForm.offset_difference === -60) {
          this.differenceInMinutes = firstAvailableMoment.diff(availableFromMoment, 'minutes') + this.shiftDatesForm.offset_difference
        } else {
          this.differenceInMinutes = firstAvailableMoment.diff(availableFromMoment, 'minutes')
        }
        this.shiftDatesForm.shift_by = `${this.differenceInMinutes} minutes`
      } else {
        this.showInvalidDateTimeError()
        return false
      }
      return true
    },
    preValidateForm () {
      if (this.shiftDatesForm.shifting_method === 'assignment date property') {
        if (this.chosenAssignments.filter(item => !item.final_submission_deadline).length
          && this.assignmentDateProperty === 'final_submission_deadline') {
          this.assignmentDatePropertyError = 'The Final Submission Deadline is not applicable for at least one of your assignments.'
          return false
        }
        if (!this.assignmentDateProperty) {
          this.assignmentDatePropertyError = 'Please choose one of the assignment properties.'
          return false
        }
      }
      if (this.shiftDatesForm.shifting_method === 'first available on') {
        if (!this.computeShiftByForDate()) {
          return false
        }
      }
      if (this.shiftDatesForm.shifting_method === 'assignment date property') {
        // the date is applied directly, so there's no shift to compute or send
        this.shiftDatesForm.shift_by = ''
        this.shiftDatesForm.offset_difference = 0
        if (!this.getChosenDateTimeMoment()) {
          this.showInvalidDateTimeError()
          return false
        }
      }
      return true
    },
    async previewShiftDates () {
      this.changeDateForm.errors.clear()
      if (!this.preValidateForm()) {
        return
      }
      if (this.shiftDatesForm.shifting_method === 'assignment date property' && this.hitPreview) {
        this.$noty.info('Please either save your previewed changes or reset before changing the timings again.')
        return
      }
      this.hitPreview = true

      this.chosenAssignments = this.assignmentDates.filter(assignment => this.selectedAssignmentIds.includes(assignment.assignment_id))
      try {
        const { data } = await axios.patch('/api/assignments/preview-shift-dates', {
          shift_by: this.shiftDatesForm.shift_by,
          chosen_assignments: this.chosenAssignments,
          assignment_date_property: this.assignmentDateProperty,
          shifting_method: this.shiftDatesForm.shifting_method,
          change_date_form: this.changeDateForm
        })
        if (data.type === 'info') {
          this.chosenAssignments = data.preview_shift_dates
          this.$noty.info(data.message)
        } else {
          this.shiftDatesForm.errors.set('shift_by', data.message)
          return false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    openShiftDatesModal () {
      this.shiftDatesForm.shift_by = null
      this.chosenAssignments = this.assignmentDates.filter(assignment => this.selectedAssignmentIds.includes(assignment.assignment_id))
      if (!this.chosenAssignments.length) {
        this.$noty.info('Please first choose at least one assignment.')
        return
      }
      this.$bvModal.show('modal-shift-dates')
      const option = 'first available on'
      this.resetShiftDatesForm(option)
    },
    selectAll () {
      this.selectedAssignmentIds = []
      let checkboxes = document.getElementsByClassName('selected-assignment-id')
      if (document.getElementById('select-all').checked) {
        for (let checkbox of checkboxes) {
          this.selectedAssignmentIds.push(parseInt(checkbox.value))
          checkbox.checked = true
        }
      }
    },
    formatDate (date, isFinalSubmissionDeadline = false) {
      if (date) {
        return this.$moment(date).format('ddd, M/D/YY h:mm A')
      }
      return isFinalSubmissionDeadline ? 'N/A - No late submissions' : 'N/A'
    },
    async getAssignmentDates () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.courseId}/dates`)
        if (data.type === 'success') {
          this.assignmentDates = data.assignment_dates
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
