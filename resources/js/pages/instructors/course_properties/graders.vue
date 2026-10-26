<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-invite-graders'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-edit-sections'"/>
    <b-modal
      id="modal-confirm-remove"
      ref="modal"
      title="Remove Grader"
    >
      <p>
        Are you sure you would like to remove this grader? Once removed, they will no longer be able to grade
        for you unless you invite them back.
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="cancelRemoveGrader"
        >
          Cancel
        </b-button>
        <b-button
          variant="danger"
          size="sm"
          class="float-right"
          @click="submitRemoveGrader"
        >
          Yes, remove this grader!
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-edit-sections"
      ref="modal"
      title="Edit Sections"

    >
      <b-form ref="form">
        Choose individual sections or <a href="#" @click="selectAllSections">select all</a>:
        <b-form-checkbox-group
          v-model="sectionsForm.selected_sections"
          :options="sectionOptions"
          :class="{ 'is-invalid': sectionsForm.errors.has('selected_sections') }"
          name="sections"
          @keydown="sectionsForm.errors.clear('selected_sections')"
        />
        <has-error :form="sectionsForm" field="selected_sections"/>
      </b-form>
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="submitEditSections"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-invite-grader"
      ref="modal"
      title="Invite Grader"

    >
      <RequiredText/>
      <b-form ref="form">
        <b-form-group
          label-cols-sm="3"
          label-cols-lg="2"
          label="Email*"
          label-for="grader_email"
        >
          <b-form-input
            id="grader_email"
            v-model="graderForm.email"
            required
            placeholder="Email Address"
            type="text"
            :class="{ 'is-invalid': graderForm.errors.has('email') }"
            @keydown="graderForm.errors.clear('email')"
          />
          <has-error :form="graderForm" field="email"/>
        </b-form-group>
        Choose individual sections or <a href="#" @click="selectAllSections">select all</a>:
        <b-form-checkbox-group
          v-model="graderForm.selected_sections"
          :options="sectionOptions"
          :class="{ 'is-invalid': graderForm.errors.has('selected_sections') }"
          name="sections"
          @keydown="graderForm.errors.clear('selected_sections')"
        />
        <has-error :form="graderForm" field="selected_sections"/>
      </b-form>
      <template #modal-footer>
        <span v-if="sendingEmail">
          <b-spinner small type="grow"/>
          Sending Email..
        </span>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="submitInviteGrader"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-if="!isLoading && user.role === 2">
        <b-card header="default" header-html="<h2 class=&quot;h7&quot;>Section Graders</h2>">
          <b-card-text>
            <div v-if="user.email !== 'commons@libretexts.org'">
              <b-container>
                <b-row>
                  <b-button class="mb-2" variant="primary" size="sm" @click="initInviteGrader()">
                    Invite Grader
                  </b-button>
                </b-row>
              </b-container>
              <div v-if="course.graders.length">
                <b-form-group
                  id="head_grader"
                  label-cols-sm="3"
                  label-cols-lg="2"
                  label-for="Head Grader"
                >
                  <template v-slot:label>
                    Head Grader
                    <QuestionCircleTooltip :id="'head-grader-tooltip'"/>
                    <b-tooltip target="head-grader-tooltip"
                               triggers="hover focus"
                               delay="500"
                    >
                      Optionally choose a head grader. Head graders can be sent a summary of ungraded assignments by
                      visiting the Grading Notifications page.
                    </b-tooltip>
                  </template>
                  <b-form-row>
                    <b-col lg="6">
                      <b-form-select v-model="headGrader"
                                     title="Select a head grader"
                                     :options="graderOptions"
                                     @change="submitHeadGrader()"
                      />
                    </b-col>
                  </b-form-row>
                </b-form-group>
                <b-table striped hover
                         aria-label="Graders"
                         :fields="fields"
                         :items="graders"
                >
                  <template v-slot:cell(sections)="data">
                    {{ formatSections(data.item.sections) }}
                  </template>
                  <template v-slot:head(can_edit_rubric)>
                    Can edit rubric
                    <QuestionCircleTooltip :id="'can-edit-rubric-tooltip'"/>
                    <b-tooltip target="can-edit-rubric-tooltip"
                               triggers="hover focus"
                               delay="500"
                    >
                      Lets this grader add and edit rubrics from the Open Grader for the assignments they grade. Editing a
                      dynamic rubric rescores the students who were already graded with it.
                    </b-tooltip>
                  </template>
                  <template v-slot:cell(can_edit_rubric)="data">
                    <b-form-checkbox :key="`can-edit-rubric-${data.item.user_id}-${rubricEditorsKey}`"
                                     :checked="rubricEditorUserIds.includes(+data.item.user_id)"
                                     switch
                                     :aria-label="`${data.item.name} can edit rubrics`"
                                     @change="updateRubricEditor(data.item, $event)"
                    />
                  </template>
                  <template v-slot:cell(actions)="data">
                    <a
                      href=""
                      aria-label="Edit Section"
                      @click.prevent="initEditSections(data.item)"
                    >
                      <b-icon icon="pencil"
                              class="text-muted"
                              :aria-label="`Edit sections for ${data.item.name}`"
                      />
                    </a>
                    <a
                      href=""
                      aria-label="Remove grader"
                      @click.prevent="initRemoveGrader(data.item.user_id)"
                    >
                      <b-icon icon="trash"
                              class="text-muted"
                              :aria-label="`Remove ${data.item.name} as a grader`"
                      />
                    </a>
                  </template>
                </b-table>
              </div>
              <div v-show=" !course.graders.length">
                <b-alert show variant="info">
                  <span class="font-weight-bold">You currently have no graders associated with this course.</span>
                </b-alert>
              </div>
            </div>
            <div v-else>
              <b-alert :show="true" variant="info">
                <span class="font-weight-bold">You cannot invite graders to courses in the Commons.</span>
              </b-alert>
            </div>
          </b-card-text>
        </b-card>
        <b-card header="default"
                class="mt-3"
                header-html="<h2 class=&quot;h7&quot;>Override Grader Contact</h2>"
        >
          <b-card-text>
            <p>
              Students may have questions about their score and may contact the grader directly via each question's
              submissions page. For open-ended submissions,
              the person who graded the question will be sent the email. For auto-graded questions, either the section
              grader will be sent the email, or if they
              don't exist, the instructor will be sent the email. You may override this email contact below. Or, you may
              remove
              this option for your students by choosing "Do no provide a contact".
            </p>
            <b-form-group
              id="head_grader"
              label-cols-sm="3"
              label-cols-lg="2"
              label-for="grader_contact"
              label="Grader Contact"
            >
              <b-form-row>
                <b-col lg="6">
                  <b-form-select id="grader_contact"
                                 v-model="contactGraderOverride"
                                 title="Contact Override"
                                 :options="contactGraderOverrideOptions"
                                 @input="submitContactGraderOverride()"
                  />
                </b-col>
              </b-form-row>
            </b-form-group>
          </b-card-text>
        </b-card>
        <b-card header="default" header-html="<h2 class=&quot;h7&quot;>Assignment Grader Notifications</h2>"
                class="mt-3"
        >
          <b-card-text>
            <b-container>
              <p>With Grader Notifications, you can optionally let your graders know when assignments are closed and
                when students submit late submissions. These emails will be sent out once per day.</p>
              <b-form-checkbox
                id="when_assignments_are_closed"
                v-model="graderNotificationsForm.when_assignments_are_closed"
                name="when_assignments_are_closed"
                value="1"
                unchecked-value="0"
              >
                Notify graders when assignments are closed
              </b-form-checkbox>
              <b-form-checkbox
                id="for_late_submissions"
                v-model="graderNotificationsForm.for_late_submissions"
                name="for_late_submissions"
                value="1"
                unchecked-value="0"
              >
                Notify graders when students submit after the assignment is closed
              </b-form-checkbox>
              <hr>
              <p>
                Additionally, you can also send your graders reminders to finish grading ungraded assessments with one
                email sent per time period.
              </p>

              <b-form-group
                label-cols-sm="4"
                label-cols-lg="3"
                label="Frequency of reminders"
                label-for="frequency_of_reminders"
              >
                <b-form-row>
                  <div class="col-md-5">
                    <b-form-select id="frequency_of_reminders"
                                   v-model="graderNotificationsForm.num_reminders_per_week"
                                   title="Frequency of reminders"
                                   required
                                   :options="numRemindersPerWeekOptions"
                                   :class="{ 'is-invalid': graderNotificationsForm.errors.has('num_reminders_per_week') }"
                                   @change="graderNotificationsForm.errors.clear();resetForwardEmails($event)"
                    />
                    <has-error :form="graderNotificationsForm" field="num_reminders_per_week"/>
                  </div>
                </b-form-row>
              </b-form-group>
              <div v-show="showForwardOptions">
                <b-form-checkbox
                  v-show="hasHeadGrader"
                  id="copy_grading_reminder_to_head_grader"
                  v-model="graderNotificationsForm.copy_grading_reminder_to_head_grader"
                  name="copy_grading_reminder_to_head_grader"
                  value="1"
                  unchecked-value="0"
                >
                  Forward a copy to the Head Grader
                </b-form-checkbox>
                <b-form-checkbox
                  id="copy_grading_reminder_to_instructor"
                  v-model="graderNotificationsForm.copy_grading_reminder_to_instructor"
                  name="copy_grading_reminder_to_instructor"
                  value="1"
                  unchecked-value="0"
                >
                  Forward a copy to the Instructor
                </b-form-checkbox>
              </div>
              <b-row align-h="end">
                <b-button class="mb-2" variant="primary" size="sm" @click="updateGradingNotifications()">
                  Update
                </b-button>
              </b-row>
            </b-container>
          </b-card-text>
        </b-card>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import Form from 'vform'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import { mapGetters } from 'vuex'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import AllFormErrors from '~/components/AllFormErrors'

export default {
  middleware: 'auth',
  components: {
    Loading,
    AllFormErrors
  },
  metaInfo () {
    return { title: 'Course Graders' }
  },
  data: () => ({
    hasHeadGrader: false,
    showForwardOptions: false,
    numRemindersPerWeekOptions: [
      { value: 0, text: 'Never' },
      { value: 1, text: 'Once a week' },
      { value: 2, text: 'Twice a week' },
      { value: 3, text: 'Three times a week' },
      { value: 7, text: 'Every day' }
    ],
    graderNotificationsForm: new Form({
      when_assignments_are_closed: 0,
      for_late_submissions: 0,
      copy_grading_reminder_to_head_grader: 0,
      num_reminders_per_week: 0,
      copy_grading_reminder_to_instructor: 0
    }),
    graderNotifications: {},
    contactGraderOverride: null,
    contactGraderOverrideOptions: [],
    allFormErrors: [],
    headGrader: null,
    graderOptions: [],
    graderToRemoveId: 0,
    sectionOptions: [],
    graderFormType: 'addGrader',
    fields: [
      {
        key: 'name',
        isRowHeader: true
      },
      'email',
      {
        key: 'sections',
        label: 'Section(s)'
      },
      {
        key: 'can_edit_rubric',
        label: 'Can edit rubric'
      },
      'actions'

    ],
    sendingEmail: false,
    isLoading: true,
    graders: {},
    rubricEditorUserIds: [],
    rubricEditorsKey: 0,
    course: { graders: {} },
    grader_user_id: 0,
    sectionsForm: new Form({
      selected_sections: [],
      course_id: 0
    }),
    graderForm: new Form({
      email: '',
      selected_sections: [],
      course_id: 0
    })
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  mounted () {
    this.courseId = this.$route.params.courseId
    this.getGraderNotifications()
    this.getCourse(this.courseId)
    this.getRubricEditors()
  },
  methods: {
    async getRubricEditors () {
      try {
        const { data } = await axios.get(`/api/courses/${this.courseId}/rubric-editors`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.rubricEditorUserIds = data.rubric_editor_user_ids
        this.rubricEditorsKey++
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateRubricEditor (grader, canEdit) {
      try {
        const { data } = await axios.patch(`/api/courses/${this.courseId}/rubric-editors/${grader.user_id}`, { can_edit: canEdit })
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
      // show what the server has, whether or not the change went through
      await this.getRubricEditors()
    },
    resetForwardEmails (target) {
      this.showForwardOptions = target !== 0
      if (!this.showForwardOptions) {
        this.graderNotificationsForm.copy_grading_reminder_to_head_grader = 0
        this.graderNotificationsForm.copy_grading_reminder_to_instructor = 0
      }
    },
    async updateGradingNotifications () {
      try {
        const { data } = await this.graderNotificationsForm.patch(`/api/grader-notifications/${this.courseId}`)
        this.$noty[data.type](data.message)
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    async getGraderNotifications () {
      try {
        const { data } = await axios.get(`/api/grader-notifications/${this.courseId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        let graderNotifications = data.grading_notifications
        this.hasHeadGrader = data.head_grader
        if (graderNotifications) {
          this.graderNotificationsForm.when_assignments_are_closed = graderNotifications.when_assignments_are_closed
          this.graderNotificationsForm.for_late_submissions = graderNotifications.for_late_submissions
          this.graderNotificationsForm.copy_grading_reminder_to_head_grader = graderNotifications.copy_grading_reminder_to_head_grader
          this.graderNotificationsForm.copy_grading_reminder_to_instructor = graderNotifications.copy_grading_reminder_to_instructor
          this.graderNotificationsForm.num_reminders_per_week = graderNotifications.num_reminders_per_week
          this.showForwardOptions = this.graderNotificationsForm.num_reminders_per_week !== 0
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitContactGraderOverride () {
      try {
        const { data } = await axios.patch(`/api/contact-grader-overrides/${this.courseId}`, { contact_grader_override: this.contactGraderOverride })
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitHeadGrader () {
      try {
        const { data } = this.headGrader !== null
          ? await axios.patch(`/api/head-graders/${this.courseId}/${this.headGrader}`)
          : await axios.delete(`/api/head-graders/${this.courseId}`)

        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    cancelRemoveGrader () {
      this.$bvModal.hide('modal-confirm-remove')
    },
    selectAllSections () {
      let allSections = []
      for (let i = 0; i < this.sectionOptions.length; i++) {
        allSections.push(this.sectionOptions[i].value)
      }

      this.graderForm.selected_sections = allSections
      this.sectionsForm.selected_sections = allSections
    },
    initInviteGrader () {
      this.graderForm.selectedSections = []
      this.graderForm.email = ''
      this.graderForm.errors.clear()
      this.$bvModal.show('modal-invite-grader')
    },
    initEditSections (graderInfo) {
      console.log(graderInfo)
      this.grader_user_id = graderInfo.user_id
      this.sectionsForm.selected_sections = Object.keys(graderInfo.sections)
      this.$bvModal.show('modal-edit-sections')
    },
    async submitEditSections (bvModalEvt) {
      bvModalEvt.preventDefault()
      try {
        this.sectionsForm.course_id = this.courseId
        const { data } = await this.sectionsForm.patch(`/api/graders/${this.grader_user_id}`)
        if (data.type === 'success') {
          this.$noty.success(data.message)
          this.$bvModal.hide('modal-edit-sections')
          await this.getCourse(this.courseId)
          this.sendingEmail = false
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
          return false
        } else {
          this.allFormErrors = this.sectionsForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-edit-sections')
          this.$nextTick(() => {
            fixInvalid()
          })
        }
      }
    },
    formatSections (sections) {
      return Object.values(sections).join(', ')
    },
    async getCourse (courseId) {
      try {
        const { data } = await axios.get(`/api/courses/${courseId}`)
        this.course = data.course
        if (!this.sectionOptions.length) { // just do this on initializing
          for (let i = 0; i < this.course.sections.length; i++) {
            let section = this.course.sections[i]
            this.sectionOptions.push({ text: section.name, value: section.id })
          }
        }
        this.graders = this.course.graders
        this.graderOptions = [{ text: 'Please choose a head grader', value: null }]

        this.contactGraderOverrideOptions = this.graders.length ?
          [{ text: 'The section grader', value: null }, {
            text: 'Me',
            value: this.user.id
          }]
          : [{
            text: 'Me',
            value: this.user.id
          }]

        this.contactGraderOverride = data.course.contact_grader_override
        if (this.contactGraderOverride === null && !this.graders.length) {
          this.contactGraderOverride = this.user.id
        }
        for (let i = 0; i < this.course.co_instructors.length; i++) {
          let coInstructor = this.course.co_instructors[i]
          if (coInstructor.status === 'accepted') {
            let coInstructorInfo = { text: coInstructor.name, value: coInstructor.id }
            this.contactGraderOverrideOptions.push(coInstructorInfo)
          }
        }
        for (let i = 0; i < this.graders.length; i++) {
          let grader = this.graders[i]
          let graderInfo = { text: grader.name, value: grader.user_id }
          this.graderOptions.push(graderInfo)
          this.contactGraderOverrideOptions.push(graderInfo)
        }
        this.contactGraderOverrideOptions.push({
          text: 'Do not provide a contact',
          value: -1
        })
        this.isLoading = false
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    initRemoveGrader (userId) {
      this.$bvModal.show('modal-confirm-remove')
      this.graderToRemoveId = userId
    },
    async submitRemoveGrader () {
      try {
        const { data } = await axios.delete(`/api/graders/${this.courseId}/${this.graderToRemoveId}`)
        this.$noty[data.type](data.message)
        this.$bvModal.hide('modal-confirm-remove')
        if (data.type === 'error') {
          return false
        }
        // remove the grader
        await this.getCourse(this.courseId)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitInviteGrader (bvModalEvt) {
      bvModalEvt.preventDefault()
      if (this.sendingEmail) {
        this.$noty.info('Please be patient while we send the email.')
        return false
      }

      try {
        this.sendingEmail = true
        this.graderForm.course_id = this.courseId
        const { data } = await this.graderForm.post('/api/invitations/grader')
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.$bvModal.hide('modal-invite-grader')
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
          return false
        } else {
          this.allFormErrors = this.graderForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-invite-graders')
          this.$nextTick(() => {
            fixInvalid()
          })
        }
      }
      this.sendingEmail = false
    }
  }
}
</script>
